<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un pago recibido (abono) y cómo se aplicó a las cuotas según la prelación del contrato. */
class LoanPayment extends Model
{
    public const KINDS = [
        'adelantado' => 'Pago a cuotas (vencidas y siguientes)',
        'anticipado' => 'Pago anticipado a capital',
        'liquidacion' => 'Liquidación anticipada',
    ];

    protected $fillable = ['loan_id', 'kind', 'amount', 'method', 'reference', 'paid_at', 'registered_by', 'notes', 'allocations', 'snapshot', 'reverted_at', 'reverted_by'];

    protected $casts = [
        'amount' => 'float',
        'paid_at' => 'datetime',
        'reverted_at' => 'datetime',
        'allocations' => 'array',
        'snapshot' => 'array',
    ];

    protected $hidden = ['snapshot'];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
