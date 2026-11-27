<?php

namespace Tests\Feature;

use App\Mail\OverdueReport;
use App\Services\DailyTasks;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsLoans;
use Tests\TestCase;

class DailyTasksTest extends TestCase
{
    use BuildsLoans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['promas.daily_tasks_on_requests' => true, 'promas.notification_email' => 'equipo@example.com']);
        $this->approvedLoan();
    }

    public function test_the_first_visit_after_nine_runs_the_tasks_once(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 08:59'));
        $this->get('/')->assertOk();
        Mail::assertNothingSent();
        $this->assertDatabaseCount('daily_task_runs', 0);

        $this->travelTo(Carbon::parse('2026-10-21 09:05'));
        $this->get('/')->assertOk();
        Mail::assertSent(OverdueReport::class, 1);
        $run = DB::table('daily_task_runs')->first();
        $this->assertSame('done', $run->status);
        $this->assertStringContainsString('Recordatorios', $run->output);

        // Más visitas el mismo día no repiten nada
        $this->get('/')->assertOk();
        $this->travelTo(Carbon::parse('2026-10-21 18:00'));
        $this->get('/')->assertOk();
        Mail::assertSent(OverdueReport::class, 1);

        // Al día siguiente vuelve a correr
        $this->travelTo(Carbon::parse('2026-10-22 10:00'));
        $this->get('/')->assertOk();
        Mail::assertSent(OverdueReport::class, 2);
    }

    public function test_it_is_off_when_disabled(): void
    {
        config(['promas.daily_tasks_on_requests' => false]);
        $this->travelTo(Carbon::parse('2026-10-21 10:00'));

        $this->get('/')->assertOk();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('daily_task_runs', 0);
    }

    public function test_only_one_caller_wins_the_day(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 10:00'));
        $daily = app(DailyTasks::class);

        $this->assertNotNull($daily->claim());
        $this->assertNull($daily->claim());
    }

    public function test_a_failed_run_is_retried_after_a_pause_up_to_three_times(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 10:00'));
        $daily = app(DailyTasks::class);

        $id = $daily->claim();
        $daily->finish($id, false, 'falló el correo');
        $this->assertNull($daily->claim(), 'No reintenta de inmediato');

        $this->travel(11)->minutes();
        $this->assertSame($id, $daily->claim());
        $daily->finish($id, false, 'falló otra vez');

        $this->travel(11)->minutes();
        $this->assertSame($id, $daily->claim());
        $daily->finish($id, false, 'tercera falla');

        $this->travel(11)->minutes();
        $this->assertNull($daily->claim(), 'Se agotaron los intentos');
        $this->assertSame(3, (int) DB::table('daily_task_runs')->value('attempts'));
        $this->assertFalse($daily->shouldTry());
    }

    public function test_a_run_that_died_is_taken_again_after_an_hour(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 10:00'));
        $daily = app(DailyTasks::class);
        $daily->claim();

        $this->travel(30)->minutes();
        $this->assertNull($daily->claim());
        $this->travel(31)->minutes();
        $this->assertNotNull($daily->claim());
    }

    public function test_the_command_with_if_due_runs_once_per_day(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 10:00'));

        $this->artisan('loans:daily-tasks --if-due')->expectsOutputToContain('Tareas diarias terminadas.')->assertSuccessful();
        $this->artisan('loans:daily-tasks --if-due')->expectsOutputToContain('ya se ejecutaron')->assertSuccessful();
        Mail::assertSent(OverdueReport::class, 1);
    }
}
