<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Una gestión de cobranza sobre un préstamo; si el resultado es una promesa de pago, se le da seguimiento. */
class CollectionAction extends Model
{
    public const CHANNELS = [
        'llamada' => 'Llamada',
        'visita' => 'Visita',
        'mensaje' => 'Mensaje (SMS o WhatsApp)',
        'correo' => 'Correo',
    ];

    public const OUTCOMES = [
        'contactado' => 'Contactado, sin promesa',
        'promesa' => 'Promesa de pago',
        'no_contesta' => 'No contesta',
        'ilocalizable' => 'Ilocalizable',
        'negativa' => 'Se niega a pagar',
        'otro' => 'Otro',
    ];

    public const PROMISE_STATUSES = [
        'pendiente' => 'Pendiente',
        'cumplida' => 'Cumplida',
        'incumplida' => 'Incumplida',
    ];

    protected $fillable = ['loan_id', 'user_id', 'channel', 'outcome', 'notes', 'promise_amount', 'promise_date', 'promise_status', 'promise_resolved_at'];

    protected $casts = [
        'promise_amount' => 'float',
        'promise_date' => 'date:Y-m-d',
        'promise_resolved_at' => 'datetime',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
