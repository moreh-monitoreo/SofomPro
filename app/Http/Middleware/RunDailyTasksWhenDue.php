<?php

namespace App\Http\Middleware;

use App\Services\DailyTasks;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Cron" dentro de la aplicación: la primera visita después de la hora configurada (09:00) lanza las tareas del día
 * cuando la respuesta ya se envió, así nadie espera. {@see DailyTasks} asegura que corran una sola vez por día.
 */
class RunDailyTasksWhenDue
{
    public function __construct(private DailyTasks $daily)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if ($this->daily->shouldTry()) {
                Artisan::call('loans:daily-tasks', ['--if-due' => true]);
            }
        } catch (\Throwable $e) {
            // Nunca debe afectar a la visita; el error queda en el log
            report($e);
        }
    }
}
