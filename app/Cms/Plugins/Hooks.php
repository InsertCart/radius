<?php

namespace App\Cms\Plugins;

/**
 * The places a plugin may add to the CMS without editing it.
 *
 * Two kinds, both deliberately small:
 *
 *  - Output hooks. A core view marks a spot with @hook('admin.themes.actions',
 *    $theme) and every callback listening on that name is called with the same
 *    arguments; whatever they return is printed there, in priority order.
 *
 *  - Admin links. A plugin adds an entry to a section of the admin sidebar,
 *    which AdminNavigation merges in with the CMS's own.
 *
 * Hook names used by the CMS itself:
 *
 *   admin.themes.actions   ($theme)  buttons under each theme card
 *   admin.themes.sidebar   ()        the right-hand column of the Themes screen
 *   admin.plugins.card     ($plugin) under a plugin on the Plugins screen
 *
 * Public pages have no hooks: themes are written by other people and cannot
 * be relied on to carry one. A plugin that adds to the front end does it from
 * a middleware, on the rendered response.
 */
class Hooks
{
    /** @var array<string, array<int, array<int, callable>>> hook => priority => callbacks */
    private array $listeners = [];

    /** @var array<string, array<int, array>> section => links */
    private array $adminLinks = [];

    public function listen(string $hook, callable $callback, int $priority = 10): void
    {
        $this->listeners[$hook][$priority][] = $callback;
    }

    public function has(string $hook): bool
    {
        return ! empty($this->listeners[$hook]);
    }

    /**
     * Everything the listeners on $hook return, joined. A listener that throws
     * is reported and skipped: a broken plugin should cost its own output, not
     * the page it was printing into.
     */
    public function render(string $hook, mixed ...$arguments): string
    {
        if (! $this->has($hook)) {
            return '';
        }

        $listeners = $this->listeners[$hook];
        ksort($listeners);

        $output = '';

        foreach ($listeners as $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    $output .= (string) $callback(...$arguments);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return $output;
    }

    /**
     * Adds an entry to the admin sidebar.
     *
     * @param  string  $section  an existing section ("Content", "Appearance", "System", ...) or a new one
     * @param  bool  $adminOnly  most plugin screens change how the site behaves, which is an owner's call
     */
    public function addAdminLink(
        string $section,
        string $label,
        string $route,
        ?string $activePattern = null,
        ?string $icon = null,
        bool $adminOnly = true,
    ): void {
        $this->adminLinks[$section][] = compact('label', 'route', 'activePattern', 'icon', 'adminOnly');
    }

    /** @return array<string, array<int, array>> */
    public function adminLinks(): array
    {
        return $this->adminLinks;
    }
}
