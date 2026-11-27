<?php

namespace Tests\Feature;

use App\Mail\BrokenPromise;
use App\Mail\PaymentReminder;
use App\Models\AuditLog;
use App\Models\CollectionAction;
use App\Services\PaymentRegistrar;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsLoans;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use BuildsLoans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Cuotas del préstamo de prueba: 5/sep (vencida), 5/oct (vencida) y 5/nov
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00'));
    }

    private function promise(array $override = []): array
    {
        return array_merge(['channel' => 'llamada', 'outcome' => 'promesa', 'promise_amount' => 1000, 'promise_date' => '2026-10-25', 'notes' => 'Paga el viernes'], $override);
    }

    public function test_a_collector_records_actions_only_on_assigned_loans(): void
    {
        ['loan' => $loan] = $this->approvedLoan();
        $collector = $this->userWithRole('Gestor de cobranza');

        $this->actingAs($collector)->postJson("/loans/{$loan->id}/collection", $this->promise())->assertForbidden();

        $loan->forceFill(['collector_id' => $collector->id])->save();
        $this->actingAs($collector)->postJson("/loans/{$loan->id}/collection", $this->promise())
            ->assertOk()->assertJsonPath('data.promise_status', 'pendiente');

        $action = CollectionAction::firstOrFail();
        $this->assertSame($collector->id, $action->user_id);
        $this->assertSame('2026-10-25', $action->promise_date->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'collection.recorded', 'loan_id' => $loan->id, 'user_id' => $collector->id]);

        // Un asesor puede registrar en cualquier préstamo
        $this->actingAs($this->userWithRole('Advisor'))
            ->postJson("/loans/{$loan->id}/collection", ['channel' => 'visita', 'outcome' => 'no_contesta'])->assertOk();
    }

    public function test_a_promise_needs_amount_and_a_near_future_date(): void
    {
        ['loan' => $loan] = $this->approvedLoan();
        $advisor = $this->userWithRole('Advisor');
        $url = "/loans/{$loan->id}/collection";

        $this->actingAs($advisor)->postJson($url, $this->promise(['promise_amount' => null]))->assertStatus(422)->assertJsonValidationErrors('promise_amount');
        $this->actingAs($advisor)->postJson($url, $this->promise(['promise_date' => '2026-10-20']))->assertStatus(422)->assertJsonValidationErrors('promise_date');
        $this->actingAs($advisor)->postJson($url, $this->promise(['promise_date' => '2027-01-30']))->assertStatus(422)->assertJsonValidationErrors('promise_date');
        $this->actingAs($advisor)->postJson($url, ['channel' => 'fax', 'outcome' => 'contactado'])->assertStatus(422)->assertJsonValidationErrors('channel');
    }

    public function test_a_promise_is_fulfilled_when_the_payment_arrives(): void
    {
        ['loan' => $loan] = $this->approvedLoan();
        $this->actingAs($this->userWithRole('Advisor'))->postJson("/loans/{$loan->id}/collection", $this->promise())->assertOk();

        $registrar = app(PaymentRegistrar::class);
        $registrar->apply($loan, 600, 'EFECTIVO');
        $this->assertSame('pendiente', CollectionAction::first()->promise_status);

        $registrar->apply($loan, 400, 'EFECTIVO');
        $this->assertSame('cumplida', CollectionAction::first()->promise_status);
    }

    public function test_a_broken_promise_alerts_the_collector(): void
    {
        Mail::fake();
        ['loan' => $loan] = $this->approvedLoan();
        $collector = $this->userWithRole('Gestor de cobranza');
        $loan->forceFill(['collector_id' => $collector->id])->save();
        $this->actingAs($collector)->postJson("/loans/{$loan->id}/collection", $this->promise())->assertOk();
        app(PaymentRegistrar::class)->apply($loan, 300, 'EFECTIVO');

        // El día de la promesa aún no se incumple; al día siguiente sí
        $this->travelTo(Carbon::parse('2026-10-25 09:00'));
        $this->artisan('loans:payment-reminders')->assertSuccessful();
        $this->assertSame('pendiente', CollectionAction::first()->promise_status);

        $this->travelTo(Carbon::parse('2026-10-26 09:00'));
        $this->artisan('loans:payment-reminders')->assertSuccessful();
        $this->assertSame('incumplida', CollectionAction::first()->promise_status);
        Mail::assertSent(BrokenPromise::class, fn ($mail) => $mail->hasTo($collector->email) && $mail->paid === 300.0);
        $this->assertSame(1, AuditLog::where('action', 'collection.promise_broken')->count());
    }

    public function test_reminders_go_out_before_and_on_the_due_date_once_per_installment(): void
    {
        Mail::fake();
        ['loan' => $loan, 'owner' => $owner, 'details' => $details] = $this->approvedLoan();
        // Al corriente: solo queda la cuota del 5 de noviembre
        $details[0]->update(['status' => 'S']);
        $details[1]->update(['status' => 'S']);

        $this->travelTo(Carbon::parse('2026-11-01 09:00'));
        $this->artisan('loans:payment-reminders')->assertSuccessful();
        Mail::assertNothingSent();

        $this->travelTo(Carbon::parse('2026-11-02 09:00'));
        $this->artisan('loans:payment-reminders')->assertSuccessful();
        $this->artisan('loans:payment-reminders')->assertSuccessful();
        Mail::assertSent(PaymentReminder::class, 1);
        Mail::assertSent(PaymentReminder::class, fn ($mail) => $mail->hasTo($owner->email) && $mail->kind === 'antes' && $mail->amount === 1160.0);

        $this->travelTo(Carbon::parse('2026-11-05 09:00'));
        $this->artisan('loans:payment-reminders')->assertSuccessful();
        Mail::assertSent(PaymentReminder::class, fn ($mail) => $mail->kind === 'vence');
        Mail::assertSent(PaymentReminder::class, 2);

        // Un préstamo ya liquidado no recibe recordatorios
        $loan->forceFill(['paid_at' => now()])->save();
        $this->assertDatabaseCount('payment_reminders', 2);
    }

    public function test_the_daily_tasks_command_runs_reminders_and_the_overdue_report(): void
    {
        Mail::fake();
        config(['promas.notification_email' => 'equipo@example.com']);
        $this->approvedLoan();

        $this->artisan('loans:daily-tasks')
            ->expectsOutputToContain('Recordatorios de pago y promesas')
            ->expectsOutputToContain('Tareas diarias terminadas.')
            ->assertSuccessful();

        Mail::assertSent(\App\Mail\OverdueReport::class, fn ($mail) => $mail->hasTo('equipo@example.com'));
    }

    public function test_the_daily_tasks_command_reports_a_failed_task(): void
    {
        Mail::fake();
        config(['promas.notification_email' => null, 'promas.email' => null]);
        $this->approvedLoan();

        // Sin destinatario el reporte al equipo falla, pero los recordatorios sí corren
        $this->artisan('loans:daily-tasks')
            ->expectsOutputToContain('Fallaron: loans:overdue-report')
            ->assertFailed();
    }

    public function test_managers_assign_collectors_and_collectors_see_only_their_portfolio(): void
    {
        ['loan' => $mine] = $this->approvedLoan();
        ['loan' => $other] = $this->approvedLoan();
        $collector = $this->userWithRole('Gestor de cobranza');
        $manager = $this->userWithRole('Gerente');

        $this->actingAs($collector)->postJson('/portfolio/overdue/assign', ['loan_ids' => [$mine->id], 'collector_id' => $collector->id])->assertForbidden();
        $this->actingAs($manager)->postJson('/portfolio/overdue/assign', ['loan_ids' => [$mine->id], 'collector_id' => $this->userWithRole('Customer')->id])->assertStatus(422);
        $this->actingAs($manager)->postJson('/portfolio/overdue/assign', ['loan_ids' => [$mine->id], 'collector_id' => $collector->id])->assertOk();

        $this->assertSame($collector->id, $mine->fresh()->collector_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'collection.assigned', 'loan_id' => $mine->id]);

        $this->actingAs($collector)->get('/portfolio/overdue')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('rows', 1)->where('rows.0.loan_id', $mine->id)->where('onlyMine', true)->where('rows.0.collector.id', $collector->id));
        $this->actingAs($manager)->get('/portfolio/overdue')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('rows', 2)->where('canAssign', true));
        $this->actingAs($manager)->get('/portfolio/overdue?mine=1')
            ->assertInertia(fn ($page) => $page->has('rows', 0));
        $this->assertNotNull($other);
    }
}
