<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Aviso diario al equipo con la cartera vencida (requiere `php artisan schedule:run` cada minuto en el servidor)
Schedule::command('loans:overdue-report')->dailyAt('08:00')->timezone('America/Mazatlan');
