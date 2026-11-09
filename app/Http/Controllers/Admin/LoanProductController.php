<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoanProductController extends Controller
{
    public function index()
    {
        $products = collect(LoanProduct::TYPES)->map(function ($type) {
            $product = LoanProduct::forType($type);

            return $product->only([
                'type', 'name', 'annual_rate', 'opening_fee', 'iva', 'min_amount', 'max_amount', 'amount_step', 'min_term', 'max_term', 'active',
                'moratory_rate', 'grace_days', 'early_payoff_penalty', 'min_forced_months',
            ]) + ['required_documents' => $product->requiredDocuments(), 'updated_at' => $product->updated_at];
        })->values();

        // Tipos que se pueden exigir: los que sube el cliente en su solicitud y los del catálogo del equipo
        $documentTypes = collect(LoanProduct::DEFAULT_DOCUMENTS)
            ->merge(\App\Models\DocumentType::orderBy('name')->pluck('name'))
            ->unique()->values();

        return Inertia::render('Admin/LoanProducts', ['products' => $products, 'documentTypes' => $documentTypes]);
    }

    public function update(Request $request, string $type)
    {
        abort_unless(in_array($type, LoanProduct::TYPES, true), 404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'annual_rate' => 'required|numeric|min:1|max:300',
            'opening_fee' => 'required|numeric|min:0|max:20',
            'iva' => 'required|numeric|min:0|max:30',
            'min_amount' => 'required|integer|min:100',
            'max_amount' => 'required|integer|gte:min_amount|max:1000000',
            'amount_step' => 'required|integer|min:1',
            'min_term' => 'required|integer|min:1|max:60',
            'max_term' => 'required|integer|gte:min_term|max:60',
            'active' => 'required|boolean',
            'moratory_rate' => 'required|numeric|min:0|max:300',
            'grace_days' => 'required|integer|min:0|max:60',
            'early_payoff_penalty' => 'required|numeric|min:0|max:20',
            'min_forced_months' => 'required|integer|min:0|max:60',
            'required_documents' => 'present|array',
            'required_documents.*' => 'string|max:255|distinct',
        ], [
            'annual_rate.min' => 'La tasa anual debe ser de al menos 1%.',
            'max_amount.gte' => 'El monto máximo no puede ser menor al mínimo.',
            'max_term.gte' => 'El plazo máximo no puede ser menor al mínimo.',
        ]);

        if (($data['max_amount'] - $data['min_amount']) % $data['amount_step'] !== 0) {
            return back()->withErrors(['amount_step' => 'La diferencia entre el monto máximo y el mínimo debe ser múltiplo del incremento.']);
        }

        $product = LoanProduct::forType($type);
        $before = $product->only(array_keys($data));
        $product = LoanProduct::updateOrCreate(['type' => $type], $data);
        AuditLogger::record('product.updated', $product, before: $before, after: $product->only(array_keys($data)), note: $type);

        return back()->with('message', 'Producto actualizado. Los cambios ya se reflejan en el simulador y en los nuevos préstamos.');
    }
}
