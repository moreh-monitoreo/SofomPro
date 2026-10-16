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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Customer::class)->constrained();
            $table->string('type')->nullable()->default('NORMAL')->description('NORMAL,FUNERARIO');
            $table->string('policy_folio')->nullable();
            $table->string('folio')->nullable();
            $table->decimal('amount', 10)->nullable();
            $table->integer('term')->nullable()->comment('Number of months');
            $table->string('purpose')->nullable();
            $table->text('purpose_description')->nullable();
            $table->decimal('score')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('reject_at')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('paid_at')->nullable()->comment('Paid at date and time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
