<?php

namespace App\Mail;

use App\Models\CollectionAction;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Aviso al gestor: venció una promesa de pago sin que el cliente completara lo prometido. */
class BrokenPromise extends Mailable
{
    public function __construct(public CollectionAction $promise, public float $paid)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Promesa de pago incumplida: '.$this->promise->loan?->customer?->full_name);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.broken-promise', with: [
            'customer' => $this->promise->loan?->customer?->full_name,
            'phone' => $this->promise->loan?->customer?->phone,
            'url' => url(route('loans.payments.index', $this->promise->loan_id, absolute: false)),
        ]);
    }
}
