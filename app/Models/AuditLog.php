<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Registro inmutable de quién hizo qué y cuándo; no se edita ni se borra desde la aplicación. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'loan_id', 'subject_type', 'subject_id', 'before', 'after', 'note', 'ip'];

    protected $casts = ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];

    /** Acciones registradas y su descripción para el administrador. */
    public const ACTIONS = [
        'loan.validation_started' => 'Pasó a validación',
        'loan.approved' => 'Aprobó la solicitud',
        'loan.rejected' => 'Rechazó la solicitud',
        'loan.conditions_changed' => 'Cambió monto o plazo',
        'loan.disbursed' => 'Registró el desembolso',
        'loan.plan_saved' => 'Guardó el plan de pagos',
        'payment.registered' => 'Registró un pago',
        'payment.reverted' => 'Revirtió un pago',
        'document.uploaded' => 'Subió un documento',
        'document.deleted' => 'Eliminó un documento',
        'account.invited' => 'Envió invitación de cuenta',
        'product.updated' => 'Cambió un producto o tasa',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function update(array $attributes = [], array $options = [])
    {
        throw new \LogicException('La bitácora de auditoría no se puede modificar.');
    }

    public function delete()
    {
        throw new \LogicException('La bitácora de auditoría no se puede borrar.');
    }
}
