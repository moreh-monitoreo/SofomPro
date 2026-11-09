<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'policy_folio', 'folio', 'customer_id', 'amount', 'purpose', 'purpose_description', 'term', 'score', 'approved_at', 'reject_at', 'start_at', 'paid_at', 'validation_started_at', 'disbursed_at', 'requested_amount', 'requested_term', 'approved_by', 'reject_reason', 'submitted_at', 'bureau_folio', 'bureau_result', 'pld_result', 'pld_evidence_path', 'validated_at', 'validated_by', 'disbursement_method', 'disbursement_reference', 'disbursed_by'];

    public const STAGES = [
        'incomplete' => 'Incompleta',
        'received' => 'Recibida',
        'in_validation' => 'En validación',
        'validated' => 'Validada',
        'approved' => 'Aprobada',
        'disbursed' => 'Desembolsada',
        'paid' => 'Liquidada',
        'rejected' => 'Rechazada',
    ];

    /** Cómo ve el cliente cada etapa: la revisión interna se resume en "En revisión". */
    public const CUSTOMER_STAGES = [
        'incomplete' => 'Solicitud incompleta',
        'received' => 'En revisión',
        'in_validation' => 'En revisión',
        'validated' => 'En revisión',
        'approved' => 'Aprobada',
        'disbursed' => 'Desembolsada',
        'paid' => 'Liquidada',
        'rejected' => 'Rechazada',
    ];

    public const PLD_RESULTS = [
        'sin_coincidencias' => 'Sin coincidencias en listas',
        'con_coincidencias' => 'Con coincidencias en listas',
    ];

    public const DISBURSEMENT_METHODS = [
        'SPEI' => 'Transferencia SPEI',
        'CHEQUE' => 'Cheque',
        'EFECTIVO' => 'Efectivo',
    ];

    protected $appends = ['status', 'stage', 'stage_label'];

    protected $hidden = ['score'];

    /** Etapa del flujo de la solicitud, calculada a partir de sus fechas. */
    public function getStageAttribute(): string
    {
        return match (true) {
            (bool) $this->reject_at => 'rejected',
            (bool) $this->paid_at => 'paid',
            (bool) $this->disbursed_at => 'disbursed',
            (bool) $this->approved_at => 'approved',
            (bool) $this->validated_at => 'validated',
            (bool) $this->validation_started_at => 'in_validation',
            (bool) $this->submitted_at => 'received',
            default => 'incomplete',
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
        $pending = fn () => $query->whereNull('reject_at')->whereNull('approved_at');

        return match ($stage) {
            'rejected' => $query->whereNotNull('reject_at'),
            'paid' => $query->whereNull('reject_at')->whereNotNull('paid_at'),
            'disbursed' => $query->whereNull('reject_at')->whereNull('paid_at')->whereNotNull('disbursed_at'),
            'approved' => $query->whereNull('reject_at')->whereNull('paid_at')->whereNull('disbursed_at')->whereNotNull('approved_at'),
            'validated' => $pending()->whereNotNull('validated_at'),
            'in_validation' => $pending()->whereNull('validated_at')->whereNotNull('validation_started_at'),
            'received' => $pending()->whereNull('validated_at')->whereNull('validation_started_at')->whereNotNull('submitted_at'),
            'incomplete' => $pending()->whereNull('validated_at')->whereNull('validation_started_at')->whereNull('submitted_at'),
            default => $query,
        };
    }

    /** Préstamos con al menos una cuota vencida sin pagar, con los días de gracia de su producto. */
    public function scopeOverdue(Builder $query, ?CarbonInterface $today = null): Builder
    {
        return $query->where(function (Builder $query) use ($today) {
            foreach (LoanProduct::TYPES as $type) {
                $grace = LoanProduct::forType($type)->grace_days;
                $query->orWhere(function (Builder $q) use ($type, $grace, $today) {
                    $type === 'FUNERARIO'
                        ? $q->where('type', 'FUNERARIO')
                        : $q->where(fn ($t) => $t->whereNull('type')->orWhere('type', '!=', 'FUNERARIO'));
                    $q->whereHas('payment.details', fn ($details) => $details->overdue($today, $grace));
                });
            }
        });
    }

    public function product(): LoanProduct
    {
        return LoanProduct::forType($this->type);
    }

    /** Documentos obligatorios del producto que aún no están en el expediente. */
    public function missingDocuments(): array
    {
        $types = $this->documentations()->pluck('type')->map(fn ($t) => mb_strtolower(trim((string) $t)))->all();

        return array_values(array_filter(
            $this->product()->requiredDocuments(),
            fn ($required) => ! in_array(mb_strtolower($required), $types, true),
        ));
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

    public function movements()
    {
        return $this->hasMany(LoanPayment::class);
    }
}
