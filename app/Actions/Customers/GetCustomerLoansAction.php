<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\Loan;

class GetCustomerLoansAction
{
    public function execute($id)
    {
        $customer = Customer::find($id);
        
        $loans = Loan::where('customer_id', $id)
            ->orderBy('id', 'desc')
            ->get();

        return $loans;
    }
}