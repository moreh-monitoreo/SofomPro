<?php

use App\Models\Status;
use App\Models\User;
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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Status::class);
            $table->string('name');
            $table->string('second_name')->nullable();
            $table->string('last_name');
            $table->string('maiden_name')->nullable();
            $table->string('gender', 10);
            $table->date('birthday')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('rfc')->nullable();
            $table->string('curp')->nullable();
            $table->string('civil_status')->nullable();
            $table->string('level_study')->nullable();
            $table->string('own_car')->nullable();
            $table->integer('economic_dependents')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
