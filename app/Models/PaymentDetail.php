<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentDetail extends Model
{
    use HasFactory;

    protected $fillable = ['payment_id', 'number', 'month', 'year', 'due_date', 'interest_payment', 'principal_payment', 'balance', 'monthly_payment', 'status', 'payed_at', 'payment_method', 'payment_reference', 'payment_total', 'registered_by', 'notes', 'moratory_iva_paid', 'moratory_paid', 'iva_paid', 'interest_paid', 'principal_paid'];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'moratory_iva_paid' => 'float',
        'moratory_paid' => 'float',
        'iva_paid' => 'float',
        'interest_paid' => 'float',
        'principal_paid' => 'float',
    ];

    /** IVA de los intereses ordinarios programado en la cuota. */
    public function scheduledIva(): float
    {
        return round((float) $this->monthly_payment - (float) $this->principal_payment - (float) $this->interest_payment, 2);
    }

    /** Lo que falta pagar de la cuota programada (sin moratorios), por concepto. */
    public function remaining(): array
    {
        return [
            'iva' => max(round($this->scheduledIva() - (float) $this->iva_paid, 2), 0.0),
            'interest' => max(round((float) $this->interest_payment - (float) $this->interest_paid, 2), 0.0),
            'principal' => max(round((float) $this->principal_payment - (float) $this->principal_paid, 2), 0.0),
        ];
    }

    public function remainingTotal(): float
    {
        return round(array_sum($this->remaining()), 2);
    }

    /** Fecha límite de pago; en planes anteriores sin fecha se usa el último día del mes de la cuota. */
    public function dueDate(): Carbon
    {
        if ($this->due_date) {
            return $this->due_date->copy()->startOfDay();
        }

        return Carbon::create($this->year, (int) $this->month, 1)->endOfMonth()->startOfDay();
    }

    /** Cuotas sin pagar cuya fecha límite ya pasó (con los días de gracia del producto). */
    public function scopeOverdue(Builder $query, ?CarbonInterface $today = null, ?int $graceDays = null): Builder
    {
        $limit = ($today ?? today())->copy()->startOfDay()->subDays($graceDays ?? (int) config('promas.overdue_grace_days', 0));

        return $query->where('status', 'N')->where(function ($q) use ($limit) {
            $q->where('due_date', '<', $limit->toDateString())
                ->orWhere(fn ($legacy) => $legacy->whereNull('due_date')->whereRaw('(year * 100 + month) < ?', [(int) $limit->format('Ym')]));
        });
    }

    public function isOverdue(?CarbonInterface $today = null, ?int $graceDays = null): bool
    {
        $limit = ($today ?? today())->copy()->startOfDay()->subDays($graceDays ?? (int) config('promas.overdue_grace_days', 0));

        return $this->status === 'N' && $this->dueDate()->lt($limit);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
