<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = ['loan_id', 'request_amount', 'payment_amount', 'cat_type', 'cat_amount', 'total_months', 'month_rate', 'opening_fees', 'status'];

    public function details()
    {
        return $this->hasMany(PaymentDetail::class);
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
