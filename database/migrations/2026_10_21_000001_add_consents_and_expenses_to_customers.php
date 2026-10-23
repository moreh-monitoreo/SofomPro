<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('privacy_accepted_at')->nullable();
            $table->timestamp('credit_check_authorized_at')->nullable();
            $table->string('consent_version', 20)->nullable();
            $table->string('consent_ip', 45)->nullable();
            $table->string('consent_user_agent')->nullable();
        });

        Schema::table('employments', function (Blueprint $table) {
            $table->decimal('monthly_expenses', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['privacy_accepted_at', 'credit_check_authorized_at', 'consent_version', 'consent_ip', 'consent_user_agent']);
        });

        Schema::table('employments', function (Blueprint $table) {
            $table->dropColumn('monthly_expenses');
        });
    }
};
