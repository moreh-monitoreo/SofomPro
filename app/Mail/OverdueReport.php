<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/** Resumen diario de clientes con cuotas vencidas. */
class OverdueReport extends Mailable
{
    public function __construct(public Collection $rows)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Cartera vencida: '.$this->rows->count().' cliente(s) con atraso - '.now()->format('d/m/Y'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.overdue-report', with: [
            'total' => round($this->rows->sum('overdue_amount'), 2),
            'url' => url(route('portfolio.overdue', absolute: false)),
        ]);
    }
}
