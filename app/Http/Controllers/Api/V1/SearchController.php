<?php

namespace App\Http\Controllers\Api\V1;

use App\Cms\Search\SearchManager;
use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One search across whatever Settings -> Search has switched on, answered by
 * whichever engine the site is configured to use.
 *
 * With ?type= it searches one kind of thing and pages through it; without,
 * it returns a few of each, which is what an app's search screen wants before
 * anybody has chosen a tab.
 */
class SearchController extends ApiController
{
    /** Results per group when no type is named. */
    private const PER_GROUP = 5;

    public function __construct(private SearchManager $search) {}

    public function index(Request $request): JsonResponse
    {
        $query = trim($request->string('q')->toString());
        $types = $this->search->types();

        if ($query === '' || mb_strlen($query) < $this->search->minChars()) {
            return $this->data([
                'query' => $query,
                'types' => array_keys($types),
                'groups' => [],
            ]);
        }

        $type = $request->string('type')->toString();

        if ($type !== '' && isset($types[$type])) {
            $perPage = $this->perPage($request, 50);
            $page = max(1, $request->integer('page', 1));
            $results = $this->search->search($type, $query, $perPage, ($page - 1) * $perPage);

            return $this->page([
                'data' => $results->items,
                'meta' => [
                    'query' => $query,
                    'type' => $type,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $results->total,
                    'last_page' => max(1, (int) ceil($results->total / $perPage)),
                    'has_more' => ($page * $perPage) < $results->total,
                ],
            ]);
        }

        $groups = [];

        foreach ($types as $key => $definition) {
            $results = $this->search->search($key, $query, self::PER_GROUP);

            if ($results->total === 0) {
                continue;
            }

            $groups[$key] = [
                'label' => $definition['label'] ?? ucfirst($key),
                'total' => $results->total,
                'items' => $results->items,
            ];
        }

        return $this->data([
            'query' => $query,
            'types' => array_keys($types),
            'groups' => $groups,
        ]);
    }
}
