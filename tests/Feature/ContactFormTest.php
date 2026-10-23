<?php

namespace Tests\Feature;

use App\Mail\ContactConfirmation;
use App\Mail\ContactReceived;
use App\Models\ContactRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['promas.notification_email' => 'equipo@promas.test', 'promas.recaptcha.secret' => null]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Laura Gómez',
            'email' => 'laura@example.com',
            'phone' => '6671234567',
            'message' => 'Quiero información de un préstamo.',
        ], $override);
    }

    public function test_a_request_is_saved_and_both_emails_are_sent(): void
    {
        $this->postJson('/contacto', $this->payload())->assertCreated();

        $contact = ContactRequest::firstOrFail();
        $this->assertSame('laura@example.com', $contact->email);
        $this->assertNotNull($contact->ip_address);

        Mail::assertSent(ContactConfirmation::class, fn ($mail) => $mail->hasTo('laura@example.com'));
        Mail::assertSent(ContactReceived::class, function ($mail) {
            return $mail->hasTo('equipo@promas.test') && $mail->hasReplyTo('laura@example.com');
        });
    }

    public function test_the_message_and_phone_are_optional(): void
    {
        $this->postJson('/contacto', ['name' => 'Laura', 'email' => 'laura@example.com'])->assertCreated();

        $this->assertSame(1, ContactRequest::count());
    }

    public function test_invalid_data_is_rejected_without_saving_or_sending(): void
    {
        $this->postJson('/contacto', $this->payload(['name' => '', 'email' => 'no-es-correo', 'phone' => '123']))
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'phone']);

        $this->assertSame(0, ContactRequest::count());
        Mail::assertNothingSent();
    }

    public function test_a_filled_honeypot_is_silently_discarded(): void
    {
        $this->postJson('/contacto', $this->payload(['website' => 'http://spam.example']))->assertOk();

        $this->assertSame(0, ContactRequest::count());
        Mail::assertNothingSent();
    }

    public function test_a_failed_email_does_not_lose_the_request(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));

        $this->postJson('/contacto', $this->payload())->assertCreated();
        $this->assertSame(1, ContactRequest::count());
    }

    public function test_the_team_notification_is_skipped_when_no_address_is_configured(): void
    {
        config(['promas.notification_email' => null]);

        $this->postJson('/contacto', $this->payload())->assertCreated();

        Mail::assertSent(ContactConfirmation::class);
        Mail::assertNotSent(ContactReceived::class);
    }

    public function test_recaptcha_is_required_when_configured(): void
    {
        config(['promas.recaptcha.secret' => 'secret']);
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false])]);

        $this->postJson('/contacto', $this->payload())->assertStatus(422)->assertJsonValidationErrors('captcha');
        $this->postJson('/contacto', $this->payload(['g-recaptcha-response' => 'token-falso']))->assertStatus(422);

        $this->assertSame(0, ContactRequest::count());
    }

    public function test_a_valid_recaptcha_token_is_accepted(): void
    {
        config(['promas.recaptcha.secret' => 'secret']);
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true])]);

        $this->postJson('/contacto', $this->payload(['g-recaptcha-response' => 'token-bueno']))->assertCreated();

        Http::assertSent(fn ($request) => $request['secret'] === 'secret' && $request['response'] === 'token-bueno');
    }

    public function test_the_form_is_rate_limited_per_client(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/contacto', $this->payload())->assertCreated();
        }

        $this->postJson('/contacto', $this->payload())->assertStatus(429);
    }

    public function test_the_emails_render_with_the_customer_data(): void
    {
        $contact = ContactRequest::create($this->payload());

        $confirmation = (new ContactConfirmation($contact))->render();
        $this->assertStringContainsString('Laura Gómez', $confirmation);
        $this->assertStringContainsString('Quiero información de un préstamo.', $confirmation);

        $received = (new ContactReceived($contact))->render();
        $this->assertStringContainsString('6671234567', $received);
        $this->assertStringContainsString('laura@example.com', $received);
    }
}
