<?php

namespace App\Actions\Loans;

use App\Models\Loan;

class PaginateLoans
{
    public function execute($search = null)
    {
        return Loan::with('customer')
            ->whereNull('approved_at')
            ->whereNull('reject_at')
            ->when($search, function ($query, $search) {
                $query->whereHas('customer', function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('second_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('maiden_name', 'like', "%{$search}%")
                        ->orWhere('rfc', 'like', "%{$search}%")
                        ->orWhere('curp', 'like', "%{$search}%");
                })
                ->orWhere('policy_folio', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate();
    }
}