<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Invitación a una persona del equipo para que cree su contraseña y active la verificación en dos pasos. */
class StaffInvitation extends Mailable
{
    public function __construct(public string $name, public string $role, public string $url, public int $hours)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu acceso al panel de '.config('promas.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.staff-invitation');
    }
}
