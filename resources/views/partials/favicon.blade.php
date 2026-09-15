{{--
    Icon tags for the document head.

    Shared by every theme and by the admin panel, so a site that uploads its
    own favicon changes one setting and not five layouts. When nothing has been
    uploaded the shipped Radius icons are served instead, which is why a fresh
    install already looks finished in a browser tab.

    An uploaded favicon replaces the .ico only. The SVG, the Apple touch icon
    and the manifest icons stay as shipped: there is one upload field, and
    guessing the other four sizes from it would mean re-encoding an image on
    every page load.
--}}
@php($uploadedFavicon = filled(setting('site_favicon')))

<link rel="icon" href="{{ site_favicon_url() }}" sizes="any">

@unless ($uploadedFavicon)
    <link rel="icon" type="image/svg+xml" href="{{ brand_asset('favicon_svg') }}">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ brand_asset('favicon_png') }}">
@endunless

<link rel="apple-touch-icon" sizes="180x180" href="{{ brand_asset('apple_touch_icon') }}">
<link rel="manifest" href="{{ brand_asset('manifest') }}">
