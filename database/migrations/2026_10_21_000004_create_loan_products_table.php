<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->unique()->comment('NORMAL, FUNERARIO');
            $table->string('name');
            $table->decimal('annual_rate', 6, 2)->comment('Tasa ordinaria anual fija, en porcentaje');
            $table->decimal('opening_fee', 5, 2)->default(0)->comment('Comisión por apertura, en porcentaje del monto');
            $table->decimal('iva', 5, 2)->default(16)->comment('IVA sobre intereses y comisiones, en porcentaje');
            $table->unsignedInteger('min_amount');
            $table->unsignedInteger('max_amount');
            $table->unsignedInteger('amount_step');
            $table->unsignedSmallInteger('min_term');
            $table->unsignedSmallInteger('max_term');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('loan_products')->insert([
            [
                'type' => 'NORMAL', 'name' => 'Préstamo personal', 'annual_rate' => 79.56, 'opening_fee' => 3, 'iva' => 16,
                'min_amount' => 2000, 'max_amount' => 10000, 'amount_step' => 1000, 'min_term' => 6, 'max_term' => 12,
                'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'type' => 'FUNERARIO', 'name' => 'Préstamo con garantía (plan funerario)', 'annual_rate' => 74.28, 'opening_fee' => 3, 'iva' => 16,
                'min_amount' => 2000, 'max_amount' => 10000, 'amount_step' => 1000, 'min_term' => 6, 'max_term' => 12,
                'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
