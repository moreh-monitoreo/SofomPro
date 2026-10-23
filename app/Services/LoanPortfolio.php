<?php

namespace App\Services;

use App\Models\Loan;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Saldo, próxima cuota y mora de un préstamo, calculados siempre del mismo modo. */
class LoanPortfolio
{
    public function summary(Loan $loan, ?CarbonInterface $today = null): array
    {
        $today = ($today ?? today())->copy()->startOfDay();
        $loan->loadMissing('payment.details');

        $details = $loan->payment?->details->sortBy('number')->values() ?? collect();

        $paid = $details->where('status', 'S');
        $unpaid = $details->where('status', '!=', 'S');
        $overdue = $unpaid->filter(fn ($d) => $d->isOverdue($today))->values();
        $next = $unpaid->first();

        $oldestOverdue = $overdue->first();

        return [
            'has_plan' => $details->isNotEmpty(),
            'stage' => $loan->stage,
            'stage_label' => $loan->stage_label,
            'customer_stage_label' => Loan::CUSTOMER_STAGES[$loan->stage],
            'total_to_pay' => $this->sum($details, 'monthly_payment'),
            'paid_amount' => round($paid->sum(fn ($d) => (float) ($d->payment_total ?? $d->monthly_payment)), 2),
            'pending_amount' => $this->sum($unpaid, 'monthly_payment'),
            'principal_balance' => $paid->isEmpty() ? (float) ($loan->payment?->request_amount ?? $loan->amount) : (float) $paid->last()->balance,
            'installments' => $details->count(),
            'installments_paid' => $paid->count(),
            'next_installment' => $next ? [
                'id' => $next->id,
                'number' => $next->number,
                'due_date' => $next->dueDate()->toDateString(),
                'amount' => (float) $next->monthly_payment,
                'days_until_due' => (int) $today->diffInDays($next->dueDate(), false),
            ] : null,
            'is_overdue' => $overdue->isNotEmpty(),
            'overdue_installments' => $overdue->count(),
            'overdue_amount' => $this->sum($overdue, 'monthly_payment'),
            'days_overdue' => $oldestOverdue ? (int) $oldestOverdue->dueDate()->diffInDays($today) : 0,
        ];
    }

    private function sum(Collection $details, string $column): float
    {
        return round($details->sum(fn ($d) => (float) $d->{$column}), 2);
    }
}
