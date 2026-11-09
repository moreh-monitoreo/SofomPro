<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una cuenta desactivada no puede iniciar sesión; se conserva para la bitácora
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable();
        });

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('municipality')->nullable()->after('suburb');
            $table->string('state')->nullable()->after('municipality');
        });

        // La actividad principal pasa a ser el giro; los sectores vulnerables de PLD se preguntan aparte
        Schema::table('employments', function (Blueprint $table) {
            $table->string('pld_activity')->nullable()->after('main_activity');
        });

        // Catálogo de SEPOMEX: un renglón por asentamiento (colonia) de cada código postal
        Schema::create('postal_codes', function (Blueprint $table) {
            $table->id();
            $table->char('code', 5)->index();
            $table->string('settlement');
            $table->string('settlement_type')->nullable();
            $table->string('municipality');
            $table->string('state');
            $table->string('city')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postal_codes');

        Schema::table('employments', function (Blueprint $table) {
            $table->dropColumn('pld_activity');
        });

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropColumn(['municipality', 'state']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deactivated_at');
        });
    }
};
