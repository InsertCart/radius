<?php

namespace App\Http\Middleware;

use App\Cms\Support\Recaptcha;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a public form submission that reCAPTCHA scores as a bot. Does
 * nothing while reCAPTCHA is switched off in Settings → Advanced.
 */
class VerifyRecaptcha
{
    public function __construct(private Recaptcha $recaptcha) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->recaptcha->enabled()) {
            return $next($request);
        }

        if ($this->recaptcha->passes($request->input(Recaptcha::FIELD), $request->ip())) {
            return $next($request);
        }

        $message = 'We could not confirm you are not a robot. Please reload the page and try again.';

        if ($request->expectsJson()) {
            abort(422, $message);
        }

        return back()
            ->withInput($request->except(['password', 'password_confirmation', Recaptcha::FIELD]))
            ->with('error', $message)
            ->withErrors(['recaptcha' => $message]);
    }
}
