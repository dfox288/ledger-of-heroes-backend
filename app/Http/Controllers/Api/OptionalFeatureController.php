<?php

namespace App\Http\Controllers\Api;

use App\DTOs\OptionalFeatureSearchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\OptionalFeatureIndexRequest;
use App\Http\Requests\OptionalFeatureShowRequest;
use App\Http\Resources\OptionalFeatureResource;
use App\Models\OptionalFeature;
use App\Services\Cache\EntityCacheService;
use App\Services\OptionalFeatureSearchService;
use Dedoc\Scramble\Attributes\QueryParameter;
use MeiliSearch\Client;

class OptionalFeatureController extends Controller
{
    use Concerns\AddsSearchableOptions;
    use Concerns\CachesEntityShow;

    /**
     * List all optional features
     *
     * Returns a paginated list of D&D 5e optional features. Use `?filter=` for filtering and `?q=` for full-text search.
     *
     * **Common Examples:**
     * ```
     * GET /api/v1/optional-features                                       # All optional features
     * GET /api/v1/optional-features?filter=feature_type = eldritch_invocation  # Warlock invocations
     * GET /api/v1/optional-features?filter=feature_type = metamagic        # Sorcerer metamagic
     * GET /api/v1/optional-features?filter=class_slugs IN [warlock]       # Warlock features
     * GET /api/v1/optional-features?filter=level_requirement <= 5          # Low-level features
     * GET /api/v1/optional-features?filter=has_spell_mechanics = true      # Features with spell-like mechanics
     * GET /api/v1/optional-features?q=agonizing                            # Full-text search for "agonizing"
     * GET /api/v1/optional-features?q=fire&filter=feature_type = elemental_discipline  # Search + filter combined
     * ```
     *
     * **Filterable Fields by Data Type:**
     *
     * **Integer Fields** (Operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `TO`):
     * - `id` (int): Feature ID
     * - `level_requirement` (int): Minimum character/class level required
     *   - Examples: `level_requirement = 5`, `level_requirement >= 9`, `level_requirement 1 TO 10`
     * - `resource_cost` (int): Number of resource points required to use
     *   - Examples: `resource_cost = 2`, `resource_cost <= 3`
     *
     * **String Fields** (Operators: `=`, `!=`):
     * - `slug` (string): URL-friendly feature identifier
     * - `feature_type` (string): Type of optional feature (eldritch_invocation, metamagic, etc.)
     *   - Examples: `feature_type = eldritch_invocation`, `feature_type = metamagic`
     * - `resource_type` (string): Type of resource consumed (ki_points, sorcery_points, etc.)
     *   - Examples: `resource_type = ki_points`, `resource_type = sorcery_points`
     *
     * **Boolean Fields** (Operators: `=`, `!=`, `IS NULL`, `EXISTS`):
     * - `has_spell_mechanics` (bool): Feature has spell-like properties (casting time, range, etc.)
     *   - Examples: `has_spell_mechanics = true`, `has_spell_mechanics = false`
     *
     * **Array Fields** (Operators: `IN`, `NOT IN`, `IS EMPTY`):
     * - `class_slugs` (array): Class slugs that can use this optional feature
     *   - Examples: `class_slugs IN [warlock]`, `class_slugs IN [fighter, monk]`
     * - `subclass_names` (array): Subclass names that can use this feature
     *   - Examples: `subclass_names IN [Battle Master]`, `subclass_names IN [Way of the Four Elements]`
     * - `source_codes` (array): Source book codes (PHB, XGE, TCoE, etc.)
     *   - Examples: `source_codes IN [PHB]`, `source_codes NOT IN [UA]`
     * - `tag_slugs` (array): Tag slugs categorizing features
     *   - Examples: `tag_slugs IN [damage]`, `tag_slugs IS EMPTY`
     *
     * **Complex Filter Examples:**
     * - Low-level Warlock invocations: `?filter=feature_type = eldritch_invocation AND level_requirement <= 5`
     * - Monk elemental disciplines: `?filter=feature_type = elemental_discipline AND class_slugs IN [monk]`
     * - Features with spell mechanics: `?filter=has_spell_mechanics = true`
     * - Metamagic options: `?filter=feature_type = metamagic AND source_codes IN [PHB, TCoE]`
     * - Fighter maneuvers: `?filter=feature_type = maneuver AND class_slugs IN [fighter]`
     *
     * **Operator Reference:**
     * See `docs/MEILISEARCH-FILTER-OPERATORS.md` for comprehensive operator documentation.
     *
     * **Query Parameters:**
     * - `q` (string): Full-text search (searches name, description, prerequisite_text)
     * - `filter` (string): Meilisearch filter expression
     * - `sort_by` (string): name, level_requirement, resource_cost, created_at, updated_at (default: name)
     * - `sort_direction` (string): asc, desc (default: asc)
     * - `per_page` (int): 1-100 (default: 15)
     * - `page` (int): Page number (default: 1)
     *
     * @param  OptionalFeatureIndexRequest  $request  Validated request with filtering parameters
     * @param  OptionalFeatureSearchService  $service  Service layer for optional feature queries
     * @param  Client  $meilisearch  Meilisearch client for advanced filtering
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression. Supports all operators by data type: Integer (=,!=,>,>=,<,<=,TO), String (=,!=), Boolean (=,!=,IS NULL,EXISTS), Array (IN,NOT IN,IS EMPTY). See docs/MEILISEARCH-FILTER-OPERATORS.md for details.', example: 'feature_type = eldritch_invocation AND level_requirement <= 5')]
    public function index(OptionalFeatureIndexRequest $request, OptionalFeatureSearchService $service, Client $meilisearch)
    {
        $dto = OptionalFeatureSearchDTO::fromRequest($request);

        // Use Meilisearch for ANY search query or filter expression
        // This enables filter-only queries without requiring ?q= parameter
        if ($dto->searchQuery !== null || $dto->meilisearchFilter !== null) {
            $features = $service->searchWithMeilisearch($dto, $meilisearch);
        } else {
            // Database query for pure pagination (no search/filter)
            $features = $service->buildDatabaseQuery($dto)->paginate($dto->perPage);
        }

        return $this->withSearchableOptions(
            OptionalFeatureResource::collection($features),
            OptionalFeature::class
        );
    }

    /**
     * Get a single optional feature
     *
     * Returns detailed information about a specific optional feature including relationships
     * like classes, sources, and tags. Supports selective relationship loading via the 'include' parameter.
     */
    public function show(OptionalFeatureShowRequest $request, OptionalFeature $optionalFeature, EntityCacheService $cache, OptionalFeatureSearchService $service)
    {
        return $this->showWithCache(
            request: $request,
            entity: $optionalFeature,
            cache: $cache,
            cacheMethod: 'getOptionalFeature',
            resourceClass: OptionalFeatureResource::class,
            defaultRelationships: $service->getShowRelationships()
        );
    }
}
