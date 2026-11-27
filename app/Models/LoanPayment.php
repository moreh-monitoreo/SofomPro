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

    protected $fillable = ['loan_id', 'kind', 'amount', 'method', 'reference', 'paid_at', 'registered_by', 'notes', 'allocations', 'snapshot', 'reverted_at', 'reverted_by', 'processor_fee', 'processor_net', 'reconciled_at', 'reconciled_by', 'bank_reference'];

    /** Formas de pago con su nombre para pantallas y reportes. */
    public const METHODS = [
        'EFECTIVO' => 'Efectivo',
        'TRANSFERENCIA' => 'Transferencia',
        'DEPOSITO' => 'Depósito',
        'STRIPE' => 'Tarjeta (Stripe)',
        'OTRO' => 'Otro',
    ];

    protected $casts = [
        'amount' => 'float',
        'paid_at' => 'datetime',
        'reverted_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'processor_fee' => 'float',
        'processor_net' => 'float',
        'allocations' => 'array',
        'snapshot' => 'array',
    ];

    protected $hidden = ['snapshot'];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /** Lo que no es efectivo llega al banco y se concilia contra el estado de cuenta. */
    public function needsReconciliation(): bool
    {
        return $this->method !== 'EFECTIVO' && ! $this->reverted_at;
    }
}
