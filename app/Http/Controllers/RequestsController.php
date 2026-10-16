<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Actions\Loans\PaginateLoans;

class RequestsController extends Controller
{
    public function index()
    {
        $loans = (new PaginateLoans())->execute();

        return Inertia::render('Request/Index', [
            'loans' => $loans
        ]);
    }

    public function search(Request $request)
    {
        $loans = (new PaginateLoans())->execute($request->search);
        
        return response()->json($loans);
    }
}
