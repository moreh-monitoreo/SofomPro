<?php

namespace App\Console\Commands;

use App\Http\Controllers\OverdueController;
use App\Mail\OverdueReport;
use App\Services\LoanPortfolio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendOverdueReport extends Command
{
    protected $signature = 'loans:overdue-report {--to= : Correo destino; por defecto el del equipo}';

    protected $description = 'Envía al equipo el resumen de clientes con cuotas vencidas';

    public function handle(LoanPortfolio $portfolio): int
    {
        $rows = app(OverdueController::class)->rows($portfolio);

        if ($rows->isEmpty()) {
            $this->info('No hay cartera vencida; no se envía correo.');

            return self::SUCCESS;
        }

        $to = $this->option('to') ?: config('promas.notification_email');
        if (blank($to)) {
            $this->error('No hay destinatario: configura CONTACT_NOTIFICATION_EMAIL o usa --to.');

            return self::FAILURE;
        }

        Mail::to($to)->send(new OverdueReport($rows));
        $this->info("Reporte enviado a {$to}: {$rows->count()} cliente(s) con atraso.");

        return self::SUCCESS;
    }
}
