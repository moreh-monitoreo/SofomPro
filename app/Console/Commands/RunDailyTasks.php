<?php

namespace App\Console\Commands;

use App\Services\DailyTasks;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Tareas diarias en un solo comando. Normalmente lo dispara la primera visita al sitio después de las 09:00
 * (ver {@see \App\Http\Middleware\RunDailyTasksWhenDue}) con --if-due, que garantiza una sola ejecución por día.
 * Sin --if-due corre siempre (para lanzarlo a mano). Si una tarea falla, las demás se ejecutan igual.
 */
class RunDailyTasks extends Command
{
    protected $signature = 'loans:daily-tasks {--if-due : Solo corre si hoy aún no se ha ejecutado}';

    protected $description = 'Recordatorios de pago, seguimiento de promesas y reporte de cartera vencida';

    private const TASKS = [
        'loans:payment-reminders' => 'Recordatorios de pago y promesas',
        'loans:overdue-report' => 'Reporte de cartera vencida al equipo',
    ];

    public function handle(DailyTasks $daily): int
    {
        $runId = null;
        if ($this->option('if-due')) {
            $runId = $daily->claim();
            if (! $runId) {
                $this->info('Las tareas de hoy ya se ejecutaron o se están ejecutando.');

                return self::SUCCESS;
            }
        }

        $failed = [];
        $log = new BufferedOutput;

        foreach (self::TASKS as $command => $label) {
            $this->info("== {$label} ({$command})");
            $log->writeln("== {$label}");
            try {
                $buffer = new BufferedOutput;
                $code = Artisan::call($command, [], $buffer);
                $text = $buffer->fetch();
                $this->output->write($text);
                $log->write($text);
            } catch (\Throwable $e) {
                report($e);
                $this->error($e->getMessage());
                $log->writeln('Error: '.$e->getMessage());
                $code = self::FAILURE;
            }
            if ($code !== self::SUCCESS) {
                $failed[] = $command;
            }
        }

        if ($runId) {
            $daily->finish($runId, ! $failed, $log->fetch());
        }

        if ($failed) {
            $this->error('Fallaron: '.implode(', ', $failed));

            return self::FAILURE;
        }

        $this->info('Tareas diarias terminadas.');

        return self::SUCCESS;
    }
}
