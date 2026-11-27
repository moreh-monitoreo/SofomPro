<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gestión de cobranza: gestor asignado por préstamo, bitácora de gestiones (llamadas, visitas, promesas de pago)
 * y registro de los recordatorios enviados para no repetirlos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedBigInteger('collector_id')->nullable()->index()->comment('Gestor de cobranza asignado');
        });

        Schema::create('collection_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('channel', 20)->comment('llamada, visita, mensaje, correo');
            $table->string('outcome', 20)->comment('contactado, promesa, no_contesta, ilocalizable, negativa, otro');
            $table->string('notes', 1000)->nullable();
            $table->decimal('promise_amount', 10)->nullable();
            $table->date('promise_date')->nullable();
            $table->string('promise_status', 20)->nullable()->comment('pendiente, cumplida, incumplida');
            $table->timestamp('promise_resolved_at')->nullable();
            $table->timestamps();
            $table->index(['promise_status', 'promise_date']);
        });

        Schema::create('payment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_detail_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->comment('antes, vence');
            $table->timestamp('sent_at');
            $table->unique(['payment_detail_id', 'kind']);
        });

        if (Schema::hasTable('roles') && ! DB::table('roles')->where('name', 'Gestor de cobranza')->where('guard_name', 'web')->exists()) {
            DB::table('roles')->insert(['name' => 'Gestor de cobranza', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reminders');
        Schema::dropIfExists('collection_actions');

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('collector_id');
        });

        if (Schema::hasTable('roles')) {
            DB::table('roles')->where('name', 'Gestor de cobranza')->delete();
        }
    }
};
