<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Control de las tareas diarias (recordatorios, promesas y reporte de vencidos) sin depender de un cron del servidor:
 * la primera visita después de la hora configurada las dispara, y la tabla daily_task_runs garantiza que corran
 * una sola vez por día. Si fallan se reintentan hasta MAX_ATTEMPTS veces, con RETRY_MINUTES entre intentos.
 */
class DailyTasks
{
    public const MAX_ATTEMPTS = 3;

    public const RETRY_MINUTES = 10;

    /** Revisión barata para cada visita: ¿ya es hora y no se ha terminado hoy? */
    public function shouldTry(?Carbon $now = null): bool
    {
        $now ??= now();
        if (! config('promas.daily_tasks_on_requests') || $now->lt($this->startsAt($now))) {
            return false;
        }

        return ! Cache::has($this->doneKey($now));
    }

    /**
     * Aparta la ejecución de hoy. Devuelve el id del registro si a esta llamada le toca correr las tareas,
     * o null si ya corrieron, están corriendo o se agotaron los intentos.
     */
    public function claim(?Carbon $now = null): ?int
    {
        $now ??= now();
        if (! Schema::hasTable('daily_task_runs')) {
            return null;
        }
        $date = $now->toDateString();

        try {
            return DB::table('daily_task_runs')->insertGetId([
                'run_date' => $date, 'status' => 'running', 'attempts' => 1, 'started_at' => $now,
            ]);
        } catch (QueryException) {
            // Ya existe el registro del día: solo se reintenta si falló, quedan intentos y pasó el tiempo de espera
        }

        $run = DB::table('daily_task_runs')->where('run_date', $date)->first();
        if (! $run) {
            return null;
        }

        $retryable = $run->status === 'failed' && $run->attempts < self::MAX_ATTEMPTS
            && Carbon::parse($run->started_at)->lte($now->copy()->subMinutes(self::RETRY_MINUTES));
        // Una ejecución que se quedó colgada (el proceso murió) se considera fallida después de una hora
        $stuck = $run->status === 'running' && Carbon::parse($run->started_at)->lte($now->copy()->subHour());

        if (! $retryable && ! $stuck) {
            if ($run->status === 'done' || $run->attempts >= self::MAX_ATTEMPTS) {
                $this->rememberDone($now);
            }

            return null;
        }

        // Actualización condicionada: si dos visitas llegan juntas, solo una la gana
        $won = DB::table('daily_task_runs')
            ->where('id', $run->id)->where('status', $run->status)->where('attempts', $run->attempts)
            ->update(['status' => 'running', 'attempts' => $run->attempts + 1, 'started_at' => $now, 'finished_at' => null]);

        return $won ? $run->id : null;
    }

    public function finish(int $runId, bool $ok, string $output): void
    {
        DB::table('daily_task_runs')->where('id', $runId)->update([
            'status' => $ok ? 'done' : 'failed',
            'finished_at' => now(),
            'output' => mb_substr($output, 0, 5000),
        ]);

        if ($ok) {
            $this->rememberDone(now());
        }
    }

    public function startsAt(Carbon $now): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', (string) config('promas.daily_tasks_at', '09:00')) + [1 => 0]);

        return $now->copy()->setTime($hour, $minute);
    }

    private function rememberDone(Carbon $now): void
    {
        Cache::put($this->doneKey($now), true, $now->copy()->endOfDay());
    }

    private function doneKey(Carbon $now): string
    {
        return 'daily-tasks-done:'.$now->toDateString();
    }
}
