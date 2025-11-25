<?php

namespace App\Http\Controllers\Api;

use App\DTOs\BackgroundSearchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackgroundIndexRequest;
use App\Http\Requests\BackgroundShowRequest;
use App\Http\Resources\BackgroundResource;
use App\Models\Background;
use App\Services\BackgroundSearchService;
use App\Services\Cache\EntityCacheService;
use Dedoc\Scramble\Attributes\QueryParameter;

class BackgroundController extends Controller
{
    /**
     * List all backgrounds
     *
     * Returns a paginated list of D&D 5e character backgrounds. Use `?filter=` for filtering and `?q=` for full-text search.
     *
     * **Common Examples:**
     * ```
     * GET /api/v1/backgrounds                                # All backgrounds
     * GET /api/v1/backgrounds?filter=tag_slugs IN [criminal] # Criminal backgrounds
     * GET /api/v1/backgrounds?filter=source_codes IN [PHB]   # PHB backgrounds only
     * GET /api/v1/backgrounds?q=noble                        # Search for "noble"
     * GET /api/v1/backgrounds?filter=tag_slugs IN [criminal, noble] # Multiple tags
     * ```
     *
     * **Tag Filtering:**
     * - Criminal backgrounds: `GET /api/v1/backgrounds?filter=tag_slugs IN [criminal]`
     * - Noble backgrounds: `GET /api/v1/backgrounds?filter=tag_slugs IN [noble]`
     * - Outlander backgrounds: `GET /api/v1/backgrounds?filter=tag_slugs IN [outlander]`
     * - Multiple tags (OR): `GET /api/v1/backgrounds?filter=tag_slugs IN [criminal, outlander]`
     *
     * **Source Filtering:**
     * - PHB only: `GET /api/v1/backgrounds?filter=source_codes IN [PHB]`
     * - SCAG only: `GET /api/v1/backgrounds?filter=source_codes IN [SCAG]`
     * - Multiple sources: `GET /api/v1/backgrounds?filter=source_codes IN [PHB, XGE]`
     *
     * **Filterable Fields:**
     * - `id` (int), `slug` (string)
     * - `source_codes` (array: PHB, SCAG, XGE, TCoE, etc.)
     * - `tag_slugs` (array: criminal, noble, outlander, etc.)
     *
     * **Operators:**
     * - Comparison: `=`, `!=`
     * - Logic: `AND`, `OR`
     * - Membership: `IN [value1, value2, ...]`
     *
     * **Query Parameters:**
     * - `q` (string): Full-text search (searches name, description, traits)
     * - `filter` (string): Meilisearch filter expression
     * - `sort_by` (string): name, created_at, updated_at (default: name)
     * - `sort_direction` (string): asc, desc (default: asc)
     * - `per_page` (int): 1-100 (default: 15)
     * - `page` (int): Page number (default: 1)
     *
     * **Background Features:**
     * - Personality traits, ideals, bonds, flaws (via random tables)
     * - Starting equipment and feature descriptions
     * - Skill, tool, and language proficiencies
     * - Source attribution (PHB, SCAG, XGE, TCoE, etc.)
     *
     * @param  BackgroundIndexRequest  $request  Validated request with filtering parameters
     * @param  BackgroundSearchService  $service  Service layer for background queries
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression for advanced filtering. Supports operators: =, !=, AND, OR, IN. Available fields: id (int), slug (string), source_codes (array), tag_slugs (array).', example: 'tag_slugs IN [criminal, noble]')]
    public function index(BackgroundIndexRequest $request, BackgroundSearchService $service)
    {
        $dto = BackgroundSearchDTO::fromRequest($request);

        // Use Scout for full-text search, otherwise use database query
        if ($dto->searchQuery !== null) {
            // Scout search - paginate first, then eager-load relationships
            $backgrounds = $service->buildScoutQuery($dto->searchQuery)->paginate($dto->perPage);
            $backgrounds->load($service->getDefaultRelationships());
        } else {
            // Database query - relationships already eager-loaded via with()
            $backgrounds = $service->buildDatabaseQuery($dto)->paginate($dto->perPage);
        }

        return BackgroundResource::collection($backgrounds);
    }

    /**
     * Get a single background
     *
     * Returns detailed information about a specific background including proficiencies,
     * traits with random tables (personality, ideals, bonds, flaws), languages, and sources.
     * Supports selective relationship loading via the 'include' parameter.
     */
    public function show(BackgroundShowRequest $request, Background $background, EntityCacheService $cache, BackgroundSearchService $service)
    {
        $validated = $request->validated();

        // Default relationships from service
        $defaultRelationships = $service->getShowRelationships();

        // Try cache first
        $cachedBackground = $cache->getBackground($background->id);

        if ($cachedBackground) {
            // If include parameter provided, use it; otherwise load defaults
            $includes = $validated['include'] ?? $defaultRelationships;
            $cachedBackground->load($includes);

            return new BackgroundResource($cachedBackground);
        }

        // Fallback to route model binding result (should rarely happen)
        $includes = $validated['include'] ?? $defaultRelationships;
        $background->load($includes);

        return new BackgroundResource($background);
    }
}
