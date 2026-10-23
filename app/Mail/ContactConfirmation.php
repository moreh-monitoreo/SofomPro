<?php

namespace App\Mail;

use App\Models\ContactRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Correo al cliente que agendó una cita desde el sitio. */
class ContactConfirmation extends Mailable
{
    public function __construct(public ContactRequest $contact)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recibimos tu solicitud de cita - '.config('promas.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.contact-confirmation');
    }
}
