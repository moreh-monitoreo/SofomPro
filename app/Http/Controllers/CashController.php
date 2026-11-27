<?php

namespace App\Http\Controllers;

use App\Http\Responses\JsonResponse;
use App\Models\CashCut;
use App\Models\LoanPayment;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Corte de caja y conciliación. Toma los pagos aplicados (sin los revertidos) por fecha de pago:
 * el efectivo se cuadra contra lo contado al cerrar el día y lo demás (transferencias, depósitos y Stripe)
 * se marca como conciliado al encontrarlo en el estado de cuenta.
 */
class CashController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);
        $movements = $this->movements($from, $to);
        $singleDay = $from->isSameDay($to);
        $cut = $singleDay ? CashCut::with('closedBy:id,name')->whereDate('date', $from)->first() : null;

        return Inertia::render('Cash/Index', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'status' => $request->input('status', 'all')],
            'methods' => LoanPayment::METHODS,
            'movements' => $movements->map(fn (LoanPayment $m) => $this->present($m, $cut))->values(),
            'totals' => $this->totals($movements),
            'byUser' => $movements->groupBy(fn ($m) => $m->registeredBy?->name ?? ($m->method === 'STRIPE' ? 'Pago en línea' : 'Sin registrar'))
                ->map(fn ($group, $name) => ['name' => $name, 'count' => $group->count(), 'amount' => round($group->sum('amount'), 2)])
                ->values(),
            'cut' => $cut,
            'canClose' => $singleDay && ! $cut && $from->lte(today()),
            'pendingReconciliation' => LoanPayment::whereNull('reverted_at')->whereNull('reconciled_at')->where('method', '!=', 'EFECTIVO')->count(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $movements = $this->movements($from, $to);

        return response()->streamDownload(function () use ($movements) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fecha', 'Folio', 'Cliente', 'Forma de pago', 'Referencia', 'Importe', 'Comisión Stripe', 'Neto Stripe', 'Registró', 'Conciliado', 'Referencia bancaria']);
            foreach ($movements as $m) {
                fputcsv($out, [
                    $m->paid_at->format('Y-m-d H:i'),
                    $m->loan?->folio ?: sprintf('PRM-%06d', $m->loan_id),
                    $m->loan?->customer?->full_name,
                    LoanPayment::METHODS[$m->method] ?? $m->method,
                    $m->reference,
                    number_format($m->amount, 2, '.', ''),
                    $m->processor_fee !== null ? number_format($m->processor_fee, 2, '.', '') : '',
                    $m->processor_net !== null ? number_format($m->processor_net, 2, '.', '') : '',
                    $m->registeredBy?->name ?? ($m->method === 'STRIPE' ? 'Pago en línea' : ''),
                    $m->reconciled_at?->format('Y-m-d') ?? ($m->method === 'EFECTIVO' ? 'N/A' : 'Pendiente'),
                    $m->bank_reference,
                ]);
            }
            fclose($out);
        }, "corte-{$from->toDateString()}-a-{$to->toDateString()}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Cierra el día: compara el efectivo contado con el registrado y guarda los totales por forma de pago. */
    public function close(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date|before_or_equal:today',
            'cash_counted' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ], [
            'date.before_or_equal' => 'No se puede cerrar un día que aún no llega.',
            'cash_counted.required' => 'Captura el efectivo contado en caja.',
        ]);

        $date = Carbon::parse($data['date'])->startOfDay();

        $cut = DB::transaction(function () use ($date, $data, $request) {
            if (CashCut::whereDate('date', $date)->lockForUpdate()->exists()) {
                return null;
            }

            $movements = $this->movements($date, $date->copy());
            $totals = $this->totals($movements);
            $expected = $totals['by_method']['EFECTIVO']['amount'] ?? 0.0;

            return CashCut::create([
                'date' => $date->toDateString(),
                'totals' => $totals['by_method'],
                'total' => $totals['total'],
                'cash_expected' => $expected,
                'cash_counted' => round((float) $data['cash_counted'], 2),
                'cash_difference' => round((float) $data['cash_counted'] - $expected, 2),
                'notes' => $data['notes'] ?? null,
                'closed_by' => $request->user()->id,
            ]);
        });

        if (! $cut) {
            return JsonResponse::error('El corte de ese día ya está cerrado.', 409);
        }

        AuditLogger::record('cash.closed', $cut, after: [
            'date' => $cut->date->toDateString(),
            'total' => $cut->total,
            'cash_expected' => $cut->cash_expected,
            'cash_counted' => $cut->cash_counted,
            'cash_difference' => $cut->cash_difference,
        ], note: $cut->notes);

        $message = $cut->cash_difference == 0.0
            ? 'Corte cerrado: el efectivo cuadra.'
            : 'Corte cerrado con una diferencia de $'.number_format($cut->cash_difference, 2).' en efectivo.';

        return JsonResponse::success($cut, $message);
    }

    /** Marca pagos que no son en efectivo como encontrados en el estado de cuenta (o en un depósito de Stripe). */
    public function reconcile(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'bank_reference' => 'required|string|max:120',
        ], [
            'ids.required' => 'Selecciona al menos un pago.',
            'bank_reference.required' => 'Captura la referencia del movimiento bancario o del depósito de Stripe.',
        ]);

        $movements = LoanPayment::whereIn('id', $data['ids'])->get();
        $invalid = $movements->reject(fn ($m) => $m->needsReconciliation() && ! $m->reconciled_at);
        if ($movements->count() !== count(array_unique($data['ids'])) || $invalid->isNotEmpty()) {
            return JsonResponse::error('Solo se pueden conciliar pagos vigentes que no sean en efectivo y que aún no estén conciliados.', 422);
        }

        $now = now();
        foreach ($movements as $movement) {
            $movement->forceFill(['reconciled_at' => $now, 'reconciled_by' => $request->user()->id, 'bank_reference' => $data['bank_reference']])->save();
            AuditLogger::record('payment.reconciled', $movement, $movement->loan_id, after: [
                'amount' => $movement->amount,
                'method' => $movement->method,
                'bank_reference' => $data['bank_reference'],
            ]);
        }

        $total = round($movements->sum('amount'), 2);
        $net = round($movements->sum(fn ($m) => $m->processor_net ?? $m->amount), 2);

        return JsonResponse::success(
            ['ids' => $movements->pluck('id'), 'total' => $total, 'net' => $net],
            "Se conciliaron {$movements->count()} pagos por $".number_format($total, 2).($net !== $total ? ' (neto de comisiones: $'.number_format($net, 2).')' : '').'.',
        );
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(Request $request): array
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $from = Carbon::parse($request->input('from', today()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', $from->toDateString()))->startOfDay();

        return [$from, $to];
    }

    private function movements(Carbon $from, Carbon $to): Collection
    {
        $status = request('status', 'all');

        return LoanPayment::with(['loan:id,folio,customer_id', 'loan.customer:id,name,second_name,last_name,maiden_name', 'registeredBy:id,name'])
            ->whereNull('reverted_at')
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($status === 'pending', fn ($q) => $q->whereNull('reconciled_at')->where('method', '!=', 'EFECTIVO'))
            ->when($status === 'reconciled', fn ($q) => $q->whereNotNull('reconciled_at'))
            ->orderBy('paid_at')
            ->get();
    }

    private function totals(Collection $movements): array
    {
        $byMethod = [];
        foreach (array_keys(LoanPayment::METHODS) as $method) {
            $group = $movements->where('method', $method);
            if ($group->isNotEmpty()) {
                $byMethod[$method] = [
                    'count' => $group->count(),
                    'amount' => round($group->sum('amount'), 2),
                    'fees' => round($group->sum(fn ($m) => (float) $m->processor_fee), 2),
                ];
            }
        }

        return ['by_method' => $byMethod, 'total' => round($movements->sum('amount'), 2), 'count' => $movements->count()];
    }

    private function present(LoanPayment $m, ?CashCut $cut): array
    {
        return [
            'id' => $m->id,
            'loan_id' => $m->loan_id,
            'folio' => $m->loan?->folio ?: sprintf('PRM-%06d', $m->loan_id),
            'customer' => $m->loan?->customer?->full_name,
            'paid_at' => $m->paid_at->toIso8601String(),
            'method' => $m->method,
            'reference' => $m->reference,
            'amount' => $m->amount,
            'processor_fee' => $m->processor_fee,
            'processor_net' => $m->processor_net,
            'registered_by' => $m->registeredBy?->name ?? ($m->method === 'STRIPE' ? 'Pago en línea' : null),
            'reconciled_at' => $m->reconciled_at?->toIso8601String(),
            'bank_reference' => $m->bank_reference,
            'needs_reconciliation' => $m->needsReconciliation() && ! $m->reconciled_at,
            // Registrado después de cerrar el corte: no está en los totales guardados de ese día
            'after_cut' => $cut && $m->created_at->gt($cut->created_at),
        ];
    }
}
