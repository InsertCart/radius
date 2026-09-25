<?php

/*
|--------------------------------------------------------------------------
| Embeds
|--------------------------------------------------------------------------
| A link pasted on a line of its own becomes the video, post or track it
| points at. Which providers are recognised, and what each one turns into, is
| declared in App\Cms\Embeds\EmbedRegistry - a provider is worked out from the
| address itself, with no call to anybody's API.
|
| Whether embeds run at all, whether privacy-preserving hosts are used and
| which providers are allowed are settings, not config: they belong to the site
| owner and live under Settings -> Embeds.
|
| A provider of your own goes in a service provider's boot method:
|
|     EmbedRegistry::extend('wistia', [
|         'label' => 'Wistia',
|         'hosts' => ['wistia.com', 'wi.st'],
|         'frames' => ['fast.wistia.net'],
|         'resolve' => fn (EmbedSource $source) => ...,
|     ]);
*/

return [

    /*
     * Extra hosts an author may point a hand-written <iframe> at.
     *
     * Every registered provider's own host is allowed already, so this is only
     * for embedding something the CMS does not recognise - an internal video
     * platform, a booking widget, a status page. The host is matched exactly,
     * so list "www.example.com" if that is what the src says.
     *
     * Anything not listed here or claimed by a provider is stripped when the
     * content is saved: an iframe is a page running inside your page, and an
     * editor account is not a reason to trust an arbitrary one.
     */
    'frame_hosts' => [],

];
