<?php

namespace App\Http\Controllers;

use App\Actions\Loans\GenerateLoanDocumentsAction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuthSiteController extends Controller
{
    public function whoami()
    {
        return Inertia::render('WhoAmI', []);
    }

    public function personalloan()
    {
        return Inertia::render('PersonalLoan', []);
    }

    public function loanguarantee()
    {
        return Inertia::render('LoanGuarantee', []);
    }

    public function contact()
    {
        return Inertia::render('Contact', []);
    }

    public function simulate(Request $request)
    {
        $request->validate([
            'amountCredit' => 'required|numeric|min:1',
            'quantityPayments' => 'required|integer|min:1|max:60',
            'type' => 'nullable|in:NORMAL,FUNERARIO',
        ]);

        // Tasa ordinaria fija sobre saldo insoluto (Cláusula Séptima): con garantía 74.28% (6.19% mensual),
        // sin garantía 79.56% (6.63% mensual).
        // Pago mensual vencido con cuota fija (amortización francesa); el IVA se calcula sobre los intereses.
        $rate = GenerateLoanDocumentsAction::monthlyRate($request->type);
        $n = (int) $request->quantityPayments;
        $amount = (float) $request->amountCredit;

        $payment = $amount * $rate / (1 - pow(1 + $rate, -$n));

        return response()->json([
            'payment' => number_format($payment, 2),
            'monthly_rate' => round($rate * 100, 2),
            'annual_rate' => $request->type === 'FUNERARIO' ? 74.28 : 79.56,
        ]);
    }

    public function requestcredit()
    {
        return Inertia::render('RequestCredit');
    }
}
