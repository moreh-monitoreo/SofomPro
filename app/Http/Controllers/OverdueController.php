<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Services\LoanPortfolio;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Cartera vencida: clientes con cuotas pasadas de su fecha límite sin liquidar. */
class OverdueController extends Controller
{
    public function index(LoanPortfolio $portfolio)
    {
        $rows = $this->rows($portfolio);

        return Inertia::render('Portfolio/Overdue', [
            'rows' => $rows,
            'totals' => [
                'clients' => $rows->count(),
                'amount' => round($rows->sum('overdue_amount'), 2),
                'installments' => $rows->sum('overdue_installments'),
            ],
            'graceDays' => (int) config('promas.overdue_grace_days', 0),
        ]);
    }

    public function export(LoanPortfolio $portfolio): StreamedResponse
    {
        $rows = $this->rows($portfolio);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Cliente', 'Teléfono', 'Correo', 'Préstamo', 'Cuotas vencidas', 'Monto vencido', 'Días de atraso', 'Primer vencimiento sin pagar']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['customer'], $row['phone'], $row['email'], $row['loan_id'], $row['overdue_installments'],
                    number_format($row['overdue_amount'], 2, '.', ''), $row['days_overdue'], $row['oldest_due_date'],
                ]);
            }
            fclose($out);
        }, 'cartera-vencida-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return Collection<int, array> ordenadas del mayor al menor atraso */
    public function rows(LoanPortfolio $portfolio): Collection
    {
        return Loan::with(['customer', 'payment.details'])
            ->overdue()
            ->get()
            ->map(function (Loan $loan) use ($portfolio) {
                $summary = $portfolio->summary($loan);
                $oldest = $loan->payment->details->where('status', '!=', 'S')->sortBy('number')
                    ->first(fn ($d) => $d->isOverdue());

                return [
                    'loan_id' => $loan->id,
                    'customer_id' => $loan->customer->id,
                    'customer' => $loan->customer->full_name,
                    'phone' => $loan->customer->phone,
                    'email' => $loan->customer->email,
                    'overdue_installments' => $summary['overdue_installments'],
                    'overdue_amount' => $summary['overdue_amount'],
                    'days_overdue' => $summary['days_overdue'],
                    'oldest_due_date' => $oldest?->dueDate()->toDateString(),
                    'pending_amount' => $summary['pending_amount'],
                ];
            })
            ->sortByDesc('days_overdue')
            ->values();
    }
}
