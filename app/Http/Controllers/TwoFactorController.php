<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureTwoFactor;
use App\Services\TwoFactorAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TwoFactorController extends Controller
{
    public function setup(Request $request, TwoFactorAuth $twoFactor)
    {
        $user = $request->user();
        abort_unless(TwoFactorAuth::appliesTo($user), 404);

        if ($user->two_factor_confirmed_at) {
            return redirect()->route('dashboard');
        }

        if (! $user->two_factor_secret) {
            $user->forceFill(['two_factor_secret' => $twoFactor->newSecret()])->save();
        }

        $secret = $user->two_factor_secret;

        return Inertia::render('Auth/TwoFactorSetup', [
            'qr' => $twoFactor->qrSvg($user, $secret),
            'secret' => trim(chunk_split($secret, 4, ' ')),
        ]);
    }

    public function confirm(Request $request, TwoFactorAuth $twoFactor)
    {
        $user = $request->user();
        abort_unless(TwoFactorAuth::appliesTo($user) && $user->two_factor_secret && ! $user->two_factor_confirmed_at, 404);

        $request->validate(['code' => 'required|string'], ['code.required' => 'Escribe el código de 6 dígitos.']);

        if (! $twoFactor->verify($user->two_factor_secret, $request->code, $user->id)) {
            throw ValidationException::withMessages(['code' => 'El código no es correcto. Revisa que el reloj de tu teléfono esté en hora e inténtalo de nuevo.']);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $codes = $twoFactor->generateRecoveryCodes($user);

        $request->session()->put(EnsureTwoFactor::SESSION_KEY, now());

        return redirect()->route('two-factor.codes')->with('recovery_codes', $codes);
    }

    public function challenge(Request $request)
    {
        $user = $request->user();
        abort_unless(TwoFactorAuth::appliesTo($user), 404);

        if (! $user->two_factor_confirmed_at) {
            return redirect()->route('two-factor.setup');
        }
        if ($request->session()->has(EnsureTwoFactor::SESSION_KEY)) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function verify(Request $request, TwoFactorAuth $twoFactor)
    {
        $user = $request->user();
        abort_unless(TwoFactorAuth::appliesTo($user) && $user->two_factor_confirmed_at, 404);

        $request->validate([
            'code' => 'nullable|string|required_without:recovery_code',
            'recovery_code' => 'nullable|string|required_without:code',
        ], [
            'code.required_without' => 'Escribe el código de tu aplicación o un código de recuperación.',
        ]);

        $valid = $request->filled('code')
            ? $twoFactor->verify($user->two_factor_secret, $request->code, $user->id)
            : $twoFactor->useRecoveryCode($user, $request->recovery_code);

        if (! $valid) {
            throw ValidationException::withMessages([
                $request->filled('code') ? 'code' : 'recovery_code' => 'El código no es válido o ya se usó.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put(EnsureTwoFactor::SESSION_KEY, now());

        return redirect()->intended(route('dashboard'));
    }

    /** Muestra los códigos de recuperación una sola vez, justo después de activarlos o regenerarlos. */
    public function codes(Request $request)
    {
        $codes = $request->session()->get('recovery_codes');
        if (! $codes) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/TwoFactorRecoveryCodes', ['codes' => $codes]);
    }

    public function regenerate(Request $request, TwoFactorAuth $twoFactor)
    {
        $user = $request->user();
        abort_unless($user->two_factor_confirmed_at, 404);

        $request->validate(['password' => 'required|string']);

        if (! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['password' => 'La contraseña no es correcta.']);
        }

        return redirect()->route('two-factor.codes')->with('recovery_codes', $twoFactor->generateRecoveryCodes($user));
    }
}
