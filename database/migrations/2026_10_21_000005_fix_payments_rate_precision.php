<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * month_rate era decimal(8,2): una tasa mensual de 0.0663 se guardaba como 0.07 y el contrato
     * mostraba otra tasa anual. cat_amount necesita decimales para guardar el CAT con precisión.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('month_rate', 8, 6)->change();
            $table->decimal('cat_amount', 8, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('month_rate', 8, 2)->change();
            $table->decimal('cat_amount', 8, 2)->change();
        });
    }
};
