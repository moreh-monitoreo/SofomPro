<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/** Contraseñas de un solo uso (TOTP, compatibles con Google/Microsoft Authenticator, Authy, etc.) y códigos de recuperación. */
class TwoFactorAuth
{
    public const RECOVERY_CODES = 8;

    /** Ventana de tolerancia: un código es válido 30 s antes o después por diferencias de reloj. */
    private const WINDOW = 1;

    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    public static function required(): bool
    {
        return (bool) config('promas.two_factor_required', true);
    }

    public static function appliesTo(User $user): bool
    {
        return self::required() && $user->hasAnyRole(['Administrator', 'Advisor']);
    }

    public function newSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function provisioningUri(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(config('promas.name', config('app.name')), $user->email, $secret);
    }

    public function qrSvg(User $user, string $secret): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd));

        return $writer->writeString($this->provisioningUri($user, $secret));
    }

    /** Valida el código del autenticador. Un mismo código no se acepta dos veces (evita repetirlo si lo ven). */
    public function verify(string $secret, string $code, int $userId): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timestamp = $this->google2fa->verifyKeyNewer($secret, $code, null, self::WINDOW);
        if ($timestamp === false) {
            return false;
        }

        return Cache::add("two-factor:used:{$userId}:{$code}", true, 120);
    }

    /** @return array<int, string> códigos en claro; solo se muestran una vez y se guardan con hash */
    public function generateRecoveryCodes(User $user): array
    {
        $codes = collect(range(1, self::RECOVERY_CODES))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();

        $user->forceFill(['two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $codes)])->save();

        return $codes;
    }

    /** Consume un código de recuperación válido. Devuelve false si no coincide con ninguno. */
    public function useRecoveryCode(User $user, string $input): bool
    {
        $input = Str::lower(trim($input));
        $stored = $user->two_factor_recovery_codes ?? [];

        foreach ($stored as $index => $hash) {
            if (Hash::check($input, $hash)) {
                unset($stored[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($stored)])->save();

                return true;
            }
        }

        return false;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }
}
