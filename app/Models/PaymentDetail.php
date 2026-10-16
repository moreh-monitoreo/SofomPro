<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentDetail extends Model
{
    use HasFactory;

    protected $fillable = ['payment_id', 'number', 'month', 'year', 'interest_payment', 'principal_payment', 'balance', 'monthly_payment', 'status', 'payed_at', 'payment_method', 'payment_reference', 'payment_total'];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
