<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TwoFactorAuth;
use Illuminate\Console\Command;

class ResetTwoFactor extends Command
{
    protected $signature = 'two-factor:reset {email : Correo de la cuenta}';

    protected $description = 'Quita la verificación en dos pasos de una cuenta (por pérdida del teléfono); deberá activarla de nuevo al entrar';

    public function handle(TwoFactorAuth $twoFactor): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No existe una cuenta con ese correo.');

            return self::FAILURE;
        }

        $twoFactor->disable($user);
        $this->info("Listo: {$user->email} deberá configurar su verificación en dos pasos en el próximo inicio de sesión.");

        return self::SUCCESS;
    }
}
