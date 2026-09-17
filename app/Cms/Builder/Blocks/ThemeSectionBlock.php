<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;
use App\Cms\Themes\ThemeSectionKeys;
use App\Cms\Themes\ThemeSections;
use Illuminate\Support\Facades\View;

/**
 * A section of the active theme - its header, hero, a product rail - placed
 * as a widget.
 *
 * The theme draws it with its own markup and stylesheet, so it looks exactly
 * like the rest of the site. The widget panel lists each section separately;
 * they all share this one widget type and are told apart by the "section"
 * setting. See ThemeSections for how a theme declares them.
 */
class ThemeSectionBlock extends Block
{
    public static function type(): string
    {
        return 'theme-section';
    }

    public static function name(): string
    {
        return 'Theme section';
    }

    public static function icon(): string
    {
        return 'grid';
    }

    public static function category(): string
    {
        return 'theme';
    }

    public static function order(): int
    {
        return 1;
    }

    public static function isAvailable(): bool
    {
        return app(ThemeSections::class)->hasAny();
    }

    public static function controls(): array
    {
        $sections = app(ThemeSections::class);

        $controls = [
            Control::select('section', 'Section', collect($sections->all())->map(fn ($s) => $s['label'])->all())
                ->default(array_key_first($sections->all())),
        ];

        // Each section's own fields, shown only while that section is chosen.
        foreach ($sections->all() as $key => $section) {
            foreach ($sections->controls($key, prefixed: true) as $control) {
                $controls[] = $control->when('section', $key);
            }
        }

        return $controls;
    }

    public static function defaults(): array
    {
        // Only the chosen section's defaults; the panel supplies the rest.
        $sections = app(ThemeSections::class);
        $first = array_key_first($sections->all());

        if ($first === null) {
            return [];
        }

        return self::defaultsFor($first);
    }

    /** A fresh widget's settings for one section. */
    public static function defaultsFor(string $section): array
    {
        $settings = ['section' => $section];

        foreach (app(ThemeSections::class)->defaults($section) as $key => $value) {
            $settings[ThemeSectionKeys::prefixed($section, $key)] = $value;
        }

        return $settings;
    }

    public function render(array $settings, array $context = []): string
    {
        $sections = app(ThemeSections::class);
        $key = (string) ($settings['section'] ?? '');
        $section = $sections->get($key);
        $editing = (bool) ($context['editing'] ?? false);

        // The layout outlived the theme it was built with. Say so in the
        // editor; show nothing to visitors rather than an error.
        if (! $section) {
            return $this->placeholder('This section came from a theme that is no longer active.', $context);
        }

        $view = 'theme::'.$section['view'];

        if (! View::exists($view)) {
            return $this->placeholder('The theme is missing the view for "'.$section['label'].'".', $context);
        }

        $own = array_merge($sections->defaults($key), ThemeSectionKeys::extract($key, $settings));

        return View::make($view, [
            'settings' => $own,
            'context' => $context,
            'editing' => $editing,
            'model' => $context['model'] ?? null,
        ])->render();
    }
}
