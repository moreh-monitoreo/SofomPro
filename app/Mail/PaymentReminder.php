<?php

namespace App\Mail;

use App\Models\PaymentDetail;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Recordatorio al cliente antes del vencimiento de su cuota y el mismo día. */
class PaymentReminder extends Mailable
{
    public function __construct(public PaymentDetail $detail, public float $amount, public string $kind)
    {
    }

    public function envelope(): Envelope
    {
        $due = $this->detail->dueDate()->format('d/m/Y');

        return new Envelope(subject: $this->kind === 'vence'
            ? "Hoy vence tu pago de ".config('promas.name')
            : "Tu pago de ".config('promas.name')." vence el {$due}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.payment-reminder', with: [
            'customer' => $this->detail->payment?->loan?->customer,
            'due' => $this->detail->dueDate(),
            'url' => url(route('dashboard', absolute: false)),
        ]);
    }
}
