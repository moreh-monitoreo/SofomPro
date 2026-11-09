<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /** Roles del equipo: entran al panel interno y deben usar verificación en dos pasos. */
    public const STAFF_ROLES = ['Administrator', 'Advisor', 'Analista de crédito', 'Gerente'];

    /** Nombre en español y qué puede hacer cada rol del equipo (para la pantalla de usuarios). */
    public const ROLE_LABELS = [
        'Administrator' => ['label' => 'Administrador', 'description' => 'Acceso completo: usuarios, productos, bitácora y correcciones de pagos.'],
        'Gerente' => ['label' => 'Gerente', 'description' => 'Aprueba o rechaza solicitudes, registra desembolsos y consulta reportes.'],
        'Analista de crédito' => ['label' => 'Analista de crédito', 'description' => 'Captura Buró y PLD y da por validadas las solicitudes.'],
        'Advisor' => ['label' => 'Asesor', 'description' => 'Consulta expedientes, sube documentos y registra pagos.'],
    ];

    /** Quién captura Buró y PLD y da por validada una solicitud. */
    public const VALIDATOR_ROLES = ['Administrator', 'Analista de crédito'];

    /** Quién aprueba, rechaza y registra el desembolso. */
    public const APPROVER_ROLES = ['Administrator', 'Gerente'];

    public function isStaff(): bool
    {
        return $this->hasAnyRole(self::STAFF_ROLES);
    }

    /** Una cuenta desactivada por un administrador no puede iniciar sesión. */
    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'privacy_accepted_at',
        'consent_version',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
