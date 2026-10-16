<?php

namespace App\Actions\Loans;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class CreateCustomerAccountAction
{
    /**
     * Crea (o reutiliza) la cuenta de usuario del cliente y la vincula con su perfil.
     * La contraseña inicial se guarda cifrada para que el administrador la consulte en el perfil;
     * por ahora no se envía nada al cliente.
     */
    public function handle(Customer $customer): User
    {
        $user = ($customer->user_id ? User::find($customer->user_id) : null)
            ?? User::where('email', $customer->email)->first();

        if (! $user) {
            $plain = $this->newPassword();
            $user = User::create([
                'name' => $customer->full_name,
                'email' => $customer->email,
                'password' => $plain,
            ]);
            $customer->update(['account_password' => Crypt::encryptString($plain)]);
        }

        if (! $user->hasRole('Customer')) {
            $user->assignRole('Customer');
        }

        $customer->update(['user_id' => $user->id]);

        return $user;
    }

    /** Genera una contraseña nueva, la aplica a la cuenta y la deja guardada cifrada. */
    public function resetPassword(Customer $customer): string
    {
        $user = $this->handle($customer);
        $password = $this->newPassword();
        $user->update(['password' => $password]);
        $customer->update(['account_password' => Crypt::encryptString($password)]);

        return $password;
    }

    public function newPassword(): string
    {
        return Str::password(10, letters: true, numbers: true, symbols: false, spaces: false);
    }

    public static function readPassword(Customer $customer): ?string
    {
        $raw = $customer->getRawOriginal('account_password');
        if (! $raw) {
            return null;
        }
        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
