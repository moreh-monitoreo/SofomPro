<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Registro de las tareas diarias: el índice único por día evita que corran dos veces aunque lleguen varias visitas a la vez. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_task_runs', function (Blueprint $table) {
            $table->id();
            $table->date('run_date')->unique();
            $table->string('status', 20)->comment('running, done, failed');
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->text('output')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_task_runs');
    }
};
