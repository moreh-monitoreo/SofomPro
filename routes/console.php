<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/*
 * Tareas diarias: recordatorios de pago a clientes (3 días antes y el día del vencimiento), seguimiento de promesas
 * de pago y reporte de cartera vencida al equipo.
 *
 * No hace falta un cron: la primera visita al sitio después de las 09:00 las dispara (RunDailyTasksWhenDue).
 * Si algún día se agrega un cron con `schedule:run` o `schedule:work`, esta entrada también sirve; con --if-due
 * nunca corren dos veces el mismo día, venga de donde venga.
 */
Schedule::command('loans:daily-tasks --if-due')->everyFifteenMinutes()->between('09:00', '23:59')->timezone('America/Mexico_City');
