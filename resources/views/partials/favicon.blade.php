{{--
    Icon tags for the document head.

    Shared by every theme and by the admin panel, so a site that uploads its
    own favicon changes one setting and not five layouts. When nothing has been
    uploaded the shipped Radius icons are served instead, which is why a fresh
    install already looks finished in a browser tab.

    ORDER MATTERS. Browsers disagree about media queries on icon links: Firefox
    and Safari honour them, Chrome ignores them and takes the last icon it was
    given. So the dark-scheme (white) icon is declared first and the dark-ink
    pair last - a browser that understands the queries picks correctly, and one
    that does not falls through to the dark-ink icon, which is the safe answer
    on the pale tab bar most people are looking at.

    The generated favicon.svg is deliberately not linked. RealFaviconGenerator
    produced it by wrapping a base64 PNG in an <svg><image> tag, so it is a
    250 KB raster carrying exactly the pixels favicon-96x96.png carries in 6 KB,
    with none of the sharpness a real vector would bring. The files are still in
    public/favicon/ - link them here if they are ever re-exported as true paths.

    An uploaded favicon replaces all of this. The other sizes stay as shipped:
    there is one upload field, and re-encoding an image into five icons on every
    page load to fill the rest would cost more than it is worth.
--}}
@php($uploadedFavicon = filled(setting('site_favicon')))

@if ($uploadedFavicon)
    <link rel="icon" href="{{ site_favicon_url() }}" sizes="any">
@else
    <link rel="icon" type="image/png" sizes="96x96"
          href="{{ brand_asset('favicon_png_light') }}" media="(prefers-color-scheme: dark)">
    <link rel="icon" type="image/png" sizes="96x96"
          href="{{ brand_asset('favicon_png') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ brand_asset('favicon') }}" sizes="any">
@endif

<link rel="apple-touch-icon" sizes="180x180" href="{{ brand_asset('apple_touch_icon') }}">
<link rel="manifest" href="{{ brand_asset('manifest') }}">
