<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StatisticsController extends Controller
{
    public function payments()
    {
        $totalLoans = Loan::whereNotNull('approved_at')
            ->whereNull('reject_at')
            ->sum('amount');

        $paidLoans = Loan::whereNotNull('paid_at')
            ->sum('amount');

        $activeLoans = Loan::whereNotNull('approved_at')
            ->whereNull('reject_at')
            ->whereNull('paid_at')
            ->sum('amount');

        $loansCount = [
            'total' => Loan::whereNotNull('approved_at')
                ->whereNull('reject_at')
                ->count(),
            'paid' => Loan::whereNotNull('paid_at')
                ->count(),
            'active' => Loan::whereNotNull('approved_at')
                ->whereNull('reject_at')
                ->whereNull('paid_at')
                ->count()
        ];

        // Obtener préstamos activos con sus pagos
        $activeLoansWithPayments = Loan::with(['payment.details'])
            ->whereNotNull('approved_at')
            ->whereNull('reject_at')
            ->whereNull('paid_at')
            ->get();

        $paymentProgress = [];
        foreach ($activeLoansWithPayments as $loan) {
            $totalToPay = $loan->payment->payment_amount;
            $totalPaid = $loan->payment->details->where('status', 'S')->sum('monthly_payment');
            $progress = ($totalPaid / $totalToPay) * 100;

            $paymentProgress[] = [
                'customer' => $loan->customer->full_name,
                'amount' => $loan->amount,
                'totalToPay' => $totalToPay,
                'totalPaid' => $totalPaid,
                'progress' => round($progress, 2)
            ];
        }

        // Ordenar por progreso de mayor a menor
        usort($paymentProgress, function($a, $b) {
            return $b['progress'] <=> $a['progress'];
        });

        return Inertia::render('Statistics/Payments', [
            'statistics' => [
                'totalLoans' => $totalLoans,
                'paidLoans' => $paidLoans,
                'activeLoans' => $activeLoans,
                'loansCount' => $loansCount,
                'paymentProgress' => $paymentProgress
            ]
        ]);
    }
}
