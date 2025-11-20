<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\SearchResource;
use App\Services\Search\GlobalSearchService;

class SearchController extends Controller
{
    public function __construct(
        private GlobalSearchService $searchService
    ) {}

    /**
     * Global search across all D&D entities
     *
     * Search simultaneously across spells, items, races, classes, backgrounds, and feats.
     * Returns grouped results with relevance ranking powered by Meilisearch.
     */
    public function __invoke(SearchRequest $request)
    {
        $startTime = microtime(true);

        $query = $request->validated('q');
        $types = $request->validated('types', null);
        $limit = $request->validated('limit', 20);

        // Execute search
        $results = $this->searchService->search($query, $types, $limit);

        // Calculate totals
        $totalResults = collect($results)->sum(fn ($items) => $items->count());

        // Prepare data for resource
        $resourceData = [
            'spells' => $results['spells'] ?? collect(),
            'items' => $results['items'] ?? collect(),
            'races' => $results['races'] ?? collect(),
            'classes' => $results['classes'] ?? collect(),
            'backgrounds' => $results['backgrounds'] ?? collect(),
            'feats' => $results['feats'] ?? collect(),
            'query' => $query,
            'types_searched' => $types ?? $this->searchService->getAvailableTypes(),
            'limit_per_type' => $limit,
            'total_results' => $totalResults,
        ];

        // Add debug info if requested
        if ($request->boolean('debug')) {
            $resourceData['debug'] = [
                'query' => $query,
                'types' => $types,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'meilisearch_host' => config('scout.meilisearch.host'),
            ];
        }

        return new SearchResource($resourceData);
    }
}
