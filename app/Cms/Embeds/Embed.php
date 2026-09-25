<?php

namespace App\Cms\Embeds;

/**
 * What one recognised link turns into.
 *
 * Four shapes cover every provider worth supporting:
 *
 *  - RATIO  an iframe in a responsive aspect-ratio box. Video players, slides,
 *           maps: anything that should fill the column and keep its shape.
 *  - CARD   an iframe of a fixed height, capped at the width the provider
 *           designed for. Social posts and audio players, which are laid out
 *           by the provider and do not scale by ratio.
 *  - SCRIPT markup the provider's own script turns into an embed. Used only
 *           where there is no iframe endpoint - X and Reddit size themselves
 *           to the post, which nothing on this side can predict.
 *  - MEDIA  a <video> or <audio> element, for a link straight to a file.
 */
final class Embed
{
    public const RATIO = 'ratio';

    public const CARD = 'card';

    public const SCRIPT = 'script';

    public const MEDIA = 'media';

    public function __construct(
        public readonly string $provider,
        public readonly string $label,
        public readonly string $url,
        public readonly string $kind,
        public readonly ?string $src = null,
        public readonly string $ratio = '16-9',
        public readonly int $height = 0,
        public readonly int $maxWidth = 0,
        public readonly ?string $title = null,
        public readonly ?string $markup = null,
        public readonly ?string $script = null,
        public readonly ?string $tag = null,
    ) {}

    /**
     * Fills in the provider name and label a resolver does not have to repeat.
     *
     * @param  array<string, mixed>  $parts
     */
    public static function make(string $provider, string $label, string $url, array $parts): self
    {
        return new self(
            provider: $provider,
            label: $label,
            url: $url,
            kind: (string) ($parts['kind'] ?? self::RATIO),
            src: $parts['src'] ?? null,
            ratio: (string) ($parts['ratio'] ?? '16-9'),
            height: (int) ($parts['height'] ?? 0),
            maxWidth: (int) ($parts['max_width'] ?? 0),
            title: $parts['title'] ?? null,
            markup: $parts['markup'] ?? null,
            script: $parts['script'] ?? null,
            tag: $parts['tag'] ?? null,
        );
    }
}
