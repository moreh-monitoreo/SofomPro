<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Corte de caja de un día: una vez cerrado no se edita ni se borra. */
class CashCut extends Model
{
    protected $fillable = ['date', 'totals', 'total', 'cash_expected', 'cash_counted', 'cash_difference', 'notes', 'closed_by'];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'totals' => 'array',
        'total' => 'float',
        'cash_expected' => 'float',
        'cash_counted' => 'float',
        'cash_difference' => 'float',
    ];

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function update(array $attributes = [], array $options = [])
    {
        throw new \LogicException('Un corte de caja cerrado no se puede modificar.');
    }

    public function delete()
    {
        throw new \LogicException('Un corte de caja cerrado no se puede borrar.');
    }
}
