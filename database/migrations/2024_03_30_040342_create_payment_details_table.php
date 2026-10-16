<?php

use App\Models\Payment;
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
        Schema::create('payment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Payment::class)->constrained();
            $table->integer('number');
            $table->smallInteger('month');
            $table->integer('year');
            $table->decimal('interest_payment', 10);
            $table->decimal('principal_payment', 10);
            $table->decimal('balance', 10);
            $table->decimal('monthly_payment', 10);
            $table->char('status')->default('N'); // P = Pagado, N = No pagado
            $table->timestamp('payed_at')->nullable()->default(null);
            $table->string('payment_method')->nullable()->default(null);
            $table->string('payment_reference')->nullable()->default(null);
            $table->decimal('payment_total', 10)->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_details');
    }
};
