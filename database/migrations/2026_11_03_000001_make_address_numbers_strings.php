<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** El número exterior puede llevar letras o guiones (12-A, 4B, S/N). */
    public function up(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('outdoor_number', 20)->change();
            $table->string('interior_number', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->integer('outdoor_number')->change();
        });
    }
};
