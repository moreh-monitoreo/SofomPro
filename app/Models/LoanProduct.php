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

    protected $fillable = ['type', 'name', 'annual_rate', 'opening_fee', 'iva', 'min_amount', 'max_amount', 'amount_step', 'min_term', 'max_term', 'active'];

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
        ];
    }

    /** Producto del tipo indicado; nunca falla, así el sitio sigue funcionando sin configuración. */
    public static function forType(?string $type): self
    {
        $type = $type === 'FUNERARIO' ? 'FUNERARIO' : 'NORMAL';

        $product = Schema::hasTable('loan_products') ? static::where('type', $type)->first() : null;

        return $product ?? new static(static::defaults($type));
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
