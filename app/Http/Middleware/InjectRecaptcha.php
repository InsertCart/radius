<?php

namespace App\Http\Middleware;

use App\Cms\Support\Recaptcha;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the reCAPTCHA v3 script to any page holding a protected form.
 *
 * Done on the rendered HTML rather than in each template so that every theme -
 * including ones bought elsewhere - and every builder widget is covered
 * without a single template change. Pages with no such form load nothing from
 * Google.
 */
class InjectRecaptcha
{
    public function __construct(private Recaptcha $recaptcha) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET')
            || ! $response instanceof IlluminateResponse
            || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            || ! $this->recaptcha->enabled()) {
            return $response;
        }

        $html = $response->getContent();
        $actions = $this->protectedActions();

        if (! is_string($html) || $actions === [] || ! str_contains($html, '</body>')) {
            return $response;
        }

        $found = array_filter($actions, fn ($url) => str_contains($html, 'action="'.e($url).'"'));

        if ($found === []) {
            return $response;
        }

        $pos = strrpos($html, '</body>');
        $response->setContent(substr($html, 0, $pos).$this->script(array_values($found)).substr($html, $pos));

        return $response;
    }

    /** @return array<string, string> action name => form URL */
    private function protectedActions(): array
    {
        $urls = [];

        foreach (Recaptcha::PROTECTED_ROUTES as $name) {
            if (Route::has($name)) {
                $urls[str_replace('.', '_', $name)] = route($name);
            }
        }

        return $urls;
    }

    private function script(array $urls): string
    {
        $key = e($this->recaptcha->siteKey());
        $config = json_encode([
            'key' => $this->recaptcha->siteKey(),
            'field' => Recaptcha::FIELD,
            // Compared by path: the browser resolves form.action against the
            // address in the bar, which need not match APP_URL exactly.
            'paths' => array_map(fn ($url) => parse_url($url, PHP_URL_PATH) ?: '/', $urls),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);

        // The token is fetched at submit time, not on page load: tokens expire
        // after two minutes, and people take longer than that over a message.
        return <<<HTML
<script src="https://www.google.com/recaptcha/api.js?render={$key}" async defer></script>
<script>
(function (c) {
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || c.paths.indexOf(new URL(form.action, location.href).pathname) === -1 || form.dataset.recaptchaDone) return;
        if (!window.grecaptcha) return;
        event.preventDefault();
        grecaptcha.ready(function () {
            grecaptcha.execute(c.key, { action: 'submit' }).then(function (token) {
                var input = form.querySelector('input[name="' + c.field + '"]');
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = c.field;
                    form.appendChild(input);
                }
                input.value = token;
                form.dataset.recaptchaDone = '1';
                form.submit();
            });
        });
    }, true);
})({$config});
</script>

HTML;
    }
}
