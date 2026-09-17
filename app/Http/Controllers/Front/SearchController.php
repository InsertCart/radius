<?php

namespace App\Http\Controllers\Front;

use App\Cms\Search\SearchManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class SearchController extends Controller
{
    /** Results shown per type on the combined page, before "see all". */
    private const PER_GROUP = 5;

    public function __construct(private SearchManager $search) {}

    /**
     * The site-wide results page: a few results from every searchable type,
     * or one type paginated when ?type= names it.
     */
    public function index(Request $request): View
    {
        $query = trim($request->string('q')->toString());
        $types = $this->search->types();
        $type = $request->string('type')->toString();
        $type = isset($types[$type]) ? $type : null;

        $groups = [];
        $paginator = null;

        if ($query !== '' && $type !== null) {
            $perPage = (int) setting('posts_per_page', 12);
            $page = max(1, $request->integer('page', 1));
            $results = $this->search->search($type, $query, $perPage, ($page - 1) * $perPage);

            $paginator = (new LengthAwarePaginator($results->items, $results->total, $perPage, $page, [
                'path' => $request->url(),
                'pageName' => 'page',
            ]))->withQueryString();

            $groups[] = $this->group($type, $types[$type], $query, $results->items, $results->total);
        } elseif ($query !== '') {
            foreach ($types as $key => $definition) {
                $results = $this->search->search($key, $query, self::PER_GROUP);

                if ($results->total > 0) {
                    $groups[] = $this->group($key, $definition, $query, $results->items, $results->total);
                }
            }
        }

        seo()->title($query !== '' ? __('Search results for “:query”', ['query' => $query]) : __('Search'))
            ->noindex();

        return view('theme::search.index', [
            'query' => $query,
            'type' => $type,
            'types' => $types,
            'groups' => $groups,
            'total' => array_sum(array_column($groups, 'total')),
            'paginator' => $paginator,
        ]);
    }

    /** JSON for live results while typing. */
    public function suggest(Request $request): JsonResponse
    {
        abort_unless($this->search->instantEnabled(), 404);

        $data = $this->search->suggest(
            $request->string('q')->toString(),
            $request->string('type')->toString() ?: null,
        );

        // visible_from is an internal scheduling detail, not for visitors.
        foreach ($data['groups'] as &$group) {
            $group['items'] = array_map(
                fn (array $item) => array_diff_key($item, ['visible_from' => true]),
                $group['items'],
            );
        }

        return response()->json($data)->header('Cache-Control', 'private, max-age=30');
    }

    private function group(string $key, array $definition, string $query, array $items, int $total): array
    {
        return [
            'type' => $key,
            'label' => $definition['label'],
            'total' => $total,
            'url' => $this->search->resultsUrl($key, $query),
            'items' => $items,
        ];
    }
}
