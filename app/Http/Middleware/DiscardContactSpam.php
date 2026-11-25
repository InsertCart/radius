<?php

namespace App\Http\Middleware;

use App\Cms\Forms\ContactChallenge;
use App\Cms\Forms\ContactFormSchema;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DiscardContactSpam
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->filled('website') || $request->has('_contact_copy')
            || (ContactChallenge::enabled() && ! $request->has('_contact_challenge'))) {
            return ContactFormSchema::receipt($request, ContactFormSchema::forRequest($request));
        }

        return $next($request);
    }
}
