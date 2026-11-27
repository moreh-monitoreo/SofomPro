<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Evidencia del desembolso: monto neto entregado, CLABE destino (SPEI) y comprobante adjunto. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('disbursed_amount', 10)->nullable()->comment('Monto neto entregado: monto menos comisión por apertura e IVA');
            $table->string('disbursement_clabe', 18)->nullable()->comment('CLABE destino cuando el desembolso es por SPEI');
            $table->string('disbursement_receipt_path')->nullable()->comment('Comprobante del desembolso');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['disbursed_amount', 'disbursement_clabe', 'disbursement_receipt_path']);
        });
    }
};
