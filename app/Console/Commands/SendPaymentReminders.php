<?php

namespace App\Console\Commands;

use App\Mail\PaymentReminder;
use App\Models\PaymentDetail;
use App\Services\CollectionService;
use App\Services\LoanLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Corre una vez al día: avisa a cada cliente unos días antes de que venza su cuota y el mismo día del vencimiento
 * (una sola vez por cuota y tipo de aviso), y da por cumplidas o incumplidas las promesas de pago.
 */
class SendPaymentReminders extends Command
{
    protected $signature = 'loans:payment-reminders {--dry-run : Solo muestra a quién se le enviaría}';

    protected $description = 'Envía recordatorios de pago a los clientes y da seguimiento a las promesas de pago';

    public function handle(LoanLedger $ledger, CollectionService $collections): int
    {
        $today = today();
        $daysBefore = max((int) config('promas.reminder_days_before', 3), 1);
        $dry = (bool) $this->option('dry-run');
        $sent = 0;

        foreach (['antes' => $today->copy()->addDays($daysBefore), 'vence' => $today->copy()] as $kind => $date) {
            $details = PaymentDetail::with('payment.loan.customer', 'payment.details')
                ->where('status', '!=', 'S')
                ->whereDate('due_date', $date)
                ->whereDoesntHave('reminders', fn ($q) => $q->where('kind', $kind))
                ->whereHas('payment.loan', fn ($q) => $q->whereNotNull('approved_at')->whereNull('reject_at')->whereNull('paid_at'))
                ->get();

            foreach ($details as $detail) {
                $loan = $detail->payment->loan;
                $email = $loan->customer?->email;
                if (blank($email)) {
                    continue;
                }

                $loan->setRelation('payment', $detail->payment);
                $amount = $ledger->amountToCover($loan, $detail, $date);
                if ($amount <= 0) {
                    continue;
                }

                if ($dry) {
                    $this->line("[{$kind}] {$email}: cuota {$detail->number} por $".number_format($amount, 2));
                    $sent++;

                    continue;
                }

                try {
                    Mail::to($email)->send(new PaymentReminder($detail, $amount, $kind));
                    DB::table('payment_reminders')->insert(['payment_detail_id' => $detail->id, 'kind' => $kind, 'sent_at' => now()]);
                    $sent++;
                } catch (\Throwable $e) {
                    // Un correo que falla no queda registrado como enviado ni detiene a los demás
                    report($e);
                    $this->warn("No se pudo enviar a {$email}: {$e->getMessage()}");
                }
            }
        }

        $promises = $dry ? ['fulfilled' => 0, 'broken' => 0] : $collections->resolvePromises(today: $today);

        $this->info("Recordatorios enviados: {$sent}. Promesas cumplidas: {$promises['fulfilled']}, incumplidas: {$promises['broken']}.");

        return self::SUCCESS;
    }
}
