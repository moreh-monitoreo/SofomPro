<?php

namespace App\Mail;

use App\Models\ContactRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Aviso al equipo de una nueva solicitud de cita; responder el correo contesta al cliente. */
class ContactReceived extends Mailable
{
    public function __construct(public ContactRequest $contact)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->contact->email, $this->contact->name)],
            subject: 'Nueva solicitud de cita: '.$this->contact->name,
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.contact-received');
    }
}
