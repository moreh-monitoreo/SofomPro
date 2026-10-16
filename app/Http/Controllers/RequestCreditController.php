<?php

namespace App\Http\Controllers;

use App\Actions\IncrementScore;
use App\Http\Responses\JsonResponse;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequestCreditController extends Controller
{
    public function createcustomer(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|string|max:10',
            'birthday' => 'required|date',
            'curp' => 'required|string|size:18',
            'rfc' => 'required|string|max:13',
            'email' => 'required|email|max:255',
            'phone' => ['required', 'regex:/^[0-9]{10}$/'],
        ]);

        $data = $request->only(['email', 'phone', 'name', 'second_name', 'last_name', 'maiden_name', 'gender', 'birthday', 'state', 'rfc', 'curp']);

        $status = Status::where('name', 'PROFILE REGISTERED')->firstOrFail();

        $data['country'] = 'Mexico';
        $data['status_id'] = $status->id;

        $customer = Customer::updateOrCreate(['curp' => $data['curp']], $data);
        $customer->status = $status;

        // Reutiliza la solicitud pendiente si el cliente regresa al primer paso
        $loan = Loan::where('customer_id', $customer->id)
            ->whereNull('approved_at')
            ->whereNull('reject_at')
            ->latest('id')
            ->first() ?? Loan::create(['customer_id' => $customer->id]);

        $customer->loan = $loan;

        return JsonResponse::success($customer, $status->name, 201);
    }

    public function addaddress(Request $request, Customer $customer, IncrementScore $incrementScore)
    {
        $status = Status::where('name', 'ADDRESS REGISTERED')->firstOrFail();
        $address = $customer->addresses()->updateOrCreate([],
            $request->only([
                'street', 'interior_number', 'outdoor_number', 'suburb', 'postal_code', 'years_living', 'type'
            ])
        );

        $customer->status_id = $status->id;
        $customer->save();
        $customer->status = $status;

        $incrementScore->handle($request->loan_id, 'live_time', $request->years_living);

        $incrementScore->handle($request->loan_id, 'home_type', $request->type);

        return JsonResponse::success($customer, $status->name, 201);
    }

    public function updatecustomer(Request $request, Customer $customer, IncrementScore $incrementScore)
    {
        $status = Status::where('name', $request->civil_status ? 'PROFILE COMPLETED' : 'PROFILE 2 REGISTERED')->firstOrFail();

        $customer->update(
            array_merge(
                ['status_id' => $status->id],
                $request->only([
                    'name', 'second_name', 'last_name', 'maiden_name', 'gender', 'birthday', 'state', 'rfc', 'curp', 'civil_status', 'level_study', 'own_car', 'economic_dependents'
                ]),
                ['country' => 'Mexico']
            )
        );

        if($status->name == 'PROFILE COMPLETED') {
            $incrementScore->handle($request->loan_id, 'civil_status', $request->civil_status);
            $incrementScore->handle($request->loan_id, 'car_status', $request->own_car);
            $incrementScore->handle($request->loan_id, 'economic_dependents', $request->economic_dependents);
        } else {
            $age = Carbon::parse($customer->birthday)->age;

            $incrementScore->handle($request->loan_id, 'age', $age);
        }


        $customer->status = $status;

        return JsonResponse::success($customer, $status->name);
    }

    public function createemployment(Request $request, Customer $customer, IncrementScore $incrementScore)
    {
        $status = Status::where('name', 'EMPLOYMENT REGISTERED')->firstOrFail();

        $customer->employment()->updateOrCreate([], [
            'type' => $request->type,
            'area' => $request->area,
            'main_activity' => $request->main_activity,
            'company' => $request->company,
            'job' => $request->job,
            'seniority_working' => $request->seniority_working,
            'phone' => $request->phone,
            'imss' => $request->imss,
            'insurance_major_medical_expenses' => $request->insurance_major_medical_expenses,
            'monthly_income' => $request->monthly_income,
            'other_income' => $request->other_income,
        ]);

        $customer->status_id = $status->id;
        $customer->save();
        $customer->status = $status;

        $incrementScore->handle($request->loan_id, 'employment_situation', $request->type);
        $incrementScore->handle($request->loan_id, 'sector', $request->area);

        return JsonResponse::success($customer, $status->name, 201);
    }

    public function updateemployment(Request $request, Customer $customer, IncrementScore $incrementScore)
    {
        $status = Status::where('name', 'EMPLOYMENT COMPLETED')->firstOrFail();

        $customer->employment()->update(
            $request->only(['company', 'job', 'seniority_working', 'phone', 'imss', 'insurance_major_medical_expenses', 'monthly_income', 'other_income'])
        );

        $customer->status_id = $status->id;
        $customer->save();
        $customer->status = $status;

        $incrementScore->handle($request->loan_id, 'seniority', $request->seniority_working);
        $incrementScore->handle($request->loan_id, 'social_security', $request->imss);
        $incrementScore->handle($request->loan_id, 'sgmm', $request->insurance_major_medical_expenses);
        $incrementScore->handle($request->loan_id, 'salary', $request->monthly_income);
        $incrementScore->handle($request->loan_id, 'another_salary', $request->other_income);

        return JsonResponse::success($customer, $status->name);
    }

    public function storeloan(Request $request, Customer $customer, IncrementScore $incrementScore)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'term' => 'required|integer|min:1',
            'purpose' => 'required|string',
            'purpose_description' => 'required|string',
            'policy_folio' => 'required_if:type,FUNERARIO|nullable|string|max:255',
        ], [
            'policy_folio.required_if' => 'El folio del plan funerario es requerido.',
        ]);

        $status = Status::where('name', 'LOAN CREATED')->firstOrFail();

        $loan = Loan::where('customer_id', $customer->id)->latest()->first();
        
        if(!$loan) {
            $loan = Loan::create([
                'customer_id' => $customer->id,
            ]);
        }

        $loan->update([
            'type' => $request->type === 'FUNERARIO' && $request->policy_folio ? 'FUNERARIO' : 'NORMAL',
            'policy_folio' => $request->policy_folio,
            'amount' => $request->amount,
            'purpose' => $request->purpose,
            'purpose_description' => $request->purpose_description,
            'approved_at' => $request->approved_at,
            'term' => $request->term,
            'start_at' => $request->start_at,
            'paid_at' => $request->paid_at,
        ]);

        $customer->status_id = $status->id;
        $customer->save();

        $incrementScore->handle($request->loan_id, 'months', $request->term);
        $incrementScore->handle($request->loan_id, 'purpose', $request->purpose);

        return JsonResponse::success($loan, $status->name, 201);
    }
}
