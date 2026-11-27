<?php

namespace Tests\Concerns;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\StatusesSeeder;
use Spatie\Permission\Models\Role;

trait BuildsLoans
{
    protected function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    protected function customerStatus(string $name): Status
    {
        if (! Status::exists()) {
            $this->seed(StatusesSeeder::class);
        }

        return Status::where('name', $name)->firstOrFail();
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($this->role($role));

        return $user;
    }

    /** Deja una solicitud lista para aprobar: enviada, con expediente completo y validada (Buró y PLD) por un analista. */
    protected function readyForApproval(Loan $loan, ?User $analyst = null): Loan
    {
        $analyst ??= $this->userWithRole('Analista de crédito');

        foreach (\App\Models\LoanProduct::DEFAULT_DOCUMENTS as $type) {
            $path = "loans/{$loan->id}/uploads/".md5($type).'.pdf';
            \Illuminate\Support\Facades\Storage::disk(config('filesystems.documents'))->put($path, '%PDF-1.4');
            $loan->documentations()->firstOrCreate(['type' => $type], ['name' => $type, 'path' => $path]);
        }

        $loan->forceFill([
            'submitted_at' => $loan->submitted_at ?? now(),
            'validation_started_at' => now(),
            'validated_at' => now(),
            'validated_by' => $analyst->id,
            'bureau_folio' => 'BC-0001',
            'bureau_result' => 'Sin atrasos',
            'bureau_score' => 700,
            'pld_result' => 'sin_coincidencias',
        ])->save();

        $this->meetsCreditPolicy($loan->customer);

        return $loan;
    }

    /** Empleo y domicilio que cumplen la política por defecto: ingreso holgado y 3 años en cada uno. */
    protected function meetsCreditPolicy(Customer $customer, array $employment = [], array $address = []): void
    {
        if (! $customer->employment()->exists()) {
            $customer->employment()->create($employment + [
                'type' => 'Empleado', 'area' => 'Servicios', 'main_activity' => 'Comercio',
                'seniority_working' => 3, 'monthly_income' => 20000, 'other_income' => 0, 'monthly_expenses' => 8000,
            ]);
        }
        if (! $customer->addresses()->exists()) {
            $customer->addresses()->create($address + [
                'street' => 'Insurgentes', 'outdoor_number' => '10', 'postal_code' => '80000', 'years_living' => 3, 'type' => 'Propiedad',
            ]);
        }
    }

    /** Préstamo aprobado con plan de 3 cuotas de $1,160.00 y su cliente con cuenta de acceso. */
    protected function approvedLoan(?User $owner = null): array
    {
        $owner ??= $this->userWithRole('Customer');

        $customer = Customer::create([
            'user_id' => $owner->id,
            'status_id' => $this->customerStatus('LOAN CREATED')->id,
            'name' => 'Ana',
            'gender' => 'Femenino',
            'last_name' => 'Pérez',
            'maiden_name' => 'López',
            'email' => $owner->email,
            'curp' => 'PELA900101MSLRPN04',
            'rfc' => 'PELA900101ABA',
        ]);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'type' => 'NORMAL',
            'amount' => 3000,
            'term' => 3,
            'approved_at' => now()->subMonth(),
            'start_at' => now()->subMonth()->startOfMonth(),
        ]);

        $payment = Payment::create([
            'loan_id' => $loan->id,
            'request_amount' => 3000,
            'payment_amount' => 3480,
            'cat_type' => 'simple',
            'cat_amount' => 1.5,
            'total_months' => 3,
            'month_rate' => 0.0663,
            'opening_fees' => 3,
            'status' => 'N',
        ]);

        // Por defecto la 1.ª y la 2.ª cuota ya vencieron y la 3.ª vence el mes siguiente (todas el día 5)
        $details = collect(range(1, 3))->map(function ($n) use ($payment) {
            $due = now()->startOfMonth()->subMonth()->addMonths($n - 1)->day(5);

            return PaymentDetail::create([
            'payment_id' => $payment->id,
            'number' => $n,
            'month' => $due->format('m'),
            'year' => $due->year,
            'due_date' => $due->toDateString(),
            'interest_payment' => 160,
            'principal_payment' => 1000,
            'balance' => 3000 - 1000 * $n,
            'monthly_payment' => 1160,
            'status' => 'N',
            ]);
        });

        return compact('owner', 'customer', 'loan', 'payment', 'details');
    }
}
