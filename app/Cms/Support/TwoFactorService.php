<?php

namespace App\Cms\Support;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP two-factor authentication, compatible with Google Authenticator, Authy,
 * 1Password and any other RFC 6238 app.
 *
 * The secret and recovery codes are encrypted on the User model, so this class
 * only deals with generating, confirming and consuming them.
 */
class TwoFactorService
{
    private Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA;
    }

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    /**
     * Stage a new secret on the user without switching 2FA on. It only becomes
     * active once confirm() succeeds, so a mis-scanned QR code cannot lock
     * someone out of their own account.
     */
    public function beginEnrolment(User $user): string
    {
        $secret = $this->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $this->generateRecoveryCodes(),
            'two_factor_confirmed_at' => null,
        ])->save();

        return $secret;
    }

    /** Verifies the first code from the app and switches 2FA on. */
    public function confirm(User $user, string $code): bool
    {
        if (blank($user->two_factor_secret) || ! $this->verify($user, $code)) {
            return false;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return true;
    }

    /**
     * Check a six-digit code against the user's secret, allowing a small
     * window either side for clock drift between the phone and the server.
     */
    public function verify(User $user, string $code): bool
    {
        if (blank($user->two_factor_secret)) {
            return false;
        }

        $code = preg_replace('/\s+/', '', $code);

        try {
            return (bool) $this->engine->verifyKey(
                $user->two_factor_secret,
                $code,
                (int) config('cms.security.two_factor_window', 1)
            );
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Consume a recovery code. Each one works exactly once, and is removed
     * from the user's list on use.
     */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $code = trim($code);
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $stored) {
            // hash_equals keeps the comparison constant-time.
            if (hash_equals($stored, $code)) {
                unset($codes[$index]);

                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    /** @return string[] */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, (int) config('cms.security.recovery_code_count', 8)))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->generateRecoveryCodes();

        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return $codes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /** The otpauth:// URI encoded in the QR code. */
    public function provisioningUri(User $user): string
    {
        return $this->engine->getQRCodeUrl(
            $this->issuer(),
            $user->email,
            $user->two_factor_secret
        );
    }

    /**
     * An inline SVG QR code. SVG avoids needing imagick, and being inline
     * means the secret never travels to a third-party chart service.
     */
    public function qrCodeSvg(User $user, int $size = 220): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd
        ));

        $svg = $writer->writeString($this->provisioningUri($user));

        // Drop the XML prolog so the markup can be embedded directly in a page.
        return preg_replace('/^<\?xml.*?\?>\s*/', '', $svg);
    }

    /** The secret in the grouped form people type in by hand. */
    public function formattedSecret(User $user): string
    {
        return trim(chunk_split((string) $user->two_factor_secret, 4, ' '));
    }

    private function issuer(): string
    {
        // Authenticator apps show this as the account label, and a space or a
        // colon in it breaks the otpauth URI.
        return preg_replace('/[^\w\-.]/', '', (string) setting('site_name', config('app.name'))) ?: 'CMS';
    }
}
