{{--
    The site logo, in whichever ink the surface behind it calls for.

    Usage:
        <x-site-logo class="h-10 w-auto" />           follows the site colour scheme
        <x-site-logo on="dark" class="h-8 w-auto" />  a surface that is always dark
        <x-site-logo on="light" small />              a surface that is always light

    'on' names the BACKGROUND, not the artwork, because that is the thing
    whoever places the tag can actually see. A dark background gets the
    light-ink file.

    on="auto" is the honest answer when the background follows the visitor: with
    Color scheme set to Always light or Always dark the choice is made here, and
    on the default Follow visitor system setting it is handed to CSS through a
    picture element. That last case cannot be decided in PHP - the server never
    learns what the browser prefers.
--}}
@props([
    'on' => 'auto',
    'small' => false,
    'alt' => null,
])

@php
    $alt = $alt ?? setting('site_name', config('app.name'));

    $darkInk = site_logo_url((bool) $small);        // for light backgrounds
    $lightInk = site_logo_light_url((bool) $small); // for dark backgrounds

    $scheme = $on === 'auto' ? site_color_scheme() : $on;

    // A site that uploaded one logo and no light variant gets the same file for
    // both schemes; a picture element that switches between identical images is
    // only markup for the browser to pick apart, so it collapses to a plain img.
    $needsSwitch = $scheme === 'system' && $darkInk !== $lightInk;
@endphp

@if ($needsSwitch)
    <picture>
        <source srcset="{{ $lightInk }}" media="(prefers-color-scheme: dark)">
        <img src="{{ $darkInk }}" alt="{{ $alt }}" {{ $attributes }}>
    </picture>
@else
    <img src="{{ $scheme === 'dark' ? $lightInk : $darkInk }}" alt="{{ $alt }}" {{ $attributes }}>
@endif
