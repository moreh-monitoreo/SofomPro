<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('requested_amount', 10)->nullable()->comment('Monto que pidió el cliente, si se aprobó otro');
            $table->unsignedSmallInteger('requested_term')->nullable()->comment('Plazo que pidió el cliente, si se aprobó otro');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('reject_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['requested_amount', 'requested_term', 'approved_by', 'reject_reason']);
        });
    }
};
