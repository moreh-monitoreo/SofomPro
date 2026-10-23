<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('year');
            $table->unsignedBigInteger('registered_by')->nullable()->comment('Usuario que registró un pago manual');
            $table->string('notes')->nullable();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->timestamp('validation_started_at')->nullable()->comment('Cuando el equipo empezó a validar la solicitud');
            $table->timestamp('disbursed_at')->nullable()->comment('Cuando se entregó el dinero al cliente');
        });
    }

    public function down(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->dropColumn(['due_date', 'registered_by', 'notes']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['validation_started_at', 'disbursed_at']);
        });
    }
};
