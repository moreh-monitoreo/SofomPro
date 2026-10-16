<?php

use App\Models\Loan;
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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Loan::class)->constrained();
            $table->decimal('request_amount', 10);
            $table->decimal('payment_amount', 10);
            $table->string('cat_type', 10);
            $table->decimal('cat_amount');
            $table->integer('total_months');
            $table->decimal('month_rate');
            $table->decimal('opening_fees');
            $table->char('status')->default('N'); // P = Pagado, N = No pagado
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
