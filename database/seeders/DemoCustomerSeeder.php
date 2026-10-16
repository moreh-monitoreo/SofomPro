<?php

namespace Database\Seeders;

use App\Actions\CreateQuotationTableAction;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Status;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Cliente de prueba con un préstamo aprobado y su tabla de pagos,
 * para poder ver el panel de cliente.
 *
 * php artisan db:seed --class=DemoCustomerSeeder
 */
class DemoCustomerSeeder extends Seeder
{
    public function run(CreateQuotationTableAction $createQuotationTableAction): void
    {
        $user = User::firstOrCreate(
            ['email' => 'cliente.demo@gmail.com'],
            ['name' => 'Juan Pérez', 'password' => bcrypt('password')]
        );
        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
        $user->syncRoles(['Customer']);

        $customer = Customer::firstOrNew(['curp' => 'PEGJ900515HDFRRN09']);
        $customer->fill([
            'status_id' => Status::where('name', 'LOAN CREATED')->value('id'),
            'name' => 'Juan',
            'last_name' => 'Pérez',
            'maiden_name' => 'García',
            'gender' => 'M',
            'birthday' => '1990-05-15',
            'state' => 'Ciudad de México',
            'country' => 'Mexico',
            'rfc' => 'PEGJ900515AB1',
            'civil_status' => 'SOLTERO',
            'level_study' => 'LICENCIATURA',
            'own_car' => 'NO',
            'economic_dependents' => 0,
        ]);
        $customer->user_id = $user->id;
        $customer->save();

        if ($customer->loans()->exists()) {
            return;
        }

        $amount = 30000;
        $years = 1;
        $openingFees = 3;
        $annualRate = 45;
        $startAt = Carbon::now()->subMonths(3)->startOfMonth();

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'type' => 'NORMAL',
            'amount' => $amount,
            'term' => $years * 12,
            'purpose' => 'NEGOCIO',
            'purpose_description' => 'Compra de mercancía para negocio propio',
            'approved_at' => $startAt->copy()->subDays(5),
            'start_at' => $startAt,
        ]);

        $table = $createQuotationTableAction->handle($amount, $years, $openingFees, $annualRate);

        $payment = Payment::create([
            'loan_id' => $loan->id,
            'request_amount' => $amount,
            'payment_amount' => $table['to_pay_simple_interest'],
            'cat_type' => $table['type'],
            'cat_amount' => $table['CAT_simple_interest'],
            'total_months' => $table['total_months'],
            'month_rate' => $table['month_rate'],
            'opening_fees' => $table['opening_fees'],
            'status' => 'N',
        ]);

        $date = $startAt->copy();
        foreach ($table['payments'] as $index => $pay) {
            $date->addMonth();
            // Los primeros dos meses ya pagados
            $paid = $index < 2;

            PaymentDetail::create([
                'payment_id' => $payment->id,
                'number' => $index + 1,
                'month' => $date->format('m'),
                'year' => $date->year,
                'interest_payment' => $pay['interest_payment'],
                'principal_payment' => $pay['principal_payment'],
                'balance' => $pay['balance'],
                'monthly_payment' => $pay['monthly_payment'],
                'status' => $paid ? 'S' : 'N',
                'payed_at' => $paid ? $date->copy() : null,
                'payment_method' => $paid ? 'STRIPE' : null,
                'payment_total' => $paid ? $pay['monthly_payment'] : null,
            ]);
        }
    }
}
