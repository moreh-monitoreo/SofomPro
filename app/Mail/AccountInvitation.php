<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Invitación al cliente para que cree su propia contraseña; nadie del personal la conoce. */
class AccountInvitation extends Mailable
{
    public function __construct(public string $name, public string $url, public int $hours)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Activa tu cuenta - '.config('promas.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.account-invitation');
    }
}
