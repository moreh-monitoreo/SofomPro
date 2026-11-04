<?php

namespace App\Actions\Loans;

use App\Mail\AccountInvitation;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CreateCustomerAccountAction
{
    /**
     * Crea (o reutiliza) la cuenta de usuario del cliente y la vincula con su perfil.
     * La cuenta nace con una contraseña aleatoria que nadie conoce; el cliente la define con la invitación.
     */
    public function handle(Customer $customer): User
    {
        $user = ($customer->user_id ? User::find($customer->user_id) : null)
            ?? User::where('email', $customer->email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $customer->full_name,
                'email' => $customer->email,
                'password' => Str::random(40),
            ]);
        }

        if (! $user->hasRole('Customer')) {
            $user->assignRole('Customer');
        }

        $customer->update(['user_id' => $user->id]);

        return $user;
    }

    /** Envía al cliente el enlace (con caducidad) para crear su contraseña. */
    public function invite(Customer $customer): User
    {
        $user = $this->handle($customer);

        $token = Password::broker()->createToken($user);
        $url = route('password.reset', ['token' => $token, 'email' => $user->email]);
        $hours = (int) round(config('auth.passwords.'.config('auth.defaults.passwords').'.expire') / 60);

        Mail::to($user->email)->send(new AccountInvitation($user->name, $url, $hours));

        return $user;
    }
}
