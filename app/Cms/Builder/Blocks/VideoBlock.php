<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

/**
 * Embeds a YouTube or Vimeo video, or a self-hosted file.
 *
 * Third-party embeds use the privacy-preserving host where one exists
 * (youtube-nocookie), because a CMS sold to many buyers should not opt their
 * visitors into tracking by default.
 */
class VideoBlock extends Block
{
    public static function type(): string
    {
        return 'video';
    }

    public static function name(): string
    {
        return 'Video';
    }

    public static function icon(): string
    {
        return 'video';
    }

    public static function category(): string
    {
        return 'media';
    }

    public static function order(): int
    {
        return 2;
    }

    public static function keywords(): array
    {
        return ['youtube', 'vimeo', 'embed', 'movie'];
    }

    public static function controls(): array
    {
        return [
            Control::select('source', 'Source', [
                'youtube' => 'YouTube',
                'vimeo' => 'Vimeo',
                'file' => 'Self-hosted file',
            ])->default('youtube'),

            Control::text('url', 'Video URL')
                ->placeholder('https://www.youtube.com/watch?v=...')
                ->help('Paste the normal share link; the embed URL is worked out for you.'),

            Control::image('poster', 'Placeholder image')
                ->help('Shown before the video loads.'),

            Control::toggle('autoplay', 'Autoplay')
                ->help('Most browsers only allow autoplay when the video is muted.'),
            Control::toggle('muted', 'Start muted'),
            Control::toggle('loop', 'Loop'),
            Control::toggle('controls', 'Show controls')->default(true),

            Control::select('ratio', 'Aspect ratio', [
                '16-9' => '16:9', '4-3' => '4:3', '1-1' => 'Square', '21-9' => '21:9', '9-16' => 'Vertical',
            ])->default('16-9'),

            Control::dimensions('radius', 'Rounded corners')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-video', 'border-radius'),

            Control::shadow('shadow', 'Shadow')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-video', 'box-shadow'),
        ];
    }

    public function data(array $settings, array $context = []): array
    {
        return [
            'embedUrl' => $this->embedUrl($settings),
        ];
    }

    /** Normalise a share URL into an embeddable one. */
    private function embedUrl(array $settings): ?string
    {
        $url = trim((string) ($settings['url'] ?? ''));

        if ($url === '') {
            return null;
        }

        $params = array_filter([
            'autoplay' => ! empty($settings['autoplay']) ? 1 : null,
            'mute' => ! empty($settings['muted']) ? 1 : null,
            'loop' => ! empty($settings['loop']) ? 1 : null,
            'controls' => empty($settings['controls']) ? 0 : null,
            'rel' => 0,
        ], fn ($v) => $v !== null);

        if (($settings['source'] ?? 'youtube') === 'youtube') {
            if (! preg_match('#(?:youtu\.be/|v=|embed/|shorts/)([A-Za-z0-9_-]{6,})#', $url, $m)) {
                return null;
            }

            if (! empty($settings['loop'])) {
                $params['playlist'] = $m[1];
            }

            return 'https://www.youtube-nocookie.com/embed/'.$m[1].'?'.http_build_query($params);
        }

        if (($settings['source'] ?? '') === 'vimeo') {
            if (! preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
                return null;
            }

            return 'https://player.vimeo.com/video/'.$m[1].'?'.http_build_query([
                'autoplay' => $params['autoplay'] ?? 0,
                'muted' => $params['mute'] ?? 0,
                'loop' => ! empty($settings['loop']) ? 1 : 0,
                'dnt' => 1,
            ]);
        }

        return null;
    }
}
