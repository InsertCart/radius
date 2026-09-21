<?php

namespace App\Http\Middleware;

use App\Cms\Builder\StyleRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fills the @builderStyles marker in the head once the whole page has rendered.
 *
 * The head is printed before the header and footer regions render, so the
 * stylesheet cannot be written where the directive stands: by then only the
 * page's own layout has registered its CSS. Waiting for the finished HTML
 * means every region's styles and fonts are included.
 */
class InjectBuilderStyles
{
    public function __construct(private StyleRegistry $styles) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof IlluminateResponse) {
            return $response;
        }

        $html = $response->getContent();

        if (! is_string($html) || ! str_contains($html, StyleRegistry::MARKER)) {
            return $response;
        }

        $pos = strpos($html, StyleRegistry::MARKER);
        $response->setContent(
            substr($html, 0, $pos).$this->styles->render().substr($html, $pos + strlen(StyleRegistry::MARKER))
        );

        return $response;
    }
}
