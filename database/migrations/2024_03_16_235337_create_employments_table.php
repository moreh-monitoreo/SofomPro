<?php

use App\Models\Customer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Customer::class)->constrained();
            $table->string('type')->description('Empleado, Freelance, Negocio, Jubilado');
            $table->string('area')->description('Servicios, Industrial, Comercio, Agropecuario, Gobierno');
            $table->string('main_activity');
            $table->string('company')->nullable();
            $table->string('job')->nullable();
            $table->integer('seniority_working')->nullable();
            $table->string('phone')->nullable();
            $table->string('imss', 10)->nullable();
            $table->string('insurance_major_medical_expenses', 10)->nullable();
            $table->integer('monthly_income')->nullable();
            $table->integer('other_income')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employments');
    }
};
