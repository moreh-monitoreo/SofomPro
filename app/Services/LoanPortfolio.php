<?php

namespace App\Services;

use App\Models\Loan;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Saldo, próxima cuota y mora de un préstamo, calculados siempre del mismo modo. */
class LoanPortfolio
{
    public function __construct(private LoanLedger $ledger)
    {
    }

    public function summary(Loan $loan, ?CarbonInterface $today = null): array
    {
        $today = ($today ?? today())->copy()->startOfDay();
        $loan->loadMissing('payment.details');
        $product = $loan->product();

        $details = $loan->payment?->details->sortBy('number')->values() ?? collect();

        $paid = $details->where('status', 'S');
        $unpaid = $details->where('status', '!=', 'S');
        $overdue = $unpaid->filter(fn ($d) => $d->isOverdue($today, $product->grace_days))->values();
        $next = $unpaid->first();

        $oldestOverdue = $overdue->first();
        $exigible = $this->ledger->exigible($details, $today);
        $charges = $exigible->map(fn ($d) => $this->ledger->charges($d, $product, $today));
        $moratory = round($charges->sum(fn ($c) => $c['moratory'] + $c['moratory_iva']), 2);
        $prepaid = (float) ($loan->payment?->prepaid_principal ?? 0);

        return [
            'has_plan' => $details->isNotEmpty(),
            'stage' => $loan->stage,
            'stage_label' => $loan->stage_label,
            'customer_stage_label' => Loan::CUSTOMER_STAGES[$loan->stage],
            'total_to_pay' => $this->sum($details, 'monthly_payment'),
            'paid_amount' => round($details->sum(fn ($d) => (float) ($d->payment_total ?? ($d->status === 'S' ? $d->monthly_payment : 0))) + $prepaid, 2),
            'pending_amount' => round($unpaid->sum(fn ($d) => $d->remainingTotal()), 2),
            'principal_balance' => $details->isEmpty()
                ? (float) $loan->amount
                : round((float) ($loan->payment->request_amount ?? $loan->amount) - $details->sum(fn ($d) => (float) $d->principal_paid) - $prepaid, 2),
            'installments' => $details->count(),
            'installments_paid' => $paid->count(),
            'next_installment' => $next ? [
                'id' => $next->id,
                'number' => $next->number,
                'due_date' => $next->dueDate()->toDateString(),
                'amount' => $this->ledger->charges($next, $product, $today)['total'],
                'days_until_due' => (int) $today->diffInDays($next->dueDate(), false),
            ] : null,
            'is_overdue' => $overdue->isNotEmpty(),
            'overdue_installments' => $overdue->count(),
            'overdue_amount' => round($overdue->sum(fn ($d) => $d->remainingTotal()), 2),
            'moratory_amount' => $moratory,
            'amount_due_today' => round($charges->sum('total'), 2),
            'days_overdue' => $oldestOverdue ? (int) $oldestOverdue->dueDate()->diffInDays($today) : 0,
            'grace_days' => $product->grace_days,
        ];
    }

    private function sum(Collection $details, string $column): float
    {
        return round($details->sum(fn ($d) => (float) $d->{$column}), 2);
    }
}
