<?php

namespace App\Cms\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * reCAPTCHA v3 for the public forms: contact, newsletter and registration.
 *
 * v3 has no checkbox. Google scores each submission from 0 (bot) to 1 (human)
 * and the form is refused below the threshold. The browser half is injected by
 * InjectRecaptcha, so a theme needs no changes to be protected.
 */
class Recaptcha
{
    /** Routes whose POST must carry a passing token. */
    public const PROTECTED_ROUTES = ['contact.submit', 'newsletter.subscribe', 'register.store'];

    public const FIELD = 'g-recaptcha-response';

    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    private const MIN_SCORE = 0.5;

    /** On only when switched on AND both keys are filled in. */
    public function enabled(): bool
    {
        return (bool) setting('recaptcha_enabled', false)
            && filled($this->siteKey())
            && filled(setting('recaptcha_secret_key'));
    }

    public function siteKey(): ?string
    {
        return setting('recaptcha_site_key') ?: null;
    }

    public function passes(?string $token, ?string $ip = null): bool
    {
        if (blank($token)) {
            return false;
        }

        try {
            $result = Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => setting('recaptcha_secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ])->json();
        } catch (ConnectionException $e) {
            // Google unreachable from this server. Refusing every sign-up and
            // enquiry until it comes back would cost more than letting a few
            // through; the rate limits and honeypots still apply.
            Log::warning('reCAPTCHA verification skipped: '.$e->getMessage());

            return true;
        }

        if (! ($result['success'] ?? false)) {
            // Usually a wrong secret key or a site key registered for another
            // domain - worth surfacing, since every visitor is being refused.
            Log::notice('reCAPTCHA rejected a token.', ['errors' => $result['error-codes'] ?? []]);

            return false;
        }

        return (float) ($result['score'] ?? 0) >= self::MIN_SCORE;
    }
}
