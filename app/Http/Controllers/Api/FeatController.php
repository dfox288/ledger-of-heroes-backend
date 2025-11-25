<?php

namespace App\Http\Controllers\Api;

use App\DTOs\FeatSearchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\FeatIndexRequest;
use App\Http\Requests\FeatShowRequest;
use App\Http\Resources\FeatResource;
use App\Models\Feat;
use App\Services\Cache\EntityCacheService;
use App\Services\FeatSearchService;
use Dedoc\Scramble\Attributes\QueryParameter;

class FeatController extends Controller
{
    /**
     * List all feats
     *
     * Returns a paginated list of D&D 5e feats. Use `?filter=` for filtering and `?q=` for full-text search.
     *
     * **Common Examples:**
     * ```
     * GET /api/v1/feats                                    # All feats
     * GET /api/v1/feats?filter=tag_slugs IN [combat]       # Combat feats
     * GET /api/v1/feats?filter=tag_slugs IN [magic]        # Magic feats
     * GET /api/v1/feats?filter=source_codes IN [PHB]       # PHB feats only
     * GET /api/v1/feats?q=armor                            # Full-text search for "armor"
     * GET /api/v1/feats?q=advantage                        # Search for "advantage"
     * GET /api/v1/feats?filter=tag_slugs IN [combat] AND source_codes IN [PHB, XGE]
     * ```
     *
     * **Tag-Based Filtering:**
     * - Combat feats: `GET /api/v1/feats?filter=tag_slugs IN [combat]`
     * - Magic feats: `GET /api/v1/feats?filter=tag_slugs IN [magic]`
     * - Skill improvement: `GET /api/v1/feats?filter=tag_slugs IN [skill-improvement]`
     * - Multiple tags (OR): `GET /api/v1/feats?filter=tag_slugs IN [combat, magic]`
     *
     * **Source Filtering:**
     * - PHB only: `GET /api/v1/feats?filter=source_codes IN [PHB]`
     * - XGE and TCoE: `GET /api/v1/feats?filter=source_codes IN [XGE, TCoE]`
     *
     * **Filterable Fields:**
     * - `tag_slugs` (array: combat, magic, skill-improvement, etc.)
     * - `source_codes` (array: PHB, XGE, TCoE, etc.)
     * - `id` (int), `slug` (string)
     *
     * **Operators:**
     * - Comparison: `=`, `!=`, `>`, `>=`, `<`, `<=`
     * - Logic: `AND`, `OR`
     * - Membership: `IN [value1, value2, ...]`
     *
     * **Query Parameters:**
     * - `q` (string): Full-text search (searches name, description, prerequisites_text)
     * - `filter` (string): Meilisearch filter expression
     * - `sort_by` (string): name, created_at, updated_at (default: name)
     * - `sort_direction` (string): asc, desc (default: asc)
     * - `per_page` (int): 1-100 (default: 15)
     * - `page` (int): Page number (default: 1)
     *
     * **Legacy MySQL Parameters (Deprecated):**
     * The following parameters still work but are deprecated in favor of Meilisearch filtering:
     * - `prerequisite_race`, `prerequisite_ability`, `min_value`, `prerequisite_proficiency`
     * - `has_prerequisites`, `grants_proficiency`, `grants_skill`
     *
     * **Use Cases:**
     * - Character Optimization: Find combat feats for martial builds
     * - Build Planning: Identify magic feats for spellcasters
     * - ASI Decisions: Compare feat benefits vs +2 ability score increase
     * - Source Filtering: Find feats from specific sourcebooks
     *
     * **Data Source:**
     * - D&D 5e feats from PHB, XGE, TCoE, and other sourcebooks
     * - Prerequisites (race, ability, proficiency) via entity_prerequisites polymorphic table
     * - Modifiers (ASI, skill bonuses) via modifiers table
     * - Proficiency grants (weapon, armor, tool, skill) via proficiencies table
     *
     * See `docs/API-EXAMPLES.md` for comprehensive usage examples.
     *
     * @param  FeatIndexRequest  $request  Validated request with filtering parameters
     * @param  FeatSearchService  $service  Service layer for feat queries
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression for advanced filtering. Supports operators: =, !=, >, >=, <, <=, AND, OR, IN. Available fields: tag_slugs (array), source_codes (array), id (int), slug (string). Legacy MySQL parameters (prerequisite_race, prerequisite_ability, etc.) are deprecated.', example: 'tag_slugs IN [combat, magic]')]
    public function index(FeatIndexRequest $request, FeatSearchService $service)
    {
        $dto = FeatSearchDTO::fromRequest($request);

        if ($dto->searchQuery !== null) {
            // Scout search - paginate first, then eager-load relationships
            $feats = $service->buildScoutQuery($dto->searchQuery)->paginate($dto->perPage);
            $feats->load($service->getDefaultRelationships());
        } else {
            // Database query - relationships already eager-loaded via with()
            $feats = $service->buildDatabaseQuery($dto)->paginate($dto->perPage);
        }

        return FeatResource::collection($feats);
    }

    /**
     * Get a single feat
     *
     * Returns detailed information about a specific feat including modifiers, proficiencies,
     * conditions, prerequisites, and source citations. Supports selective relationship loading.
     */
    public function show(FeatShowRequest $request, Feat $feat, EntityCacheService $cache, FeatSearchService $service)
    {
        $validated = $request->validated();

        // Default relationships from service
        $defaultRelationships = $service->getShowRelationships();

        // Try cache first
        $cachedFeat = $cache->getFeat($feat->id);

        if ($cachedFeat) {
            // If include parameter provided, use it; otherwise load defaults
            $includes = $validated['include'] ?? $defaultRelationships;
            $cachedFeat->load($includes);

            return new FeatResource($cachedFeat);
        }

        // Fallback to route model binding result (should rarely happen)
        $includes = $validated['include'] ?? $defaultRelationships;
        $feat->load($includes);

        return new FeatResource($feat);
    }
}
