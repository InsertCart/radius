<?php

namespace App\Cms\Embeds;

use Closure;

/**
 * Every link shape the CMS knows how to turn into an embed.
 *
 * Deliberately offline: a provider is recognised from the address itself and
 * the embed URL is worked out arithmetically, with no oEmbed discovery call.
 * That costs a little accuracy - a provider that changes its URL scheme has to
 * be updated here - and buys a great deal: pages do not wait on somebody
 * else's API to render, a slow or blocked provider cannot slow the site down,
 * there is no cache to go stale or warm, and the whole thing works on a host
 * with no outbound network access at all.
 *
 * Providers declare the hosts they answer for and the frame hosts they emit.
 * The second list is what HtmlSanitizer allows through as an iframe, so adding
 * a provider here is enough - the sanitiser is never edited separately, and the
 * two lists cannot drift apart.
 *
 * A site or module can add its own with EmbedRegistry::extend().
 */
class EmbedRegistry
{
    /** Allowed on every iframe: the permissions a player legitimately needs. */
    public const ALLOW = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';

    /** @var array<string, array<string, mixed>> */
    private static array $extra = [];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $resolved = null;

    /**
     * Register a provider, or replace a bundled one of the same name.
     *
     * @param  array{label: string, hosts?: string[], host_pattern?: string, any_host?: bool, frames?: string[], resolve: Closure}  $definition
     */
    public static function extend(string $name, array $definition): void
    {
        self::$extra[$name] = $definition;
        self::$resolved = null;
    }

    /** @return array<string, array<string, mixed>> */
    public static function providers(): array
    {
        return self::$resolved ??= array_merge(self::bundled(), self::$extra);
    }

    /** Provider names and labels, for the settings screen. */
    public static function labels(): array
    {
        return array_map(fn (array $provider) => (string) $provider['label'], self::providers());
    }

    /**
     * Hosts an iframe may point at, gathered from every provider.
     *
     * @return string[]
     */
    public static function frameHosts(): array
    {
        $hosts = [];

        foreach (self::providers() as $provider) {
            foreach ($provider['frames'] ?? [] as $host) {
                $hosts[] = strtolower($host);
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * The embed a link becomes, or null when nothing recognises it.
     *
     * @param  string[]|null  $only  Provider names to consider; null means all.
     */
    public function resolve(string $url, ?array $only = null, bool $privacy = true): ?Embed
    {
        $source = EmbedSource::from($url);

        if (! $source) {
            return null;
        }

        foreach (self::providers() as $name => $provider) {
            if ($only !== null && ! in_array($name, $only, true)) {
                continue;
            }

            if (! $this->handles($provider, $source)) {
                continue;
            }

            $parts = ($provider['resolve'])($source, $privacy);

            if (! is_array($parts) || $parts === []) {
                continue;
            }

            return Embed::make($name, (string) $provider['label'], $source->url, $parts);
        }

        return null;
    }

    private function handles(array $provider, EmbedSource $source): bool
    {
        if (! empty($provider['any_host'])) {
            return true;
        }

        foreach ($provider['hosts'] ?? [] as $host) {
            if ($source->host === EmbedSource::normaliseHost($host)) {
                return true;
            }
        }

        return isset($provider['host_pattern'])
            && preg_match($provider['host_pattern'], $source->host) === 1;
    }

    // Bundled providers ----------------------------------------------------

    /** @return array<string, array<string, mixed>> */
    private static function bundled(): array
    {
        return [
            'youtube' => [
                'label' => 'YouTube',
                'hosts' => ['youtube.com', 'youtu.be', 'youtube-nocookie.com', 'music.youtube.com'],
                'frames' => ['www.youtube.com', 'www.youtube-nocookie.com'],
                'resolve' => self::youtube(),
            ],

            'vimeo' => [
                'label' => 'Vimeo',
                'hosts' => ['vimeo.com', 'player.vimeo.com'],
                'frames' => ['player.vimeo.com'],
                'resolve' => self::vimeo(),
            ],

            'dailymotion' => [
                'label' => 'Dailymotion',
                'hosts' => ['dailymotion.com', 'dai.ly', 'geo.dailymotion.com'],
                'frames' => ['geo.dailymotion.com'],
                'resolve' => self::dailymotion(),
            ],

            'twitch' => [
                'label' => 'Twitch',
                'hosts' => ['twitch.tv', 'clips.twitch.tv'],
                'frames' => ['player.twitch.tv', 'clips.twitch.tv'],
                'resolve' => self::twitch(),
            ],

            'loom' => [
                'label' => 'Loom',
                'hosts' => ['loom.com'],
                'frames' => ['www.loom.com'],
                'resolve' => self::loom(),
            ],

            'spotify' => [
                'label' => 'Spotify',
                'hosts' => ['open.spotify.com', 'spotify.com'],
                'frames' => ['open.spotify.com'],
                'resolve' => self::spotify(),
            ],

            'soundcloud' => [
                'label' => 'SoundCloud',
                'hosts' => ['soundcloud.com'],
                'frames' => ['w.soundcloud.com'],
                'resolve' => self::soundcloud(),
            ],

            'instagram' => [
                'label' => 'Instagram',
                'hosts' => ['instagram.com', 'instagr.am'],
                'frames' => ['www.instagram.com'],
                'resolve' => self::instagram(),
            ],

            'tiktok' => [
                'label' => 'TikTok',
                'hosts' => ['tiktok.com'],
                'frames' => ['www.tiktok.com'],
                'resolve' => self::tiktok(),
            ],

            'x' => [
                'label' => 'X (Twitter)',
                'hosts' => ['twitter.com', 'x.com'],
                'frames' => ['platform.twitter.com'],
                'resolve' => self::x(),
            ],

            'facebook' => [
                'label' => 'Facebook',
                'hosts' => ['facebook.com', 'fb.watch'],
                'frames' => ['www.facebook.com'],
                'resolve' => self::facebook(),
            ],

            'linkedin' => [
                'label' => 'LinkedIn',
                'hosts' => ['linkedin.com'],
                'frames' => ['www.linkedin.com'],
                'resolve' => self::linkedin(),
            ],

            'reddit' => [
                'label' => 'Reddit',
                'hosts' => ['reddit.com', 'old.reddit.com', 'new.reddit.com'],
                'frames' => ['embed.reddit.com'],
                'resolve' => self::reddit(),
            ],

            'pinterest' => [
                'label' => 'Pinterest',
                'host_pattern' => '#^(?:[a-z]{2}\.)?pinterest\.[a-z][a-z.]+$#',
                'resolve' => self::pinterest(),
            ],

            'gist' => [
                'label' => 'GitHub Gist',
                'hosts' => ['gist.github.com'],
                'resolve' => self::gist(),
            ],

            'codepen' => [
                'label' => 'CodePen',
                'hosts' => ['codepen.io'],
                'frames' => ['codepen.io'],
                'resolve' => self::codepen(),
            ],

            'figma' => [
                'label' => 'Figma',
                'hosts' => ['figma.com'],
                'frames' => ['www.figma.com', 'embed.figma.com'],
                'resolve' => self::figma(),
            ],

            'google_docs' => [
                'label' => 'Google Docs, Sheets, Slides and Forms',
                'hosts' => ['docs.google.com'],
                'frames' => ['docs.google.com'],
                'resolve' => self::googleDocs(),
            ],

            'google_maps' => [
                'label' => 'Google Maps',
                'host_pattern' => '#^(?:maps\.)?google\.[a-z][a-z.]+$#',
                'frames' => ['maps.google.com', 'www.google.com'],
                'resolve' => self::googleMaps(),
            ],

            'openstreetmap' => [
                'label' => 'OpenStreetMap',
                'hosts' => ['openstreetmap.org', 'osm.org'],
                'frames' => ['www.openstreetmap.org'],
                'resolve' => self::openStreetMap(),
            ],

            // Last, because it matches on the file extension rather than the
            // host: every other provider gets first refusal on the link.
            'media_file' => [
                'label' => 'Video or audio file',
                'any_host' => true,
                'resolve' => self::mediaFile(),
            ],
        ];
    }

    // Resolvers ------------------------------------------------------------

    private static function youtube(): Closure
    {
        return function (EmbedSource $source, bool $privacy): ?array {
            $host = $privacy ? 'www.youtube-nocookie.com' : 'www.youtube.com';
            $ratio = '16-9';
            $maxWidth = 0;
            $params = ['rel' => 0];

            // A playlist link has no single video to point at, so the series
            // player is used instead.
            if ($source->path === '/playlist') {
                $list = $source->query('list', '#^[A-Za-z0-9_-]{2,}$#');

                return $list ? [
                    'src' => "https://{$host}/embed/videoseries?".http_build_query(['list' => $list]),
                    'title' => 'YouTube playlist',
                ] : null;
            }

            if ($source->host === 'youtu.be') {
                $id = $source->segment(0, '#^[A-Za-z0-9_-]{11}$#');
            } elseif ($match = $source->pathMatches('#^/(embed|v|e|shorts|live)/([A-Za-z0-9_-]{11})#')) {
                $id = $match[2];

                if ($match[1] === 'shorts') {
                    // A short is filmed for a phone; given the full column it
                    // would be taller than the screen.
                    $ratio = '9-16';
                    $maxWidth = 400;
                }
            } else {
                $id = $source->query('v', '#^[A-Za-z0-9_-]{11}$#');
            }

            if (! $id) {
                return null;
            }

            if ($start = self::seconds((string) ($source->query('t') ?? $source->query('start') ?? ''))) {
                $params['start'] = $start;
            }

            // A video opened from inside a playlist keeps the playlist.
            if ($list = $source->query('list', '#^[A-Za-z0-9_-]{2,}$#')) {
                $params['list'] = $list;
            }

            return [
                'src' => "https://{$host}/embed/{$id}?".http_build_query($params),
                'ratio' => $ratio,
                'max_width' => $maxWidth,
                'title' => 'YouTube video',
            ];
        };
    }

    private static function vimeo(): Closure
    {
        return function (EmbedSource $source): ?array {
            if (! $match = $source->pathMatches('#^/(?:video/)?(\d{6,})(?:/([A-Za-z0-9]+))?#')) {
                return null;
            }

            // dnt asks Vimeo not to track the visitor; an unlisted video needs
            // the hash from the share link to play at all.
            $params = ['dnt' => 1];

            if (isset($match[2])) {
                $params['h'] = $match[2];
            } elseif ($hash = $source->query('h', '#^[A-Za-z0-9]+$#')) {
                $params['h'] = $hash;
            }

            return [
                'src' => 'https://player.vimeo.com/video/'.$match[1].'?'.http_build_query($params),
                'title' => 'Vimeo video',
            ];
        };
    }

    private static function dailymotion(): Closure
    {
        return function (EmbedSource $source): ?array {
            $id = $source->host === 'dai.ly'
                ? $source->segment(0, '#^[A-Za-z0-9]{5,}$#')
                : ($source->pathMatches('#^/video/([A-Za-z0-9]{5,})#')[1] ?? null);

            return $id ? [
                'src' => 'https://geo.dailymotion.com/player.html?video='.$id,
                'title' => 'Dailymotion video',
            ] : null;
        };
    }

    private static function twitch(): Closure
    {
        return function (EmbedSource $source): ?array {
            // Twitch refuses to play unless the embedding domain is named, so
            // this is the one provider whose embed URL depends on the site.
            $parent = self::siteHost();

            if ($source->host === 'clips.twitch.tv') {
                $clip = $source->segment(0, '#^[A-Za-z0-9_-]{4,}$#');

                return $clip ? [
                    'src' => 'https://clips.twitch.tv/embed?'.http_build_query(['clip' => $clip, 'parent' => $parent, 'autoplay' => 'false']),
                    'title' => 'Twitch clip',
                ] : null;
            }

            if ($match = $source->pathMatches('#^/videos/(\d+)$#')) {
                return [
                    'src' => 'https://player.twitch.tv/?'.http_build_query(['video' => $match[1], 'parent' => $parent, 'autoplay' => 'false']),
                    'title' => 'Twitch video',
                ];
            }

            if ($match = $source->pathMatches('#^/([A-Za-z0-9_]{3,25})/clip/([A-Za-z0-9_-]{4,})#')) {
                return [
                    'src' => 'https://clips.twitch.tv/embed?'.http_build_query(['clip' => $match[2], 'parent' => $parent, 'autoplay' => 'false']),
                    'title' => 'Twitch clip',
                ];
            }

            if ($match = $source->pathMatches('#^/([A-Za-z0-9_]{3,25})$#')) {
                return [
                    'src' => 'https://player.twitch.tv/?'.http_build_query(['channel' => $match[1], 'parent' => $parent, 'autoplay' => 'false']),
                    'title' => 'Twitch channel',
                ];
            }

            return null;
        };
    }

    private static function loom(): Closure
    {
        return function (EmbedSource $source): ?array {
            $match = $source->pathMatches('#^/(?:share|embed)/([0-9a-f]{16,})#');

            return $match ? [
                'src' => 'https://www.loom.com/embed/'.$match[1],
                'title' => 'Loom recording',
            ] : null;
        };
    }

    private static function spotify(): Closure
    {
        return function (EmbedSource $source): ?array {
            // Share links from the app carry a locale segment: /intl-de/track/...
            $match = $source->pathMatches('#^/(?:intl-[a-z-]+/)?(track|album|playlist|episode|show|artist)/([A-Za-z0-9]{16,})#');

            if (! $match) {
                return null;
            }

            return [
                'kind' => Embed::CARD,
                'src' => 'https://open.spotify.com/embed/'.$match[1].'/'.$match[2],
                // A single track is one row; a list needs room to scroll.
                'height' => in_array($match[1], ['track', 'episode'], true) ? 152 : 352,
                'title' => 'Spotify '.$match[1],
            ];
        };
    }

    private static function soundcloud(): Closure
    {
        return function (EmbedSource $source): ?array {
            // The player needs a full track address; a bare profile link has
            // nothing to play.
            if (! $source->pathMatches('#^/[\w-]+/[\w-]#')) {
                return null;
            }

            return [
                'kind' => Embed::CARD,
                'src' => 'https://w.soundcloud.com/player/?'.http_build_query([
                    'url' => $source->url,
                    'color' => '#ff5500',
                    'auto_play' => 'false',
                    'hide_related' => 'true',
                    'show_comments' => 'false',
                    'show_teaser' => 'false',
                    'visual' => 'false',
                ]),
                'height' => str_contains($source->path, '/sets/') ? 450 : 166,
                'title' => 'SoundCloud player',
            ];
        };
    }

    private static function instagram(): Closure
    {
        return function (EmbedSource $source): ?array {
            // The username is optional in the path: /p/ABC and /alice/p/ABC
            // are the same post.
            if (! $match = $source->pathMatches('#/(p|reel|reels|tv)/([A-Za-z0-9_-]{5,})#')) {
                return null;
            }

            $type = $match[1] === 'reels' ? 'reel' : $match[1];

            return [
                'kind' => Embed::CARD,
                'src' => "https://www.instagram.com/{$type}/{$match[2]}/embed/captioned/",
                // A reel is shot vertically, so its card is taller than a photo's.
                'height' => $type === 'p' ? 680 : 800,
                'max_width' => 540,
                'title' => 'Instagram post',
            ];
        };
    }

    private static function tiktok(): Closure
    {
        return function (EmbedSource $source): ?array {
            $id = $source->pathMatches('#/video/(\d{6,})#')[1]
                ?? $source->pathMatches('#^/embed/v2/(\d{6,})#')[1]
                ?? null;

            return $id ? [
                'kind' => Embed::CARD,
                'src' => 'https://www.tiktok.com/embed/v2/'.$id,
                'height' => 760,
                'max_width' => 340,
                'title' => 'TikTok video',
            ] : null;
        };
    }

    private static function x(): Closure
    {
        return function (EmbedSource $source): ?array {
            if (! $match = $source->pathMatches('#^/([A-Za-z0-9_]{1,20})/status(?:es)?/(\d+)#')) {
                return null;
            }

            // Rebuilt rather than passed through, so nothing from the original
            // query string - tracking parameters included - reaches the widget.
            $url = 'https://twitter.com/'.$match[1].'/status/'.$match[2];

            return [
                'kind' => Embed::SCRIPT,
                'markup' => '<blockquote class="twitter-tweet" data-dnt="true"><a href="'.e($url).'">'.e($url).'</a></blockquote>',
                'script' => 'https://platform.twitter.com/widgets.js',
                'max_width' => 550,
                'title' => 'Post on X',
            ];
        };
    }

    private static function facebook(): Closure
    {
        return function (EmbedSource $source): ?array {
            $isVideo = $source->host === 'fb.watch'
                || $source->path === '/watch'
                || (bool) $source->pathMatches('#/(videos|reel)/#');

            $plugin = $isVideo ? 'video' : 'post';

            return [
                'kind' => $isVideo ? Embed::RATIO : Embed::CARD,
                'src' => "https://www.facebook.com/plugins/{$plugin}.php?".http_build_query([
                    'href' => $source->url,
                    'show_text' => $isVideo ? 'false' : 'true',
                    'width' => 552,
                ]),
                'height' => 640,
                'max_width' => 552,
                'title' => $isVideo ? 'Facebook video' : 'Facebook post',
            ];
        };
    }

    private static function linkedin(): Closure
    {
        return function (EmbedSource $source): ?array {
            $urn = null;

            // A share link ends in the activity id:
            // /posts/alice_some-title-7180000000000000000-AbCd
            if ($match = $source->pathMatches('#/urn:li:(activity|share|ugcPost):(\d{15,})#')) {
                $urn = 'urn:li:'.$match[1].':'.$match[2];
            } elseif ($match = $source->pathMatches('#-(\d{15,})(?:-[A-Za-z0-9_-]+)?$#')) {
                $urn = 'urn:li:activity:'.$match[1];
            }

            return $urn ? [
                'kind' => Embed::CARD,
                'src' => 'https://www.linkedin.com/embed/feed/update/'.$urn,
                'height' => 620,
                'max_width' => 552,
                'title' => 'LinkedIn post',
            ] : null;
        };
    }

    private static function reddit(): Closure
    {
        return function (EmbedSource $source): ?array {
            if (! $match = $source->pathMatches('#^/r/([A-Za-z0-9_]{2,21})/comments/([A-Za-z0-9]{4,})#')) {
                return null;
            }

            $url = 'https://www.reddit.com/r/'.$match[1].'/comments/'.$match[2].'/';

            return [
                'kind' => Embed::SCRIPT,
                'markup' => '<blockquote class="reddit-embed-bq" data-embed-height="500"><a href="'.e($url).'">'.e($url).'</a></blockquote>',
                'script' => 'https://embed.reddit.com/widgets.js',
                'max_width' => 640,
                'title' => 'Reddit post',
            ];
        };
    }

    private static function pinterest(): Closure
    {
        return function (EmbedSource $source): ?array {
            if (! $match = $source->pathMatches('#^/pin/(\d{6,})#')) {
                return null;
            }

            $url = 'https://www.pinterest.com/pin/'.$match[1].'/';

            return [
                'kind' => Embed::SCRIPT,
                'markup' => '<a data-pin-do="embedPin" data-pin-width="medium" href="'.e($url).'">'.e($url).'</a>',
                'script' => 'https://assets.pinterest.com/js/pinit.js',
                'max_width' => 345,
                'title' => 'Pinterest pin',
            ];
        };
    }

    private static function gist(): Closure
    {
        return function (EmbedSource $source): ?array {
            // Gist has no iframe endpoint at all: the script tag is the embed.
            if (! $match = $source->pathMatches('#^/([A-Za-z0-9][A-Za-z0-9-]{0,38})/([0-9a-f]{20,})#')) {
                return null;
            }

            return [
                'kind' => Embed::SCRIPT,
                'markup' => '<script src="https://gist.github.com/'.$match[1].'/'.$match[2].'.js"></script>',
                'title' => 'GitHub gist',
            ];
        };
    }

    private static function codepen(): Closure
    {
        return function (EmbedSource $source): ?array {
            // A team pen carries an extra segment: /team/codepen/pen/PNaGbb
            if (! $match = $source->pathMatches('#^/(?:team/)?([\w-]+)/(?:pen|details|full|embed|pres)/([\w-]+)#')) {
                return null;
            }

            return [
                'kind' => Embed::CARD,
                'src' => 'https://codepen.io/'.$match[1].'/embed/'.$match[2].'?'.http_build_query([
                    'default-tab' => 'result',
                ]),
                'height' => 420,
                'title' => 'CodePen',
            ];
        };
    }

    private static function figma(): Closure
    {
        return function (EmbedSource $source): ?array {
            if (! $source->pathMatches('#^/(file|design|proto|board|slides)/#')) {
                return null;
            }

            return [
                'src' => 'https://www.figma.com/embed?'.http_build_query([
                    'embed_host' => 'radius',
                    'url' => $source->url,
                ]),
                'title' => 'Figma file',
            ];
        };
    }

    private static function googleDocs(): Closure
    {
        return function (EmbedSource $source): ?array {
            // A published form has its own path shape, with an extra segment.
            if ($match = $source->pathMatches('#^/forms/d/e/([\w-]{10,})#')) {
                return [
                    'kind' => Embed::CARD,
                    'src' => 'https://docs.google.com/forms/d/e/'.$match[1].'/viewform?embedded=true',
                    'height' => 780,
                    'title' => 'Google Form',
                ];
            }

            if (! $match = $source->pathMatches('#^/(document|spreadsheets|presentation|forms)/d/([\w-]{10,})#')) {
                return null;
            }

            // Slides are a deck, so they take the widescreen box. A document, a
            // sheet or a form is read down the page and gets a tall frame.
            if ($match[1] === 'presentation') {
                return [
                    'src' => 'https://docs.google.com/presentation/d/'.$match[2].'/embed',
                    'title' => 'Google Slides presentation',
                ];
            }

            if ($match[1] === 'forms') {
                return [
                    'kind' => Embed::CARD,
                    'src' => 'https://docs.google.com/forms/d/'.$match[2].'/viewform?embedded=true',
                    'height' => 780,
                    'title' => 'Google Form',
                ];
            }

            return [
                'kind' => Embed::CARD,
                'src' => 'https://docs.google.com/'.$match[1].'/d/'.$match[2].'/preview',
                'height' => 640,
                'title' => $match[1] === 'document' ? 'Google Doc' : 'Google Sheet',
            ];
        };
    }

    private static function googleMaps(): Closure
    {
        return function (EmbedSource $source): ?array {
            // A link already built by Maps' own "embed a map" button.
            if (str_starts_with($source->path, '/maps/embed')) {
                return ['src' => $source->url, 'ratio' => '4-3', 'title' => 'Map'];
            }

            if (! str_starts_with($source->path, '/maps')) {
                return null;
            }

            $params = [];

            // A place link names the place; a plain map link carries the
            // coordinates and zoom in the @ segment.
            if ($match = $source->pathMatches('#/maps/place/([^/@?]+)#')) {
                $params['q'] = rawurldecode(str_replace('+', ' ', $match[1]));
            } elseif ($query = $source->query('q')) {
                $params['q'] = $query;
            }

            if ($match = $source->pathMatches('#/@(-?\d+\.\d+),(-?\d+\.\d+),([\d.]+)z#')) {
                $params['q'] ??= $match[1].','.$match[2];
                $params['z'] = (int) round((float) $match[3]);
            }

            // A shortened goo.gl link, or a search with nothing to centre on,
            // can only be resolved by asking Google - which this never does.
            if (! isset($params['q'])) {
                return null;
            }

            $params['output'] = 'embed';

            return [
                'src' => 'https://maps.google.com/maps?'.http_build_query($params),
                'ratio' => '4-3',
                'title' => 'Map',
            ];
        };
    }

    private static function openStreetMap(): Closure
    {
        return function (EmbedSource $source): ?array {
            // OpenStreetMap puts the view in the fragment: #map=15/51.5/-0.12
            if (! preg_match('#map=([\d.]+)/(-?[\d.]+)/(-?[\d.]+)#', $source->fragment, $match)) {
                return null;
            }

            [, $zoom, $lat, $lon] = $match;

            // The embed wants a bounding box rather than a zoom level, so the
            // zoom is turned back into a span: each level halves what is shown.
            $span = 0.35 / (2 ** max(0.0, (float) $zoom - 12));

            return [
                'src' => 'https://www.openstreetmap.org/export/embed.html?'.http_build_query([
                    'bbox' => implode(',', [
                        round($lon - $span, 5), round($lat - $span, 5),
                        round($lon + $span, 5), round($lat + $span, 5),
                    ]),
                    'layer' => 'mapnik',
                    'marker' => $lat.','.$lon,
                ]),
                'ratio' => '4-3',
                'title' => 'Map',
            ];
        };
    }

    private static function mediaFile(): Closure
    {
        return function (EmbedSource $source): ?array {
            $tag = match ($source->extension()) {
                'mp4', 'webm', 'ogv', 'm4v' => 'video',
                'mp3', 'wav', 'm4a', 'oga', 'flac' => 'audio',
                default => null,
            };

            return $tag ? [
                'kind' => Embed::MEDIA,
                'tag' => $tag,
                'src' => $source->url,
                'title' => $tag === 'video' ? 'Video' : 'Audio',
            ] : null;
        };
    }

    // Helpers --------------------------------------------------------------

    /** "1h2m10s", "90" or "" as a number of seconds. */
    private static function seconds(string $value): int
    {
        if ($value === '') {
            return 0;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        if (! preg_match('#^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$#i', $value, $match)) {
            return 0;
        }

        return ((int) ($match[1] ?? 0)) * 3600 + ((int) ($match[2] ?? 0)) * 60 + ((int) ($match[3] ?? 0));
    }

    /** The domain this site is served from, for providers that ask for it. */
    private static function siteHost(): string
    {
        $host = request()->getHost();

        if ($host === '') {
            $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        }

        return $host !== '' ? $host : 'localhost';
    }
}
