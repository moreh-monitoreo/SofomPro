<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Recordatorio de pago enviado al cliente (uno por cuota y tipo de aviso). */
class PaymentReminderLog extends Model
{
    protected $table = 'payment_reminders';

    public $timestamps = false;

    protected $fillable = ['payment_detail_id', 'kind', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];
}
