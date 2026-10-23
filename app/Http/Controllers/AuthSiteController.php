<?php

namespace App\Http\Controllers;

use App\Models\LoanProduct;
use App\Services\LoanCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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

    public function privacy()
    {
        return Inertia::render('PrivacyNotice', [
            'company' => Arr::only(config('promas'), ['name', 'legal_name', 'rfc', 'address', 'phone', 'email', 'privacy_email', 'consent_version']),
        ]);
    }

    public function simulate(Request $request, LoanCalculator $calculator)
    {
        $request->validate(['type' => 'nullable|in:NORMAL,FUNERARIO']);

        $product = LoanProduct::forType($request->type);

        if (! $product->active) {
            return response()->json([
                'message' => 'Este producto no está disponible por el momento.',
                'errors' => ['type' => ['Este producto no está disponible por el momento.']],
            ], 422);
        }

        $request->validate([
            'amountCredit' => [
                'required', 'numeric', "min:{$product->min_amount}", "max:{$product->max_amount}",
                function ($attribute, $value, $fail) use ($product) {
                    if (fmod($value - $product->min_amount, max($product->amount_step, 1)) !== 0.0) {
                        $fail("El monto debe ser múltiplo de $".number_format($product->amount_step).'.');
                    }
                },
            ],
            'quantityPayments' => "required|integer|min:{$product->min_term}|max:{$product->max_term}",
        ], [
            'amountCredit.min' => "El monto mínimo es de $".number_format($product->min_amount).'.',
            'amountCredit.max' => "El monto máximo es de $".number_format($product->max_amount).'.',
            'quantityPayments.min' => "El plazo mínimo es de {$product->min_term} meses.",
            'quantityPayments.max' => "El plazo máximo es de {$product->max_term} meses.",
        ]);

        $result = $calculator->simulate((float) $request->amountCredit, (int) $request->quantityPayments, $product);

        // `payment` se conserva por compatibilidad con la versión anterior del simulador
        return response()->json($result + ['payment' => number_format($result['first_payment'], 2)]);
    }

    /** Condiciones vigentes de cada producto activo; el simulador las usa para armar sus controles. */
    public function simulatorConfig()
    {
        $products = collect(LoanProduct::TYPES)
            ->map(fn ($type) => LoanProduct::forType($type))
            ->filter(fn ($product) => $product->active)
            ->mapWithKeys(fn ($product) => [$product->type => [
                'type' => $product->type,
                'name' => $product->name,
                'annual_rate' => $product->annual_rate,
                'monthly_rate' => round($product->annual_rate / 12, 2),
                'iva' => $product->iva,
                'opening_fee' => $product->opening_fee,
                'min_amount' => $product->min_amount,
                'max_amount' => $product->max_amount,
                'amount_step' => $product->amount_step,
                'min_term' => $product->min_term,
                'max_term' => $product->max_term,
            ]]);

        return response()->json(['products' => $products]);
    }

    public function requestcredit()
    {
        return Inertia::render('RequestCredit');
    }
}
