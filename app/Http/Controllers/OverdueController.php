<?php

namespace App\Http\Controllers;

use App\Models\CollectionAction;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\LoanPortfolio;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Cartera vencida: clientes con cuotas pasadas de su fecha límite sin liquidar, con su gestor y su última gestión. */
class OverdueController extends Controller
{
    public function index(Request $request, LoanPortfolio $portfolio)
    {
        $user = $request->user();
        $onlyMine = self::onlyAssigned($user);
        $mine = $onlyMine || $request->boolean('mine');
        $rows = $this->rows($portfolio, $mine ? $user->id : null);
        $canAssign = $user->hasAnyRole(User::APPROVER_ROLES);

        return Inertia::render('Portfolio/Overdue', [
            'rows' => $rows,
            'totals' => [
                'clients' => $rows->count(),
                'amount' => round($rows->sum('overdue_amount'), 2),
                'installments' => $rows->sum('overdue_installments'),
            ],
            'graceDays' => collect(LoanProduct::TYPES)->mapWithKeys(fn ($type) => [$type => LoanProduct::forType($type)->grace_days]),
            'filters' => ['mine' => $mine],
            'onlyMine' => $onlyMine,
            'canAssign' => $canAssign,
            'collectors' => $canAssign ? CollectionController::collectors() : [],
            'outcomes' => CollectionAction::OUTCOMES,
        ]);
    }

    public function export(Request $request, LoanPortfolio $portfolio): StreamedResponse
    {
        $user = $request->user();
        $rows = $this->rows($portfolio, self::onlyAssigned($user) || $request->boolean('mine') ? $user->id : null);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Cliente', 'Teléfono', 'Correo', 'Préstamo', 'Cuotas vencidas', 'Monto vencido', 'Moratorios con IVA', 'Días de atraso', 'Primer vencimiento sin pagar', 'Gestor', 'Última gestión', 'Promesa de pago']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['customer'], $row['phone'], $row['email'], $row['loan_id'], $row['overdue_installments'],
                    number_format($row['overdue_amount'], 2, '.', ''), number_format($row['moratory_amount'], 2, '.', ''), $row['days_overdue'], $row['oldest_due_date'],
                    $row['collector']['name'] ?? '',
                    $row['last_action'] ? $row['last_action']['date'].' '.(CollectionAction::OUTCOMES[$row['last_action']['outcome']] ?? $row['last_action']['outcome']) : '',
                    $row['promise'] ? '$'.number_format($row['promise']['amount'], 2).' al '.$row['promise']['date'] : '',
                ]);
            }
            fclose($out);
        }, 'cartera-vencida-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** El gestor de cobranza sin otro rol del equipo solo ve la cartera que tiene asignada. */
    public static function onlyAssigned(User $user): bool
    {
        return $user->hasRole('Gestor de cobranza') && ! $user->hasAnyRole([...User::COLLECTION_ROLES, 'Analista de crédito']);
    }

    /** @return Collection<int, array> ordenadas del mayor al menor atraso */
    public function rows(LoanPortfolio $portfolio, ?int $collectorId = null): Collection
    {
        return Loan::with(['customer', 'payment.details', 'collector:id,name', 'collectionActions' => fn ($q) => $q->latest('id')])
            ->overdue()
            ->when($collectorId, fn ($q) => $q->where('collector_id', $collectorId))
            ->get()
            ->map(function (Loan $loan) use ($portfolio) {
                $summary = $portfolio->summary($loan);
                $oldest = $loan->payment->details->where('status', '!=', 'S')->sortBy('number')
                    ->first(fn ($d) => $d->isOverdue(null, $loan->product()->grace_days));
                $last = $loan->collectionActions->first();
                $promise = $loan->collectionActions->firstWhere('promise_status', 'pendiente');

                return [
                    'loan_id' => $loan->id,
                    'customer_id' => $loan->customer->id,
                    'customer' => $loan->customer->full_name,
                    'phone' => $loan->customer->phone,
                    'email' => $loan->customer->email,
                    'overdue_installments' => $summary['overdue_installments'],
                    'overdue_amount' => $summary['overdue_amount'],
                    'moratory_amount' => $summary['moratory_amount'],
                    'days_overdue' => $summary['days_overdue'],
                    'oldest_due_date' => $oldest?->dueDate()->toDateString(),
                    'pending_amount' => $summary['pending_amount'],
                    'collector' => $loan->collector?->only('id', 'name'),
                    'last_action' => $last ? ['date' => $last->created_at->toDateString(), 'outcome' => $last->outcome] : null,
                    'promise' => $promise ? ['amount' => $promise->promise_amount, 'date' => $promise->promise_date->toDateString()] : null,
                ];
            })
            ->sortByDesc('days_overdue')
            ->values();
    }
}
