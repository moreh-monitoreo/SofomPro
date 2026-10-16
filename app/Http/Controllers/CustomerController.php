<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Loan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Actions\Customers\GetCustomerLoansAction;

class CustomerController extends Controller
{

    public function show($id)
    {
        $customer = Customer::find($id);

        $loans = (new GetCustomerLoansAction)->execute($id);

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'loans' => $loans,
        ]);
    }

    public function payments($customer, $loan)
    {
        $customer = Customer::findOrFail($customer);
        $loan = Loan::with('payment.details')->where('customer_id', $customer->id)->findOrFail($loan);

        // Un cliente solo puede ver sus propios préstamos
        if (! auth()->user()->hasAnyRole(['Administrator', 'Advisor']) && $customer->user_id !== auth()->id()) {
            abort(403);
        }

        return Inertia::render('Customers/Payments', [
            'customer' => $customer,
            'loan' => $loan,
        ]);
    }
}
