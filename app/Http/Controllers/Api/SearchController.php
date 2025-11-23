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
     * Search simultaneously across spells, items, races, classes, backgrounds, feats, and monsters.
     * Returns grouped results with relevance ranking powered by Meilisearch. Perfect for implementing
     * universal search bars, type-ahead autocomplete, and cross-entity content discovery.
     *
     * **Basic Examples:**
     * - Search everything: `GET /api/v1/search?q=fire`
     * - Search by keyword: `GET /api/v1/search?q=dragon` (finds Dragon spells, Dragon race, Dragon monsters)
     * - Search specific types: `GET /api/v1/search?q=healing&types=spells,items`
     * - Limit results per type: `GET /api/v1/search?q=magic&limit=10` (10 per entity type)
     * - Debug mode: `GET /api/v1/search?q=test&debug=true` (includes execution time, Meilisearch host)
     *
     * **Type-Specific Search Examples:**
     * - Spells only: `GET /api/v1/search?q=fireball&types=spells`
     * - Monsters only: `GET /api/v1/search?q=ancient&types=monsters`
     * - Items + Equipment: `GET /api/v1/search?q=sword&types=items`
     * - Character options: `GET /api/v1/search?q=elf&types=races,classes,backgrounds,feats`
     * - Combat content: `GET /api/v1/search?q=attack&types=spells,items,monsters`
     *
     * **Multi-Type Search Examples:**
     * - Fire-themed content: `GET /api/v1/search?q=fire&types=spells,monsters,items` (Fire spells, fire-breathing monsters, fire resistance items)
     * - Healing options: `GET /api/v1/search?q=healing&types=spells,items,classes` (Healing spells, potions, Cleric)
     * - Stealth builds: `GET /api/v1/search?q=stealth&types=spells,items,backgrounds,feats` (Invisibility, Cloak of Elvenkind, Criminal, Skulker)
     * - Undead encounters: `GET /api/v1/search?q=undead&types=monsters,spells,items` (Zombie, Turn Undead, Holy Water)
     *
     * **Fuzzy Matching Examples (Typo-Tolerant):**
     * - "firebll" → finds "Fireball" (1 character typo)
     * - "ligt" → finds "Light" (1 character typo)
     * - "elv" → finds "Elf", "Elven", "Elvish" (prefix matching)
     * - "wizar" → finds "Wizard" (partial word matching)
     *
     * **Use Cases:**
     * - **Universal Search Bar**: Single endpoint for navbar/header search across entire D&D compendium
     * - **Type-Ahead Autocomplete**: Fast fuzzy matching with <50ms average response time
     * - **Content Discovery**: "What D&D content relates to dragons?" (spells, monsters, races, items)
     * - **Character Building**: Find related spells, items, and feats for specific builds
     * - **DM Encounter Prep**: Search for monsters + spells + items for themed encounters
     * - **Rules Lookup**: Quick reference across all entity types ("What gives advantage?")
     * - **Mobile Apps**: Lightweight endpoint for cross-entity search on mobile devices
     * - **API Exploration**: Discover available content before querying specific endpoints
     *
     * **Query Parameters:**
     * - `q` (string, required): Search query term (min 2 characters)
     * - `types` (string, optional): Comma-separated entity types to search (default: all)
     *   - Valid types: `spells`, `items`, `monsters`, `races`, `classes`, `backgrounds`, `feats`
     * - `limit` (int, optional): Maximum results per entity type (default: 20, max: 100)
     * - `debug` (bool, optional): Include debug info (execution time, Meilisearch host)
     *
     * **Response Structure:**
     * ```json
     * {
     *   "data": {
     *     "query": "fire",
     *     "types_searched": ["spells", "items", "monsters", "races", "classes", "backgrounds", "feats"],
     *     "limit_per_type": 20,
     *     "total_results": 42,
     *     "spells": [...],      // Array of spell objects
     *     "items": [...],       // Array of item objects
     *     "monsters": [...],    // Array of monster objects
     *     "races": [...],       // Array of race objects
     *     "classes": [...],     // Array of class objects
     *     "backgrounds": [...], // Array of background objects
     *     "feats": [...]        // Array of feat objects
     *   }
     * }
     * ```
     *
     * **Performance:**
     * - Average response time: <50ms for most queries
     * - p95 response time: <100ms
     * - Searches 3,600+ documents across 7 entity types
     * - Powered by Meilisearch for typo-tolerance and relevance ranking
     *
     * **Relevance Ranking:**
     * Results are sorted by Meilisearch relevance score, which considers:
     * - Exact matches (highest priority)
     * - Prefix matches (e.g., "fire" matches "Fireball")
     * - Typo tolerance (1-2 character differences)
     * - Field importance (name > description)
     * - Word proximity (closer words rank higher)
     *
     * **Data Source:**
     * - 477 spells, 516 items, 598 monsters, 131 classes
     * - Races, subraces, backgrounds, feats from all D&D 5e sourcebooks
     * - Indexed in Meilisearch for fast fuzzy search
     * - Falls back to database LIKE queries if Meilisearch unavailable
     *
     * **Example Frontend Implementation:**
     * ```javascript
     * // Universal search bar with type-ahead
     * const searchAll = async (query) => {
     *   const response = await fetch(`/api/v1/search?q=${query}&limit=5`);
     *   const data = await response.json();
     *   return data.data; // { spells: [...], items: [...], ... }
     * };
     *
     * // Category-specific search (e.g., spell picker)
     * const searchSpells = async (query) => {
     *   const response = await fetch(`/api/v1/search?q=${query}&types=spells&limit=10`);
     *   const data = await response.json();
     *   return data.data.spells; // Array of matching spells
     * };
     * ```
     *
     * See `docs/API-EXAMPLES.md` for comprehensive usage examples and integration patterns.
     *
     * @param  SearchRequest  $request  Validated request with search query and filters
     * @return SearchResource Grouped search results with metadata
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
            'monsters' => $results['monsters'] ?? collect(),
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
