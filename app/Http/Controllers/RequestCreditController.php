<?php

namespace App\Http\Controllers;

use App\Actions\IncrementScore;
use App\Http\Middleware\EnsureRequestCreditOwner;
use App\Http\Responses\JsonResponse;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Status;
use App\Rules\Curp;
use App\Rules\Rfc;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RequestCreditController extends Controller
{
    /** Documentos que el cliente debe subir en su solicitud: campo => tipo guardado en el expediente. */
    public const REQUIRED_DOCUMENTS = [
        'ine_front' => 'INE (frente)',
        'ine_back' => 'INE (reverso)',
        'proof_of_address' => 'Comprobante de domicilio',
        'bank_statement' => 'Estado de cuenta bancario',
    ];

    public function createcustomer(Request $request)
    {
        $request->merge([
            'curp' => strtoupper(trim((string) $request->curp)),
            'rfc' => strtoupper(trim((string) $request->rfc)),
        ]);

        $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|string|in:Femenino,Masculino',
            'birthday' => 'required|date|before:-18 years',
            'curp' => ['required', 'string', new Curp],
            'rfc' => ['required', 'string', 'max:13', new Rfc],
            'email' => 'required|email|max:255',
            'phone' => ['required', 'regex:/^[0-9]{10}$/'],
            'accept_privacy' => 'accepted',
            'authorize_credit_check' => 'accepted',
        ], [
            'birthday.before' => 'Debes ser mayor de edad para solicitar un préstamo.',
            'accept_privacy.accepted' => 'Debes aceptar el Aviso de Privacidad para continuar.',
            'authorize_credit_check.accepted' => 'Debes autorizar la consulta en Sociedades de Información Crediticia para continuar.',
        ]);

        $existing = Customer::where('curp', $request->curp)->first();
        if ($existing && ($existing->user_id || $existing->loans()->whereNotNull('approved_at')->exists())) {
            return JsonResponse::error('Ya existe un registro con esa CURP. Inicia sesión en tu cuenta para solicitar otro préstamo o comunícate con nosotros.', 409);
        }

        $data = $request->only(['email', 'phone', 'name', 'second_name', 'last_name', 'maiden_name', 'gender', 'birthday', 'state', 'rfc', 'curp']);

        $status = Status::where('name', 'PROFILE REGISTERED')->firstOrFail();

        $data['country'] = 'Mexico';
        $data['status_id'] = $status->id;
        $data['privacy_accepted_at'] = now();
        $data['credit_check_authorized_at'] = now();
        $data['consent_version'] = config('promas.consent_version');
        $data['consent_ip'] = $request->ip();
        $data['consent_user_agent'] = mb_substr((string) $request->userAgent(), 0, 255);

        $customer = Customer::updateOrCreate(['curp' => $data['curp']], $data);
        $customer->status = $status;

        // Reutiliza la solicitud pendiente si el cliente regresa al primer paso
        $loan = Loan::where('customer_id', $customer->id)
            ->whereNull('approved_at')
            ->whereNull('reject_at')
            ->latest('id')
            ->first() ?? Loan::create(['customer_id' => $customer->id]);

        $customer->loan = $loan;

        $request->session()->put(EnsureRequestCreditOwner::SESSION_KEY, $customer->id);

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

        $loanId = $this->pendingLoanId($customer);
        $incrementScore->handle($loanId, 'live_time', $request->years_living);
        $incrementScore->handle($loanId, 'home_type', $request->type);

        return JsonResponse::success($customer, $status->name, 201);
    }

    public function updatecustomer(Request $request, Customer $customer, IncrementScore $incrementScore)
    {
        $status = Status::where('name', $request->civil_status ? 'PROFILE COMPLETED' : 'PROFILE 2 REGISTERED')->firstOrFail();

        $customer->update(
            array_merge(
                ['status_id' => $status->id],
                $request->only([
                    'name', 'second_name', 'last_name', 'maiden_name', 'gender', 'birthday', 'state', 'civil_status', 'level_study', 'own_car', 'economic_dependents'
                ]),
                ['country' => 'Mexico']
            )
        );

        $loanId = $this->pendingLoanId($customer);
        if($status->name == 'PROFILE COMPLETED') {
            $incrementScore->handle($loanId, 'civil_status', $request->civil_status);
            $incrementScore->handle($loanId, 'car_status', $request->own_car);
            $incrementScore->handle($loanId, 'economic_dependents', $request->economic_dependents);
        } else {
            $age = Carbon::parse($customer->birthday)->age;

            $incrementScore->handle($loanId, 'age', $age);
        }

        $customer->status = $status;

        return JsonResponse::success($customer, $status->name);
    }

    public function createemployment(Request $request, Customer $customer, IncrementScore $incrementScore)
    {
        $request->validate([
            'type' => 'required|string|max:255',
            'area' => 'required|string|max:255',
            'main_activity' => 'required|string|max:255',
            'seniority_working' => 'required|integer|min:0|max:80',
            'monthly_income' => 'required|numeric|min:0',
            'monthly_expenses' => 'required|numeric|min:0',
            'other_income' => 'nullable|numeric|min:0',
        ], [
            'type.required' => 'Selecciona tu situación laboral.',
            'area.required' => 'Selecciona tu área de trabajo.',
            'main_activity.required' => 'Selecciona tu actividad principal.',
            'seniority_working.required' => 'Captura tu antigüedad laboral en años.',
            'monthly_income.required' => 'Captura tus ingresos mensuales.',
            'monthly_expenses.required' => 'Captura tus egresos mensuales.',
        ]);

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
            'monthly_expenses' => $request->monthly_expenses,
            'other_income' => $request->other_income,
        ]);

        $customer->status_id = $status->id;
        $customer->save();
        $customer->status = $status;

        $loanId = $this->pendingLoanId($customer);
        $incrementScore->handle($loanId, 'employment_situation', $request->type);
        $incrementScore->handle($loanId, 'sector', $request->area);

        return JsonResponse::success($customer, $status->name, 201);
    }

    public function updateemployment(Request $request, Customer $customer, IncrementScore $incrementScore)
    {
        $request->validate([
            'monthly_income' => 'nullable|numeric|min:0',
            'monthly_expenses' => 'nullable|numeric|min:0',
            'other_income' => 'nullable|numeric|min:0',
        ]);

        $status = Status::where('name', 'EMPLOYMENT COMPLETED')->firstOrFail();

        $customer->employment()->update(
            $request->only(['company', 'job', 'seniority_working', 'phone', 'imss', 'insurance_major_medical_expenses', 'monthly_income', 'monthly_expenses', 'other_income'])
        );

        $customer->status_id = $status->id;
        $customer->save();
        $customer->status = $status;

        $loanId = $this->pendingLoanId($customer);
        $incrementScore->handle($loanId, 'seniority', $request->seniority_working);
        $incrementScore->handle($loanId, 'social_security', $request->imss);
        $incrementScore->handle($loanId, 'sgmm', $request->insurance_major_medical_expenses);
        $incrementScore->handle($loanId, 'salary', $request->monthly_income);
        $incrementScore->handle($loanId, 'another_salary', $request->other_income);

        return JsonResponse::success($customer, $status->name);
    }

    public function storeloan(Request $request, Customer $customer, IncrementScore $incrementScore)
    {
        // El producto se define igual que al guardar: con garantía solo si trae folio del plan funerario
        $type = $request->type === 'FUNERARIO' && $request->policy_folio ? 'FUNERARIO' : 'NORMAL';
        $product = LoanProduct::forType($type);

        if (! $product->active) {
            return JsonResponse::error('Este producto no está disponible por el momento.', 422);
        }

        // Mismos límites que el simulador público: monto entre mínimo y máximo, en múltiplos del incremento, y plazo dentro del rango
        $request->validate([
            'amount' => [
                'required', 'numeric', "min:{$product->min_amount}", "max:{$product->max_amount}",
                function ($attribute, $value, $fail) use ($product) {
                    if (fmod($value - $product->min_amount, max($product->amount_step, 1)) !== 0.0) {
                        $fail('El monto debe ser múltiplo de $'.number_format($product->amount_step).'.');
                    }
                },
            ],
            'term' => "required|integer|min:{$product->min_term}|max:{$product->max_term}",
            'purpose' => 'required|string',
            'purpose_description' => 'required|string',
            'policy_folio' => 'required_if:type,FUNERARIO|nullable|string|max:255',
        ], [
            'policy_folio.required_if' => 'El folio del plan funerario es requerido.',
            'amount.min' => 'El monto mínimo es de $'.number_format($product->min_amount).'.',
            'amount.max' => 'El monto máximo es de $'.number_format($product->max_amount).'.',
            'term.min' => "El plazo mínimo es de {$product->min_term} meses.",
            'term.max' => "El plazo máximo es de {$product->max_term} meses.",
        ]);

        $status = Status::where('name', 'LOAN CREATED')->firstOrFail();

        $loan = Loan::where('customer_id', $customer->id)->whereNull('approved_at')->whereNull('reject_at')->latest('id')->first();

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
            'term' => $request->term,
        ]);

        $customer->status_id = $status->id;
        $customer->save();

        $incrementScore->handle($loan->id, 'months', $request->term);
        $incrementScore->handle($loan->id, 'purpose', $request->purpose);

        return JsonResponse::success($loan, $status->name, 201);
    }

    /** Documentos del solicitante (INE por ambos lados, comprobante de domicilio y estado de cuenta). */
    public function documents(Request $request, Customer $customer)
    {
        $rules = [];
        $messages = [];
        foreach (self::REQUIRED_DOCUMENTS as $field => $label) {
            $rules[$field] = 'required|file|mimes:pdf,jpg,jpeg,png|max:5120';
            $messages["{$field}.required"] = "Falta subir: {$label}.";
            $messages["{$field}.mimes"] = "{$label} debe ser PDF, JPG o PNG.";
            $messages["{$field}.max"] = "{$label} pesa más de 5 MB.";
            $messages["{$field}.uploaded"] = "{$label} no se pudo subir; revisa que no pese más de 5 MB.";
        }
        $request->validate($rules, $messages);

        $loan = Loan::where('customer_id', $customer->id)->whereNull('approved_at')->whereNull('reject_at')->latest('id')->first();
        if (! $loan || ! $loan->amount) {
            return JsonResponse::error('Primero captura los datos del préstamo.', 422);
        }

        $disk = Storage::disk(config('filesystems.documents'));
        $saved = [];

        foreach (self::REQUIRED_DOCUMENTS as $field => $label) {
            $file = $request->file($field);
            $previous = $loan->documentations()->where('type', $label)->first();
            if ($previous && $disk->exists($previous->path)) {
                $disk->delete($previous->path);
            }

            $path = $disk->putFileAs("loans/{$loan->id}/uploads", $file, "{$field}.{$file->extension()}");
            $saved[] = $loan->documentations()->updateOrCreate(['type' => $label], ['name' => $label, 'path' => $path]);
        }

        return JsonResponse::success(['documents' => $saved], 'DOCUMENTS UPLOADED', 201);
    }

    private function pendingLoanId(Customer $customer): ?int
    {
        return Loan::where('customer_id', $customer->id)
            ->whereNull('approved_at')
            ->whereNull('reject_at')
            ->latest('id')
            ->value('id');
    }
}
