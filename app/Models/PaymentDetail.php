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

    protected $fillable = ['payment_id', 'number', 'month', 'year', 'due_date', 'interest_payment', 'principal_payment', 'balance', 'monthly_payment', 'status', 'payed_at', 'payment_method', 'payment_reference', 'payment_total', 'registered_by', 'notes'];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
    ];

    /** Fecha límite de pago; en planes anteriores sin fecha se usa el último día del mes de la cuota. */
    public function dueDate(): Carbon
    {
        if ($this->due_date) {
            return $this->due_date->copy()->startOfDay();
        }

        return Carbon::create($this->year, (int) $this->month, 1)->endOfMonth()->startOfDay();
    }

    /** Cuotas sin pagar cuya fecha límite ya pasó (con los días de gracia configurados). */
    public function scopeOverdue(Builder $query, ?CarbonInterface $today = null): Builder
    {
        $limit = ($today ?? today())->copy()->startOfDay()->subDays((int) config('promas.overdue_grace_days', 0));

        return $query->where('status', 'N')->where(function ($q) use ($limit) {
            $q->where('due_date', '<', $limit->toDateString())
                ->orWhere(fn ($legacy) => $legacy->whereNull('due_date')->whereRaw('(year * 100 + month) < ?', [(int) $limit->format('Ym')]));
        });
    }

    public function isOverdue(?CarbonInterface $today = null): bool
    {
        $limit = ($today ?? today())->copy()->startOfDay()->subDays((int) config('promas.overdue_grace_days', 0));

        return $this->status === 'N' && $this->dueDate()->lt($limit);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
