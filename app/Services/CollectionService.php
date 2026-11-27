<?php

namespace App\Services;

use App\Mail\BrokenPromise;
use App\Models\CollectionAction;
use App\Models\LoanPayment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Mail;

/**
 * Seguimiento de promesas de pago. Una promesa se cumple cuando, desde el día en que se hizo, el cliente pagó
 * al menos lo prometido; se incumple si pasa su fecha sin completarse, y entonces se avisa al gestor.
 */
class CollectionService
{
    /** @return array{fulfilled: int, broken: int} */
    public function resolvePromises(?int $loanId = null, ?CarbonInterface $today = null, bool $markBroken = true): array
    {
        $today = ($today ?? today())->copy()->startOfDay();
        $result = ['fulfilled' => 0, 'broken' => 0];

        $promises = CollectionAction::with('loan.customer')
            ->where('promise_status', 'pendiente')
            ->when($loanId, fn ($q) => $q->where('loan_id', $loanId))
            ->get();

        foreach ($promises as $promise) {
            $paid = (float) LoanPayment::where('loan_id', $promise->loan_id)
                ->whereNull('reverted_at')
                ->where('paid_at', '>=', $promise->created_at->copy()->startOfDay())
                ->sum('amount');

            if ($paid + 0.005 >= (float) $promise->promise_amount) {
                $promise->forceFill(['promise_status' => 'cumplida', 'promise_resolved_at' => now()])->save();
                $result['fulfilled']++;

                continue;
            }

            if ($markBroken && $promise->promise_date->lt($today)) {
                $promise->forceFill(['promise_status' => 'incumplida', 'promise_resolved_at' => now()])->save();
                $result['broken']++;
                AuditLogger::record('collection.promise_broken', $promise, $promise->loan_id, after: [
                    'promise_amount' => $promise->promise_amount,
                    'promise_date' => $promise->promise_date->toDateString(),
                    'paid' => round($paid, 2),
                ]);
                $this->notifyCollector($promise, $paid);
            }
        }

        return $result;
    }

    private function notifyCollector(CollectionAction $promise, float $paid): void
    {
        $collector = $promise->loan?->collector ?? $promise->user;
        if (! $collector?->email || ! $collector->isActive()) {
            return;
        }

        try {
            Mail::to($collector->email)->send(new BrokenPromise($promise, round($paid, 2)));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
