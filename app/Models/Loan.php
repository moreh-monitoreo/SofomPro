<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'policy_folio', 'folio', 'customer_id', 'amount', 'purpose', 'purpose_description', 'term', 'score', 'approved_at', 'reject_at', 'start_at', 'paid_at', 'validation_started_at', 'disbursed_at', 'requested_amount', 'requested_term', 'approved_by', 'reject_reason'];

    public const STAGES = [
        'received' => 'Recibida',
        'in_validation' => 'En validación',
        'approved' => 'Aprobada',
        'disbursed' => 'Desembolsada',
        'paid' => 'Liquidada',
        'rejected' => 'Rechazada',
    ];

    /** Cómo ve el cliente cada etapa: la revisión interna se resume en "En revisión". */
    public const CUSTOMER_STAGES = [
        'received' => 'En revisión',
        'in_validation' => 'En revisión',
        'approved' => 'Aprobada',
        'disbursed' => 'Desembolsada',
        'paid' => 'Liquidada',
        'rejected' => 'Rechazada',
    ];

    protected $appends = ['status', 'stage', 'stage_label'];

    /** Etapa del flujo de la solicitud, calculada a partir de sus fechas. */
    public function getStageAttribute(): string
    {
        return match (true) {
            (bool) $this->reject_at => 'rejected',
            (bool) $this->paid_at => 'paid',
            (bool) $this->disbursed_at => 'disbursed',
            (bool) $this->approved_at => 'approved',
            (bool) $this->validation_started_at => 'in_validation',
            default => 'received',
        };
    }

    public function getStageLabelAttribute(): string
    {
        return self::STAGES[$this->stage];
    }

    public function getStatusAttribute()
    {
        if (!$this->approved_at && !$this->reject_at) {
            return 'Pendiente';
        }
        if ($this->paid_at) {
            return 'Pagado';
        }
        if ($this->approved_at) {
            return 'Aprobado';
        } else {
            return 'Rechazado';
        }
    }
    
    /** Filtra por etapa con las mismas reglas que {@see self::getStageAttribute()}. */
    public function scopeStage(Builder $query, string $stage): Builder
    {
        return match ($stage) {
            'rejected' => $query->whereNotNull('reject_at'),
            'paid' => $query->whereNull('reject_at')->whereNotNull('paid_at'),
            'disbursed' => $query->whereNull('reject_at')->whereNull('paid_at')->whereNotNull('disbursed_at'),
            'approved' => $query->whereNull('reject_at')->whereNull('paid_at')->whereNull('disbursed_at')->whereNotNull('approved_at'),
            'in_validation' => $query->whereNull('reject_at')->whereNull('approved_at')->whereNotNull('validation_started_at'),
            'received' => $query->whereNull('reject_at')->whereNull('approved_at')->whereNull('validation_started_at'),
            default => $query,
        };
    }

    /** Préstamos con al menos una cuota vencida sin pagar. */
    public function scopeOverdue(Builder $query, ?CarbonInterface $today = null): Builder
    {
        return $query->whereHas('payment.details', fn ($details) => $details->overdue($today));
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function documentations()
    {
        return $this->hasMany(Documentation::class);
    }
}
