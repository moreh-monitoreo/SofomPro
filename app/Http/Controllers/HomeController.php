<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Services\LoanPortfolio;
use Carbon\Carbon;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index(LoanPortfolio $portfolio)
    {
        $user = auth()->user();

        $isStaff = $user->isStaff();

        // El personal ve solo el resumen del equipo; "Mis préstamos" es del portal del cliente
        $loans = $isStaff ? collect() : Loan::with('payment.details')
            ->whereHas('customer', fn ($query) => $query->where('user_id', $user->id))
            ->latest('id')
            ->get()
            ->map(fn (Loan $loan) => [
                'id' => $loan->id,
                'customer_id' => $loan->customer_id,
                'folio' => $loan->folio ?: sprintf('PRM-%06d', $loan->id),
                'type' => $loan->type,
                'amount' => $loan->amount,
                'term' => $loan->term,
                'purpose' => $loan->purpose,
                'approved_at' => $this->date($loan->approved_at),
                'start_at' => $this->date($loan->start_at),
                'paid_at' => $this->date($loan->paid_at),
                'summary' => $portfolio->summary($loan),
            ]);

        return Inertia::render('Dashboard', [
            'loans' => $loans,
            'staff' => $isStaff ? $this->staffMetrics($portfolio) : null,
            'message' => session('message'),
        ]);
    }

    private function date($value): ?string
    {
        return $value ? Carbon::parse($value)->format('d/m/Y') : null;
    }

    /** Resumen para el equipo: solicitudes por atender y cartera en mora. */
    private function staffMetrics(LoanPortfolio $portfolio): array
    {
        $overdue = Loan::with('payment.details')->overdue()->get()->map(fn (Loan $loan) => $portfolio->summary($loan));

        return [
            'received' => Loan::stage('received')->count(),
            'in_validation' => Loan::stage('in_validation')->count(),
            'validated' => Loan::stage('validated')->count(),
            'incomplete' => Loan::stage('incomplete')->count(),
            'approved' => Loan::stage('approved')->count(),
            'overdue_clients' => $overdue->count(),
            'overdue_amount' => round($overdue->sum('overdue_amount'), 2),
        ];
    }
}
