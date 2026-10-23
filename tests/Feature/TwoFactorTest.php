<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTwoFactor;
use App\Models\User;
use App\Services\TwoFactorAuth;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\BuildsLoans;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use BuildsLoans, RefreshDatabase;

    private Google2FA $totp;

    protected function setUp(): void
    {
        parent::setUp();
        config(['promas.two_factor_required' => true]);
        $this->totp = new Google2FA;
    }

    /** Miembro del equipo con 2FA ya activado y su secreto. */
    private function enrolled(string $role = 'Administrator'): array
    {
        $user = $this->userWithRole($role, ['password' => 'secreta-123']);
        $secret = $this->totp->generateSecretKey(32);
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $codes = app(TwoFactorAuth::class)->generateRecoveryCodes($user);

        return [$user, $secret, $codes];
    }

    private function verified(): array
    {
        return [EnsureTwoFactor::SESSION_KEY => now()];
    }

    // ---- Obligatoriedad ---------------------------------------------------------------------

    public function test_staff_without_two_factor_are_sent_to_setup_from_every_panel_area(): void
    {
        foreach (['Administrator', 'Advisor'] as $role) {
            $user = $this->userWithRole($role);

            foreach (['/dashboard', '/pipeline', '/loans', '/portfolio/overdue', '/profile'] as $url) {
                $this->actingAs($user)->get($url)->assertRedirect(route('two-factor.setup'));
            }
        }

        $this->actingAs($this->userWithRole('Administrator'))->get('/admin/loan-products')->assertRedirect(route('two-factor.setup'));
    }

    public function test_json_requests_get_403_instead_of_a_redirect(): void
    {
        $this->actingAs($this->userWithRole('Advisor'))->postJson('/loans/1/validation')
            ->assertForbidden()->assertJsonPath('two_factor', 'two-factor.setup');
    }

    public function test_customers_are_not_affected(): void
    {
        $this->actingAs($this->userWithRole('Customer'))->get('/dashboard')->assertOk();
        $this->actingAs($this->userWithRole('Customer'))->get(route('two-factor.setup'))->assertNotFound();
    }

    public function test_it_can_be_turned_off_by_configuration(): void
    {
        config(['promas.two_factor_required' => false]);

        $this->actingAs($this->userWithRole('Administrator'))->get('/pipeline')->assertOk();
        $this->actingAs($this->userWithRole('Administrator'))->get(route('two-factor.setup'))->assertNotFound();
    }

    // ---- Activación -------------------------------------------------------------------------

    public function test_the_setup_page_shows_a_qr_and_stores_the_secret_encrypted(): void
    {
        $user = $this->userWithRole('Administrator');

        $response = $this->actingAs($user)->get(route('two-factor.setup'))->assertOk();

        $secret = $user->fresh()->two_factor_secret;
        $this->assertSame(32, strlen($secret));
        $this->assertStringContainsString('&lt;svg', $response->getContent());

        $raw = DB::table('users')->where('id', $user->id)->value('two_factor_secret');
        $this->assertNotSame($secret, $raw);
        $this->assertStringNotContainsString($secret, $raw);
    }

    public function test_the_secret_is_never_sent_to_the_browser(): void
    {
        [$user, $secret, $codes] = $this->enrolled();

        $html = $this->actingAs($user)->withSession($this->verified())->get('/dashboard')->getContent();

        $this->assertStringNotContainsString($secret, $html);
        $this->assertStringNotContainsString('two_factor_secret', $html);
        $this->assertStringNotContainsString('two_factor_recovery_codes', $html);
        $this->assertArrayNotHasKey('two_factor_secret', $user->fresh()->toArray());
    }

    public function test_confirming_with_the_right_code_activates_it_and_shows_recovery_codes_once(): void
    {
        $user = $this->userWithRole('Administrator');
        $this->actingAs($user)->get(route('two-factor.setup'));
        $secret = $user->fresh()->two_factor_secret;

        $response = $this->post(route('two-factor.confirm'), ['code' => $this->totp->getCurrentOtp($secret)]);

        $response->assertRedirect(route('two-factor.codes'));
        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertCount(8, $user->two_factor_recovery_codes);

        $this->get(route('two-factor.codes'))->assertOk()->assertSee('&quot;component&quot;:&quot;Auth\/TwoFactorRecoveryCodes&quot;', false);
        $this->get(route('two-factor.codes'))->assertRedirect(route('dashboard'));

        $this->get('/pipeline')->assertOk();
    }

    public function test_confirming_with_a_wrong_code_does_not_activate_it(): void
    {
        $user = $this->userWithRole('Administrator');
        $this->actingAs($user)->get(route('two-factor.setup'));

        $this->post(route('two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->get('/pipeline')->assertRedirect(route('two-factor.setup'));
    }

    public function test_recovery_codes_are_stored_hashed(): void
    {
        [$user, , $codes] = $this->enrolled();

        $this->assertCount(8, $codes);
        foreach ($user->fresh()->two_factor_recovery_codes as $hash) {
            $this->assertStringStartsWith('$2y$', $hash);
            $this->assertNotContains($hash, $codes);
        }
    }

    // ---- Verificación al entrar ---------------------------------------------------------------

    public function test_an_enrolled_user_must_pass_the_challenge_in_every_new_session(): void
    {
        [$user] = $this->enrolled();

        $this->actingAs($user)->get('/pipeline')->assertRedirect(route('two-factor.challenge'));
        $this->get('/loans')->assertRedirect(route('two-factor.challenge'));
        $this->get('/admin/loan-products')->assertRedirect(route('two-factor.challenge'));
    }

    public function test_the_right_code_opens_the_panel_and_returns_to_the_requested_page(): void
    {
        [$user, $secret] = $this->enrolled();

        $this->actingAs($user)->get('/pipeline')->assertRedirect(route('two-factor.challenge'));
        $this->get(route('two-factor.challenge'))->assertOk();

        $this->post(route('two-factor.verify'), ['code' => $this->totp->getCurrentOtp($secret)])->assertRedirect(url('/pipeline'));

        $this->get('/pipeline')->assertOk();
        $this->get('/admin/loan-products')->assertOk();
    }

    public function test_a_wrong_code_keeps_the_panel_closed(): void
    {
        [$user] = $this->enrolled();

        $this->actingAs($user)->post(route('two-factor.verify'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->get('/pipeline')->assertRedirect(route('two-factor.challenge'));
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        [$user, $secret] = $this->enrolled();
        $code = $this->totp->getCurrentOtp($secret);

        $this->actingAs($user)->post(route('two-factor.verify'), ['code' => $code])->assertRedirect();
        $this->flushSession();

        $this->post(route('two-factor.verify'), ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_a_recovery_code_works_once(): void
    {
        [$user, , $codes] = $this->enrolled();

        $this->actingAs($user)->post(route('two-factor.verify'), ['recovery_code' => strtoupper($codes[0])])->assertRedirect();
        $this->get('/pipeline')->assertOk();
        $this->assertCount(7, $user->fresh()->two_factor_recovery_codes);

        $this->flushSession();
        $this->post(route('two-factor.verify'), ['recovery_code' => $codes[0]])->assertSessionHasErrors('recovery_code');
        $this->post(route('two-factor.verify'), ['recovery_code' => $codes[1]])->assertSessionHasNoErrors();
    }

    public function test_the_verification_expires_after_twelve_hours(): void
    {
        [$user] = $this->enrolled();

        $this->actingAs($user)->withSession($this->verified())->get('/pipeline')->assertOk();

        $this->travel(13)->hours();
        $this->get('/pipeline')->assertRedirect(route('two-factor.challenge'));
    }

    public function test_the_challenge_is_rate_limited(): void
    {
        [$user] = $this->enrolled();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->post(route('two-factor.verify'), ['code' => '111111']);
        }

        $this->actingAs($user)->post(route('two-factor.verify'), ['code' => '111111'])->assertStatus(429);
    }

    public function test_an_advisor_also_needs_the_second_step(): void
    {
        [$advisor] = $this->enrolled('Advisor');

        $this->actingAs($advisor)->get('/pipeline')->assertRedirect(route('two-factor.challenge'));
        $this->withSession($this->verified())->get('/pipeline')->assertOk();
    }

    // ---- Códigos de recuperación y reinicio -----------------------------------------------------

    public function test_regenerating_recovery_codes_needs_the_password(): void
    {
        [$user, , $oldCodes] = $this->enrolled();

        $this->actingAs($user)->withSession($this->verified())->post(route('two-factor.regenerate'), ['password' => 'incorrecta'])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check($oldCodes[0], $user->fresh()->two_factor_recovery_codes[0]));

        $this->post(route('two-factor.regenerate'), ['password' => 'secreta-123'])->assertRedirect(route('two-factor.codes'));
        $this->assertFalse(Hash::check($oldCodes[0], $user->fresh()->two_factor_recovery_codes[0]));
    }

    public function test_regenerating_codes_requires_a_verified_session(): void
    {
        [$user] = $this->enrolled();

        $this->actingAs($user)->post(route('two-factor.regenerate'), ['password' => 'secreta-123'])->assertRedirect(route('two-factor.challenge'));
    }

    public function test_the_reset_command_removes_two_factor_so_the_user_enrolls_again(): void
    {
        [$user] = $this->enrolled();

        $this->artisan('two-factor:reset', ['email' => $user->email])->assertSuccessful();

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->actingAs($user)->get('/pipeline')->assertRedirect(route('two-factor.setup'));

        $this->artisan('two-factor:reset', ['email' => 'nadie@example.com'])->assertFailed();
    }

    public function test_guests_cannot_reach_the_two_factor_pages(): void
    {
        $this->get(route('two-factor.setup'))->assertRedirect('/login');
        $this->get(route('two-factor.challenge'))->assertRedirect('/login');
        $this->post(route('two-factor.verify'), ['code' => '123456'])->assertRedirect('/login');
    }
}
