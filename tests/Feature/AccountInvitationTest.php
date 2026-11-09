<?php

namespace Tests\Feature;

use App\Mail\AccountInvitation;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsLoans;
use Tests\TestCase;

class AccountInvitationTest extends TestCase
{
    use BuildsLoans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.documents' => 'local']);
        Storage::fake('local');
        $this->role('Customer');
    }

    private function pendingLoan(): Loan
    {
        $customer = Customer::create([
            'status_id' => $this->customerStatus('LOAN CREATED')->id,
            'name' => 'Laura',
            'last_name' => 'Gómez',
            'gender' => 'Femenino',
            'email' => 'laura@example.com',
            'curp' => 'GOML900101MSLMRR01',
            'rfc' => 'GOML900101AB1',
        ]);

        return $this->readyForApproval(Loan::create(['customer_id' => $customer->id, 'type' => 'NORMAL', 'amount' => 3000, 'term' => 6, 'purpose' => 'Negocio']));
    }

    private function approve(Loan $loan): void
    {
        $this->actingAs($this->userWithRole('Administrator'))->putJson("/loans/{$loan->id}", ['status' => 'accepted'])->assertOk();
    }

    public function test_approving_sends_an_invitation_instead_of_creating_a_known_password(): void
    {
        Mail::fake();
        $loan = $this->pendingLoan();

        $this->approve($loan);

        Mail::assertSent(AccountInvitation::class, fn ($mail) => $mail->hasTo('laura@example.com')
            && str_contains($mail->url, '/reset-password/')
            && str_contains($mail->url, 'email=laura%40example.com'));
        $this->assertTrue(User::where('email', 'laura@example.com')->first()->hasRole('Customer'));
    }

    public function test_the_invitation_link_lets_the_customer_choose_a_password(): void
    {
        Mail::fake();
        $loan = $this->pendingLoan();
        $this->approve($loan);

        $url = null;
        Mail::assertSent(AccountInvitation::class, function ($mail) use (&$url) {
            $url = $mail->url;

            return true;
        });
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $token = basename(parse_url($url, PHP_URL_PATH));

        auth()->logout();
        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'laura@example.com',
            'password' => 'MiClaveNueva#2026',
            'password_confirmation' => 'MiClaveNueva#2026',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('MiClaveNueva#2026', User::where('email', 'laura@example.com')->first()->password));
    }

    public function test_the_loan_page_never_exposes_a_password(): void
    {
        Mail::fake();
        $loan = $this->pendingLoan();
        $this->approve($loan);

        $response = $this->actingAs($this->userWithRole('Administrator'))->get("/loans/{$loan->id}");

        $response->assertOk()->assertInertia(fn ($page) => $page->where('account.email', 'laura@example.com')->missing('account.password'));
        $this->assertFalse(Schema::hasColumn('customers', 'account_password'));
    }

    public function test_staff_can_resend_the_invitation_only_for_approved_loans(): void
    {
        Mail::fake();
        $loan = $this->pendingLoan();
        $staff = $this->userWithRole('Advisor');

        $this->actingAs($staff)->postJson("/loans/{$loan->id}/account/invitation")->assertStatus(422);
        Mail::assertNothingSent();

        $this->approve($loan);
        Mail::assertSentCount(1);

        $this->actingAs($staff)->postJson("/loans/{$loan->id}/account/invitation")->assertOk();
        Mail::assertSentCount(2);
    }

    public function test_the_invitation_expires_with_the_configured_window(): void
    {
        $this->assertGreaterThanOrEqual(1440, config('auth.passwords.users.expire'));
    }

    public function test_public_registration_is_closed(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'Password#123', 'password_confirmation' => 'Password#123'])->assertStatus(404);
        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }
}
