<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3, operación del crédito: solicitud enviada, validación (Buró y PLD), desembolso,
 * mora configurable por producto, abonos con prelación, referencias y CLABE del cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->comment('Cuando el cliente envió la solicitud completa (documentos)');
            $table->string('bureau_folio', 60)->nullable()->comment('Folio de la consulta a la Sociedad de Información Crediticia');
            $table->string('bureau_result', 255)->nullable();
            $table->string('pld_result', 30)->nullable()->comment('sin_coincidencias, con_coincidencias');
            $table->string('pld_evidence_path')->nullable()->comment('Comprobante del cotejo contra listas');
            $table->timestamp('validated_at')->nullable()->comment('Cuando el analista terminó la validación');
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->string('disbursement_method', 20)->nullable()->comment('SPEI, CHEQUE, EFECTIVO');
            $table->string('disbursement_reference', 120)->nullable();
            $table->unsignedBigInteger('disbursed_by')->nullable();
        });

        // Las solicitudes que ya avanzaron o ya tienen documentos se consideran enviadas; las demás quedan incompletas
        DB::table('loans')
            ->where(function ($q) {
                $q->whereNotNull('approved_at')->orWhereNotNull('reject_at')->orWhereNotNull('validation_started_at')
                    ->orWhereExists(fn ($d) => $d->select(DB::raw(1))->from('documentations')->whereColumn('documentations.loan_id', 'loans.id'));
            })
            ->update(['submitted_at' => DB::raw('created_at')]);

        Schema::table('loan_products', function (Blueprint $table) {
            $table->decimal('moratory_rate', 6, 2)->default(42)->comment('Tasa anual de interés moratorio, en porcentaje (cláusula octava)');
            $table->unsignedSmallInteger('grace_days')->default((int) config('promas.overdue_grace_days', 0));
            $table->decimal('early_payoff_penalty', 5, 2)->default(3)->comment('Penalización por liquidar antes de los meses forzosos (cláusula novena)');
            $table->unsignedSmallInteger('min_forced_months')->default(0);
            $table->json('required_documents')->nullable()->comment('Tipos de documento obligatorios para aprobar');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('clabe', 18)->nullable()->comment('Cuenta CLABE para depositar el crédito');
        });

        Schema::create('customer_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship', 60);
            $table->string('phone', 10);
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('prepaid_principal', 10)->default(0)->comment('Pagos anticipados aplicados al saldo insoluto');
        });

        Schema::table('payment_details', function (Blueprint $table) {
            $table->decimal('moratory_iva_paid', 10)->default(0);
            $table->decimal('moratory_paid', 10)->default(0);
            $table->decimal('iva_paid', 10)->default(0);
            $table->decimal('interest_paid', 10)->default(0);
            $table->decimal('principal_paid', 10)->default(0);
        });

        // Las cuotas que ya estaban pagadas quedan cubiertas en cada concepto
        DB::table('payment_details')->where('status', 'S')->update([
            'interest_paid' => DB::raw('interest_payment'),
            'principal_paid' => DB::raw('principal_payment'),
            'iva_paid' => DB::raw('monthly_payment - interest_payment - principal_payment'),
        ]);

        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->comment('adelantado, anticipado, liquidacion');
            $table->decimal('amount', 10);
            $table->string('method', 20);
            $table->string('reference', 120)->nullable();
            $table->timestamp('paid_at');
            $table->unsignedBigInteger('registered_by')->nullable();
            $table->string('notes')->nullable();
            $table->json('allocations')->comment('Cómo se aplicó el pago, por cuota y concepto');
            $table->json('snapshot')->comment('Estado de las cuotas antes del pago, para poder revertirlo');
            $table->timestamp('reverted_at')->nullable();
            $table->unsignedBigInteger('reverted_by')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('roles')) {
            foreach (['Analista de crédito', 'Gerente'] as $role) {
                if (! DB::table('roles')->where('name', $role)->where('guard_name', 'web')->exists()) {
                    DB::table('roles')->insert(['name' => $role, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_payments');
        Schema::dropIfExists('customer_references');

        Schema::table('payment_details', function (Blueprint $table) {
            $table->dropColumn(['moratory_iva_paid', 'moratory_paid', 'iva_paid', 'interest_paid', 'principal_paid']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('prepaid_principal');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('clabe');
        });
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn(['moratory_rate', 'grace_days', 'early_payoff_penalty', 'min_forced_months', 'required_documents']);
        });
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'submitted_at', 'bureau_folio', 'bureau_result', 'pld_result', 'pld_evidence_path', 'validated_at', 'validated_by',
                'disbursement_method', 'disbursement_reference', 'disbursed_by',
            ]);
        });

        DB::table('roles')->whereIn('name', ['Analista de crédito', 'Gerente'])->delete();
    }
};
