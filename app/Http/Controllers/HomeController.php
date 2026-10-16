<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
class HomeController extends Controller
{
    public function index()
    {
        $loans = Loan::with(['payment.details'])->whereHas('customer', function($query) {
            $query->where('user_id', auth()->id());
        })
        ->get();

        foreach ($loans as $loan) {
            $loan->start_at = Carbon::parse($loan->start_at)->format('d/m/Y');
            $loan->total_payed = $loan->payment->details->where('status', 'S')->sum('monthly_payment');
        }

        return Inertia::render('Dashboard', [
            'loans' => $loans,
        ]);
    }
}
