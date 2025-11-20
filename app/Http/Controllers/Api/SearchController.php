<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\BackgroundResource;
use App\Http\Resources\ClassResource;
use App\Http\Resources\FeatResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\RaceResource;
use App\Http\Resources\SpellResource;
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

        // Transform to resources
        $data = [
            'spells' => SpellResource::collection($results['spells'] ?? collect())->resolve(),
            'items' => ItemResource::collection($results['items'] ?? collect())->resolve(),
            'races' => RaceResource::collection($results['races'] ?? collect())->resolve(),
            'classes' => ClassResource::collection($results['classes'] ?? collect())->resolve(),
            'backgrounds' => BackgroundResource::collection($results['backgrounds'] ?? collect())->resolve(),
            'feats' => FeatResource::collection($results['feats'] ?? collect())->resolve(),
        ];

        // Calculate totals
        $totalResults = collect($results)->sum(fn ($items) => $items->count());

        $response = [
            'data' => $data,
            'meta' => [
                'query' => $query,
                'types_searched' => $types ?? $this->searchService->getAvailableTypes(),
                'limit_per_type' => $limit,
                'total_results' => $totalResults,
            ],
        ];

        // Add debug info if requested
        if ($request->boolean('debug')) {
            $response['debug'] = [
                'query' => $query,
                'types' => $types,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'meilisearch_host' => config('scout.meilisearch.host'),
            ];
        }

        return response()->json($response);
    }
}
