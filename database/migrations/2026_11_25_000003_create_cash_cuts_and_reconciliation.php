<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corte de caja diario (efectivo contado contra lo registrado) y conciliación de los pagos que llegan al banco
 * (transferencias, depósitos y Stripe) contra el estado de cuenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_cuts', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->json('totals')->comment('Cobrado por forma de pago al cerrar');
            $table->decimal('total', 12)->default(0);
            $table->decimal('cash_expected', 12)->default(0);
            $table->decimal('cash_counted', 12)->default(0);
            $table->decimal('cash_difference', 12)->default(0);
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamps();
        });

        Schema::table('loan_payments', function (Blueprint $table) {
            $table->decimal('processor_fee', 10)->nullable()->comment('Comisión de Stripe (con IVA)');
            $table->decimal('processor_net', 10)->nullable()->comment('Lo que Stripe deposita después de su comisión');
            $table->timestamp('reconciled_at')->nullable();
            $table->unsignedBigInteger('reconciled_by')->nullable();
            $table->string('bank_reference', 120)->nullable()->comment('Movimiento del estado de cuenta o payout de Stripe');
        });
    }

    public function down(): void
    {
        Schema::table('loan_payments', function (Blueprint $table) {
            $table->dropColumn(['processor_fee', 'processor_net', 'reconciled_at', 'reconciled_by', 'bank_reference']);
        });

        Schema::dropIfExists('cash_cuts');
    }
};
