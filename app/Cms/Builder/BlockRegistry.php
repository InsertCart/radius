<?php

namespace App\Cms\Builder;

use App\Cms\Builder\Blocks\Block;

/**
 * The catalogue of widgets the editor can place.
 *
 * Blocks are declared in config/builder.php. A theme or a future add-on can
 * register more at runtime through register(), so the widget list is not
 * closed to the CMS's own blocks.
 */
class BlockRegistry
{
    /** @var array<string, class-string<Block>> */
    private array $blocks = [];

    /** @var array<string, Block> */
    private array $instances = [];

    private bool $booted = false;

    /** Categories shown as groups in the widget panel. */
    public const CATEGORIES = [
        'basic' => 'Basic',
        'media' => 'Media',
        'layout' => 'Layout',
        'content' => 'Content',
        'shop' => 'Shop',
        'product' => 'Product page',
        'site' => 'Site parts',
    ];

    private function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach (config('builder.blocks', []) as $class) {
            $this->register($class);
        }

        $this->booted = true;
    }

    /** @param class-string<Block> $class */
    public function register(string $class): void
    {
        if (! class_exists($class) || ! is_subclass_of($class, Block::class)) {
            return;
        }

        $this->blocks[$class::type()] = $class;
    }

    /** @return class-string<Block>|null */
    public function find(string $type): ?string
    {
        $this->boot();

        return $this->blocks[$type] ?? null;
    }

    public function make(string $type): ?Block
    {
        $class = $this->find($type);

        if (! $class) {
            return null;
        }

        return $this->instances[$type] ??= new $class;
    }

    public function has(string $type): bool
    {
        return $this->find($type) !== null;
    }

    /** @return array<string, class-string<Block>> */
    public function all(): array
    {
        $this->boot();

        return $this->blocks;
    }

    /**
     * Widgets the current site can actually use, with their full schemas.
     * Blocks whose module is switched off never reach the editor.
     */
    public function available(): array
    {
        $available = [];

        foreach ($this->all() as $type => $class) {
            if (! $class::isAvailable()) {
                continue;
            }

            $available[$type] = $class::schema();
        }

        uasort($available, function (array $a, array $b) {
            return [$a['category'], $a['order'], $a['name']] <=> [$b['category'], $b['order'], $b['name']];
        });

        return $available;
    }

    /**
     * The widget panel's grouped list. Widgets tied to particular areas are
     * only offered while one of those areas is being edited.
     */
    public function panel(?string $area = null): array
    {
        $grouped = [];

        foreach ($this->available() as $type => $schema) {
            $areas = $this->blocks[$type]::areas();

            if ($areas !== null && ! in_array($area, $areas, true)) {
                continue;
            }

            $grouped[$schema['category']][] = [
                'type' => $type,
                'name' => $schema['name'],
                'icon' => $schema['icon'],
                'keywords' => $schema['keywords'],
            ];
        }

        $panel = [];

        foreach (self::CATEGORIES as $key => $label) {
            if (! empty($grouped[$key])) {
                $panel[] = ['key' => $key, 'label' => $label, 'widgets' => $grouped[$key]];
            }
        }

        return $panel;
    }
}
