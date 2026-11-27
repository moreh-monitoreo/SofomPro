<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Política de crédito por producto (capacidad de pago, antigüedades y calificación de Buró) en lugar del score
 * acumulado por respuestas, que no seguía ningún criterio. Un valor en 0 desactiva la regla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->decimal('max_debt_ratio', 5, 2)->default(35)->comment('Cuota máxima como % del ingreso disponible');
            $table->unsignedSmallInteger('min_employment_years')->default(1);
            $table->unsignedSmallInteger('min_residence_years')->default(1);
            $table->unsignedSmallInteger('min_bureau_score')->default(600);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedSmallInteger('bureau_score')->nullable()->comment('Calificación de la consulta a Buró');
            $table->json('policy_result')->nullable()->comment('Evaluación de la política al aprobar');
            $table->string('policy_override_reason', 500)->nullable()->comment('Justificación al aprobar sin cumplir la política');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['bureau_score', 'policy_result', 'policy_override_reason']);
        });

        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn(['max_debt_ratio', 'min_employment_years', 'min_residence_years', 'min_bureau_score']);
        });
    }
};
