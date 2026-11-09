<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Condiciones comerciales de cada producto (tasa, comisión, montos y plazos).
 * Se editan desde el panel de administración; si la tabla aún no existe o está vacía
 * se usan los valores por defecto de {@see self::defaults()}.
 */
class LoanProduct extends Model
{
    public const TYPES = ['NORMAL', 'FUNERARIO'];

    /** Documentos que el cliente sube en su solicitud; son los obligatorios por defecto para aprobar. */
    public const DEFAULT_DOCUMENTS = ['INE (frente)', 'INE (reverso)', 'Comprobante de domicilio', 'Estado de cuenta bancario'];

    protected $fillable = ['type', 'name', 'annual_rate', 'opening_fee', 'iva', 'min_amount', 'max_amount', 'amount_step', 'min_term', 'max_term', 'active', 'moratory_rate', 'grace_days', 'early_payoff_penalty', 'min_forced_months', 'required_documents'];

    protected $casts = [
        'annual_rate' => 'float',
        'opening_fee' => 'float',
        'iva' => 'float',
        'min_amount' => 'integer',
        'max_amount' => 'integer',
        'amount_step' => 'integer',
        'min_term' => 'integer',
        'max_term' => 'integer',
        'active' => 'boolean',
        'moratory_rate' => 'float',
        'grace_days' => 'integer',
        'early_payoff_penalty' => 'float',
        'min_forced_months' => 'integer',
        'required_documents' => 'array',
    ];

    public static function defaults(string $type): array
    {
        $guarantee = $type === 'FUNERARIO';

        return [
            'type' => $guarantee ? 'FUNERARIO' : 'NORMAL',
            'name' => $guarantee ? 'Préstamo con garantía (plan funerario)' : 'Préstamo personal',
            'annual_rate' => $guarantee ? 74.28 : 79.56,
            'opening_fee' => 3.0,
            'iva' => 16.0,
            'min_amount' => 2000,
            'max_amount' => 10000,
            'amount_step' => 1000,
            'min_term' => 6,
            'max_term' => 12,
            'active' => true,
            'moratory_rate' => 42.0,
            'grace_days' => (int) config('promas.overdue_grace_days', 0),
            'early_payoff_penalty' => 3.0,
            'min_forced_months' => 0,
            'required_documents' => self::DEFAULT_DOCUMENTS,
        ];
    }

    /** Producto del tipo indicado; nunca falla, así el sitio sigue funcionando sin configuración. */
    public static function forType(?string $type): self
    {
        $type = $type === 'FUNERARIO' ? 'FUNERARIO' : 'NORMAL';

        $product = Schema::hasTable('loan_products') ? static::where('type', $type)->first() : null;

        return $product ?? new static(static::defaults($type));
    }

    /** Tipos de documento que deben estar en el expediente para aprobar. */
    public function requiredDocuments(): array
    {
        return $this->required_documents ?? self::DEFAULT_DOCUMENTS;
    }

    /** Tasa moratoria diaria: anual entre 360 (cláusula octava). */
    public function dailyMoratoryRate(): float
    {
        return $this->moratory_rate / 100 / 360;
    }

    public function monthlyRate(): float
    {
        return $this->annual_rate / 12 / 100;
    }

    /** Montos que el simulador permite elegir. */
    public function amounts(): array
    {
        return range($this->min_amount, $this->max_amount, max($this->amount_step, 1));
    }

    public function terms(): array
    {
        return range($this->min_term, $this->max_term);
    }
}
