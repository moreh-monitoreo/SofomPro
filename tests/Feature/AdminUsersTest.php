<?php

namespace Tests\Feature;

use App\Mail\StaffInvitation;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsLoans;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use BuildsLoans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        foreach (User::STAFF_ROLES as $role) {
            $this->role($role);
        }
    }

    public function test_only_administrators_manage_users(): void
    {
        foreach (['Customer', 'Advisor', 'Gerente', 'Analista de crédito'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/admin/users')->assertForbidden();
        }

        $this->actingAs($this->userWithRole('Administrator'))->get('/admin/users')->assertOk();
    }

    public function test_the_list_shows_staff_but_not_customers(): void
    {
        $admin = $this->userWithRole('Administrator');
        $analyst = $this->userWithRole('Analista de crédito');
        $customer = $this->userWithRole('Customer');

        $this->actingAs($admin)->get('/admin/users')->assertInertia(fn ($page) => $page
            ->component('Admin/Users')
            ->has('users', 2)
            ->where('users.0.role', fn ($role) => in_array($role, ['Administrator', 'Analista de crédito'], true))
        );

        $emails = collect($this->actingAs($admin)->get('/admin/users')->viewData('page')['props']['users'])->pluck('email');
        $this->assertTrue($emails->contains($analyst->email));
        $this->assertFalse($emails->contains($customer->email));
    }

    public function test_an_administrator_creates_a_user_and_sends_the_invitation(): void
    {
        $admin = $this->userWithRole('Administrator');

        $this->actingAs($admin)->post('/admin/users', ['name' => 'Laura Gerente', 'email' => 'Laura@Promas.mx', 'role' => 'Gerente'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $user = User::where('email', 'laura@promas.mx')->firstOrFail();
        $this->assertTrue($user->hasRole('Gerente'));
        $this->assertTrue($user->isActive());
        Mail::assertSent(StaffInvitation::class, fn ($mail) => $mail->hasTo('laura@promas.mx') && $mail->role === 'Gerente');
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'laura@promas.mx']);
        $this->assertTrue(AuditLog::where('action', 'user.created')->where('subject_id', $user->id)->exists());
    }

    public function test_a_mail_failure_keeps_the_user_and_shows_a_message_instead_of_an_error_page(): void
    {
        $admin = $this->userWithRole('Administrator');
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Connection to smtp.titan.email:587 timed out'));

        $this->actingAs($admin)->post('/admin/users', ['name' => 'Laura', 'email' => 'laura@promas.mx', 'role' => 'Gerente'])
            ->assertRedirect()->assertSessionHasErrors('invitation');

        $user = User::where('email', 'laura@promas.mx')->firstOrFail();
        $this->assertTrue($user->hasRole('Gerente'));
        $this->assertFalse(AuditLog::where('action', 'user.invited')->exists());

        $this->actingAs($admin)->post("/admin/users/{$user->id}/invitation")
            ->assertRedirect()->assertSessionHasErrors('user');
    }

    public function test_the_email_must_be_new_and_the_role_must_be_a_staff_role(): void
    {
        $admin = $this->userWithRole('Administrator');
        $customer = $this->userWithRole('Customer');

        $this->actingAs($admin)->post('/admin/users', ['name' => 'X', 'email' => $customer->email, 'role' => 'Advisor'])
            ->assertSessionHasErrors('email');
        $this->actingAs($admin)->post('/admin/users', ['name' => 'X', 'email' => 'x@promas.mx', 'role' => 'Customer'])
            ->assertSessionHasErrors('role');

        Mail::assertNothingSent();
    }

    public function test_the_role_can_be_changed_and_is_audited(): void
    {
        $admin = $this->userWithRole('Administrator');
        $advisor = $this->userWithRole('Advisor');

        $this->actingAs($admin)->put("/admin/users/{$advisor->id}", ['name' => 'Nuevo nombre', 'role' => 'Analista de crédito'])
            ->assertSessionHasNoErrors();

        $advisor->refresh();
        $this->assertSame('Nuevo nombre', $advisor->name);
        $this->assertTrue($advisor->hasRole('Analista de crédito'));
        $this->assertFalse($advisor->hasRole('Advisor'));

        $log = AuditLog::where('action', 'user.updated')->latest('id')->first();
        $this->assertSame('Advisor', $log->before['role']);
        $this->assertSame('Analista de crédito', $log->after['role']);
    }

    public function test_an_administrator_cannot_demote_or_deactivate_themselves(): void
    {
        $admin = $this->userWithRole('Administrator');
        $this->userWithRole('Administrator');

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", ['name' => $admin->name, 'role' => 'Advisor'])
            ->assertSessionHasErrors('role');
        $this->actingAs($admin)->post("/admin/users/{$admin->id}/deactivate")->assertSessionHasErrors('user');

        $this->assertTrue($admin->fresh()->hasRole('Administrator'));
        $this->assertTrue($admin->fresh()->isActive());
    }

    public function test_another_administrator_can_be_demoted_or_deactivated_while_one_remains(): void
    {
        $admin = $this->userWithRole('Administrator');
        $other = $this->userWithRole('Administrator');
        $third = $this->userWithRole('Administrator');

        $this->actingAs($admin)->put("/admin/users/{$other->id}", ['name' => $other->name, 'role' => 'Gerente'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/admin/users/{$third->id}/deactivate")->assertSessionHasNoErrors();

        $this->assertTrue($other->fresh()->hasRole('Gerente'));
        $this->assertFalse($third->fresh()->isActive());
        // Queda un solo administrador activo y no se puede quitar a sí mismo
        $this->actingAs($admin)->put("/admin/users/{$admin->id}", ['name' => $admin->name, 'role' => 'Gerente'])->assertSessionHasErrors('role');
    }

    public function test_a_deactivated_user_cannot_log_in_and_loses_open_sessions(): void
    {
        $admin = $this->userWithRole('Administrator');
        $advisor = $this->userWithRole('Advisor', ['password' => 'secreto-123']);

        $this->actingAs($admin)->post("/admin/users/{$advisor->id}/deactivate")->assertSessionHasNoErrors();
        $this->assertTrue(AuditLog::where('action', 'user.deactivated')->exists());

        $this->post('/logout');
        $this->post('/login', ['email' => $advisor->email, 'password' => 'secreto-123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        // Una sesión que ya estaba abierta se cierra en la siguiente petición
        $this->actingAs($advisor->fresh())->get('/loans')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_reactivated_user_can_log_in_again(): void
    {
        $admin = $this->userWithRole('Administrator');
        $advisor = $this->userWithRole('Advisor', ['password' => 'secreto-123']);
        $advisor->forceFill(['deactivated_at' => now()])->save();

        $this->actingAs($admin)->post("/admin/users/{$advisor->id}/reactivate")->assertSessionHasNoErrors();
        $this->post('/logout');

        $this->post('/login', ['email' => $advisor->email, 'password' => 'secreto-123'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($advisor->fresh());
    }

    public function test_two_factor_can_be_reset_for_a_lost_phone(): void
    {
        $admin = $this->userWithRole('Administrator');
        $analyst = $this->userWithRole('Analista de crédito');
        $analyst->forceFill(['two_factor_secret' => 'SECRETO', 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($admin)->post("/admin/users/{$analyst->id}/two-factor/reset")->assertSessionHasNoErrors();

        $this->assertNull($analyst->fresh()->two_factor_confirmed_at);
        $this->assertNull($analyst->fresh()->two_factor_secret);
        $this->assertTrue(AuditLog::where('action', 'user.two_factor_reset')->exists());
    }

    public function test_the_invitation_can_be_resent_but_not_to_customers_or_inactive_users(): void
    {
        $admin = $this->userWithRole('Administrator');
        $advisor = $this->userWithRole('Advisor');
        $customer = $this->userWithRole('Customer');

        $this->actingAs($admin)->post("/admin/users/{$advisor->id}/invitation")->assertSessionHasNoErrors();
        Mail::assertSent(StaffInvitation::class, 1);

        $this->actingAs($admin)->post("/admin/users/{$customer->id}/invitation")->assertNotFound();

        $advisor->forceFill(['deactivated_at' => now()])->save();
        $this->actingAs($admin)->post("/admin/users/{$advisor->id}/invitation")->assertSessionHasErrors('user');
        Mail::assertSent(StaffInvitation::class, 1);
    }

    public function test_deactivation_removes_database_sessions(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->userWithRole('Administrator');
        $advisor = $this->userWithRole('Advisor');
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $advisor->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($admin)->post("/admin/users/{$advisor->id}/deactivate");

        $this->assertDatabaseMissing('sessions', ['user_id' => $advisor->id]);
    }
}
