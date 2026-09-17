<?php

namespace App\Cms\Search;

use App\Cms\Search\Contracts\Engine;
use App\Cms\Search\Contracts\Searchable;
use App\Cms\Search\Engines\DatabaseEngine;
use App\Cms\Search\Engines\IndexEngine;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

/**
 * The one entry point to site search.
 *
 * Controllers, widgets and themes ask this class; it decides which engine
 * answers, which content types are switched on, and keeps an index current as
 * content is saved. An engine that is not ready - an index that has not been
 * built yet - is quietly replaced by the database engine, so search never
 * goes dark because of a missing build.
 */
class SearchManager
{
    /** @var array<string, array<string, mixed>> */
    private array $types;

    /** @var array<string, Engine> */
    private array $engines = [];

    /** @var array<string, Closure> */
    private array $extensions = [];

    /** @var array<class-string, true> */
    private array $observed = [];

    private bool $observing = false;

    private bool $scriptsRendered = false;

    public function __construct(private Container $app)
    {
        $this->types = config('search.types', []);
    }

    // Registration -----------------------------------------------------------

    /**
     * Makes a model searchable.
     *
     *     search()->register('event', [
     *         'model' => Event::class,
     *         'label' => 'Events',
     *         'setting' => null,           // always on
     *         'results_route' => 'events.index',
     *     ]);
     */
    public function register(string $type, array $definition): void
    {
        if (! preg_match('/^[a-z0-9_-]+$/', $type)) {
            throw new InvalidArgumentException("Search type [{$type}] may only contain a-z, 0-9, dashes and underscores.");
        }

        $model = $definition['model'] ?? null;

        if (! is_string($model) || ! is_subclass_of($model, Model::class) || ! is_subclass_of($model, Searchable::class)) {
            throw new InvalidArgumentException("Search type [{$type}] needs a model implementing ".Searchable::class.'.');
        }

        $this->types[$type] = array_merge(['label' => ucfirst($type), 'module' => null, 'setting' => null, 'results_route' => null], $definition);

        if ($this->observing) {
            $this->observeModel($model);
        }
    }

    /** Adds an engine the site owner can then choose by name. */
    public function extend(string $name, Closure $factory): void
    {
        $this->extensions[$name] = $factory;
        unset($this->engines[$name]);
    }

    /** Every registered type, enabled or not. */
    public function definitions(): array
    {
        return $this->types;
    }

    /**
     * Types the public can search right now: module on, and not switched off
     * under Settings -> Search.
     *
     * @return array<string, array<string, mixed>>
     */
    public function types(): array
    {
        return array_filter($this->types, function (array $definition) {
            if (filled($definition['module'] ?? null) && ! modules()->enabled($definition['module'])) {
                return false;
            }

            if (blank($definition['setting'] ?? null)) {
                return true;
            }

            $value = settings()->get($definition['setting']);

            return $value === null || filter_var($value, FILTER_VALIDATE_BOOLEAN);
        });
    }

    public function has(string $type): bool
    {
        return isset($this->types[$type]);
    }

    /** @return class-string<Model&Searchable> */
    public function modelFor(string $type): string
    {
        if (! isset($this->types[$type])) {
            throw new InvalidArgumentException("Unknown search type [{$type}].");
        }

        return $this->types[$type]['model'];
    }

    public function typeFor(Model $model): ?string
    {
        foreach ($this->types as $type => $definition) {
            if ($model instanceof $definition['model']) {
                return $type;
            }
        }

        return null;
    }

    // Engines ----------------------------------------------------------------

    /** The engine chosen under Settings -> Search. */
    public function engineName(): string
    {
        return (string) (settings()->get('search_engine') ?: 'database');
    }

    public function engine(?string $name = null): Engine
    {
        $name ??= $this->engineName();

        if (! isset($this->extensions[$name]) && ! in_array($name, ['database', 'index'], true)) {
            $name = 'database';
        }

        return $this->engines[$name] ??= match (true) {
            isset($this->extensions[$name]) => ($this->extensions[$name])($this->app),
            $name === 'index' => new IndexEngine($this, $this->app->make(\Illuminate\Filesystem\Filesystem::class)),
            default => new DatabaseEngine($this),
        };
    }

    /** The chosen engine if it can answer for $type, the database otherwise. */
    public function engineFor(string $type): Engine
    {
        $engine = $this->engine();

        try {
            if ($engine->ready($type)) {
                return $engine;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->engine('database');
    }

    /** Names shown in the engine picker, for engines registered with extend(). */
    public function engineNames(): array
    {
        return array_values(array_unique(array_merge(['database', 'index'], array_keys($this->extensions))));
    }

    // Searching --------------------------------------------------------------

    /**
     * Narrows a listing query to matches for $term. A blank term leaves the
     * query untouched, so a listing page can call this unconditionally.
     *
     *     $posts = search()->constrain(Post::published(), 'post', $request->input('q'), rank: true)
     *         ->paginate();
     */
    public function constrain(Builder|Relation $query, string $type, ?string $term, bool $rank = false): Builder|Relation
    {
        $term = $this->clean($term);

        if ($term === '' || ! $this->has($type)) {
            return $query;
        }

        return $this->engineFor($type)->constrain($query, $type, $term, $rank);
    }

    /** Display-ready matches for one type. */
    public function search(string $type, ?string $term, int $limit = 10, int $offset = 0): SearchResults
    {
        $term = $this->clean($term);

        if ($term === '' || ! $this->has($type)) {
            return SearchResults::empty();
        }

        return $this->engineFor($type)->search($type, $term, max(1, min($limit, 100)), max(0, $offset));
    }

    /**
     * Grouped matches for live results.
     *
     * @return array{query: string, url: ?string, groups: array<int, array<string, mixed>>}
     */
    public function suggest(?string $term, ?string $type = null, ?int $limit = null): array
    {
        $term = $this->clean($term);
        $limit ??= $this->suggestLimit();

        $types = $this->types();

        if ($type !== null && $type !== '' && $type !== 'all') {
            $types = array_intersect_key($types, [$type => true]);
        }

        $response = ['query' => $term, 'url' => $this->searchUrl($term), 'groups' => []];

        if (mb_strlen($term) < $this->minChars() || $types === []) {
            return $response;
        }

        $build = function () use ($types, $term, $limit, $response) {
            foreach ($types as $key => $definition) {
                $results = $this->search($key, $term, $limit);

                if ($results->total === 0) {
                    continue;
                }

                $response['groups'][] = [
                    'type' => $key,
                    'label' => $definition['label'],
                    'total' => $results->total,
                    'url' => $this->resultsUrl($key, $term),
                    'items' => $results->items,
                ];
            }

            return $response;
        };

        // The index answers from files already; caching only pays for the
        // database engine, where it spares a LIKE scan per repeated keystroke.
        $seconds = $this->cacheSeconds();

        if ($seconds <= 0 || $this->engineName() !== 'database') {
            return $build();
        }

        $key = 'cms.search.suggest.'.md5(json_encode([array_keys($types), $term, $limit, url('/')]));

        return Cache::remember($key, $seconds, $build);
    }

    /** Where "see all" for one type goes, or null when there is nowhere to go. */
    public function resultsUrl(string $type, ?string $term): ?string
    {
        $route = $this->types[$type]['results_route'] ?? null;

        if ($route && Route::has($route)) {
            return route($route, ['q' => $term]);
        }

        return Route::has('search') ? route('search', ['q' => $term, 'type' => $type]) : null;
    }

    /**
     * Where a search form for $type submits: the type's own listing when it
     * has one, the site-wide results page otherwise.
     */
    public function formAction(string $type = 'all'): string
    {
        $route = $this->types[$type]['results_route'] ?? null;

        if ($route && Route::has($route)) {
            return route($route);
        }

        return Route::has('search') ? route('search') : url('/');
    }

    public function searchUrl(?string $term = null): ?string
    {
        return Route::has('search') ? route('search', array_filter(['q' => $term])) : null;
    }

    /**
     * One row as a result: the model's own toSearchResult() with id and type
     * added and every key present, so views and the JSON response can rely on
     * the shape.
     */
    public function resultFor(string $type, Model $model): array
    {
        /** @var Model&Searchable $model */
        $result = $model->toSearchResult();

        return [
            'id' => $model->getKey(),
            'type' => $type,
            'title' => (string) ($result['title'] ?? ''),
            'url' => (string) ($result['url'] ?? '#'),
            'excerpt' => $result['excerpt'] ?? null,
            'image' => $result['image'] ?? null,
            'meta' => $result['meta'] ?? null,
            'visible_from' => isset($result['visible_from']) ? (int) $result['visible_from'] : null,
        ];
    }

    // Keeping an index current -----------------------------------------------

    /** Hooks model events so saved content reaches the index. Called once at boot. */
    public function observe(): void
    {
        $this->observing = true;

        foreach ($this->types as $definition) {
            $this->observeModel($definition['model']);
        }
    }

    /** Adds, refreshes or removes one row in the active engine, as its current state requires. */
    public function sync(Model $model): void
    {
        $type = $this->typeFor($model);

        if ($type === null || $this->engineName() === 'database') {
            return;
        }

        // Never let the index take down a save. The worst case is a stale
        // entry until the next rebuild; the admin's work is not lost.
        try {
            $engine = $this->engine();

            if (! $engine->ready($type)) {
                return;
            }

            $class = $this->modelFor($type);
            $fresh = $class::searchableQuery(true)->whereKey($model->getKey())->first();

            $fresh ? $engine->update($type, $fresh) : $engine->delete($type, $model->getKey());
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function remove(Model $model): void
    {
        $type = $this->typeFor($model);

        if ($type === null || $this->engineName() === 'database') {
            return;
        }

        try {
            $engine = $this->engine();

            if ($engine->ready($type)) {
                $engine->delete($type, $model->getKey());
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Rebuilds the chosen engine for every enabled type, or just $type.
     *
     * @return array<string, int> documents indexed per type
     */
    public function rebuild(?string $type = null, ?string $engine = null): array
    {
        $engine = $this->engine($engine);
        $types = $type ? [$type => $this->types[$type] ?? throw new InvalidArgumentException("Unknown search type [{$type}].")] : $this->types();
        $counts = [];

        foreach (array_keys($types) as $key) {
            $counts[$key] = $engine->rebuild($key);
        }

        return $counts;
    }

    /** Per-type facts for the admin status card and search:status. */
    public function status(): array
    {
        $engine = $this->engine();
        $enabled = $this->types();
        $rows = [];

        foreach ($this->types as $type => $definition) {
            try {
                $status = $engine->status($type);
            } catch (\Throwable $e) {
                $status = ['ready' => false, 'documents' => null, 'built_at' => null, 'updated_at' => null, 'bytes' => null, 'error' => $e->getMessage()];
            }

            $rows[$type] = array_merge($status, [
                'label' => $definition['label'],
                'enabled' => isset($enabled[$type]),
            ]);
        }

        return $rows;
    }

    // Settings ---------------------------------------------------------------

    public function instantEnabled(): bool
    {
        return settings()->bool('search_instant', true);
    }

    public function pageEnabled(): bool
    {
        return settings()->bool('search_page', true);
    }

    public function minChars(): int
    {
        return max(1, min(10, settings()->int('search_min_chars', 2)));
    }

    public function suggestLimit(): int
    {
        return max(1, min(20, settings()->int('search_suggest_limit', 5)));
    }

    public function rateLimit(): int
    {
        return max(10, settings()->int('search_rate_limit', 60));
    }

    public function cacheSeconds(): int
    {
        return max(0, settings()->int('search_cache_seconds', 60));
    }

    // Front end --------------------------------------------------------------

    /**
     * The live-results script and its styles, at most once per page, and only
     * when live results are switched on. Emitted by @searchScripts, which every
     * search form includes next to itself - so a theme needs no layout change.
     */
    public function scripts(): string
    {
        if ($this->scriptsRendered || ! $this->instantEnabled() || ! Route::has('search.suggest')) {
            return '';
        }

        $this->scriptsRendered = true;

        return view(theme_view('search.script'), [
            'config' => [
                'endpoint' => route('search.suggest'),
                'minChars' => $this->minChars(),
                'delay' => 200,
                'labels' => [
                    'noResults' => __('No results for “:query”'),
                    'seeAll' => __('See all :count'),
                    'viewAll' => __('Search the whole site'),
                ],
            ],
        ])->render();
    }

    private function observeModel(string $model): void
    {
        if (isset($this->observed[$model])) {
            return;
        }

        $this->observed[$model] = true;

        $model::saved(fn (Model $row) => $this->sync($row));
        $model::deleted(fn (Model $row) => $this->remove($row));

        if (method_exists($model, 'restored')) {
            $model::restored(fn (Model $row) => $this->sync($row));
        }
    }

    private function clean(?string $term): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $term)), 0, 100);
    }
}
