<?php

namespace App\Http\Controllers\Api;

use App\DTOs\BackgroundSearchDTO;
use App\Http\Controllers\Api\Concerns\AddsSearchableOptions;
use App\Http\Controllers\Api\Concerns\CachesEntityShow;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackgroundIndexRequest;
use App\Http\Requests\BackgroundShowRequest;
use App\Http\Resources\BackgroundResource;
use App\Models\Background;
use App\Services\BackgroundSearchService;
use App\Services\Cache\EntityCacheService;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use MeiliSearch\Client;

class BackgroundController extends Controller
{
    use AddsSearchableOptions;
    use CachesEntityShow;

    /**
     * List all backgrounds
     *
     * Returns a paginated list of D&D 5e character backgrounds. Use `?filter=` for filtering and `?q=` for full-text search.
     *
     * **Common Examples:**
     * ```
     * GET /api/v1/backgrounds                                    # All backgrounds (34 total)
     * GET /api/v1/backgrounds?filter=name = "Acolyte"            # Exact name match
     * GET /api/v1/backgrounds?filter=tag_slugs IN [criminal]     # Criminal backgrounds
     * GET /api/v1/backgrounds?filter=source_codes IN [PHB]       # PHB backgrounds only
     * GET /api/v1/backgrounds?q=noble                            # Full-text search
     * GET /api/v1/backgrounds?q=noble&filter=source_codes IN [PHB]  # Search + filter combined
     * GET /api/v1/backgrounds?filter=skill_proficiencies IN [Insight, Religion]  # Specific skills
     * GET /api/v1/backgrounds?filter=grants_language_choice = true  # Language choices
     * ```
     *
     * **Filterable Fields by Data Type:**
     *
     * **Integer Fields** (Operators: `=`, `!=`, `>`, `>=`, `<`, `<=`):
     * - `id` (int): Background ID
     *   - Examples: `id = 5`, `id >= 10`
     *
     * **String Fields** (Operators: `=`, `!=`):
     * - `name` (string): Background name (e.g., "Acolyte", "Criminal", "Noble", "Soldier")
     *   - Examples: `name = "Acolyte"`, `name != "Soldier"`
     *   - Use Case: Direct filtering by background name
     * - `slug` (string): URL-friendly identifier (e.g., "acolyte", "criminal", "noble")
     *   - Examples: `slug = "acolyte"`, `slug != "criminal"`
     *
     * **Boolean Fields** (Operators: `=`, `!=`, `IS NULL`, `EXISTS`):
     * - `grants_language_choice` (bool): Whether this background grants player-choice languages
     *   - Examples: `grants_language_choice = true`, `grants_language_choice = false`
     *   - Use Case: Find backgrounds that offer language flexibility
     *
     * **Array Fields** (Operators: `IN`, `NOT IN`, `IS EMPTY`):
     * - `source_codes` (array): Source book codes (PHB, SCAG, XGE, TCoE, etc.)
     *   - Examples: `source_codes IN [PHB]`, `source_codes IN [PHB, XGE]`, `source_codes NOT IN [UA]`
     *   - Use Case: Filter by campaign-allowed sourcebooks
     * - `tag_slugs` (array): Descriptive tag slugs (criminal, noble, outlander, sage, soldier, etc.)
     *   - Examples: `tag_slugs IN [criminal]`, `tag_slugs IN [criminal, noble]`
     *   - Use Case: Find backgrounds by archetype
     * - `skill_proficiencies` (array): Skill names granted by this background
     *   - Examples: `skill_proficiencies IN [Insight]`, `skill_proficiencies IN [Insight, Religion]`
     *   - Use Case: Fill skill gaps in party composition (e.g., "Need someone with Insight")
     * - `tool_proficiency_types` (array): Tool proficiency type names
     *   - Examples: `tool_proficiency_types IN [Thieves' Tools]`
     *   - Use Case: Find backgrounds for rogues/thieves
     *
     * **Complex Filter Examples:**
     * - Criminal backgrounds with Insight: `?filter=tag_slugs IN [criminal] AND skill_proficiencies IN [Insight]`
     * - Backgrounds with language choices from PHB: `?filter=grants_language_choice = true AND source_codes IN [PHB]`
     * - Non-PHB backgrounds: `?filter=source_codes NOT IN [PHB]`
     * - Multiple skill requirements: `?filter=skill_proficiencies IN [Insight, Religion] AND source_codes IN [PHB]`
     * - Search + filter: `?q=noble&filter=grants_language_choice = true`
     *
     * **Use Cases:**
     * - **Character Creation:** Find backgrounds that grant specific skill proficiencies needed for your build
     * - **Party Optimization:** Identify backgrounds that fill skill gaps (e.g., party needs Insight & Religion)
     * - **Roleplay Choices:** Filter by tags to find backgrounds matching character concept (criminal, noble, etc.)
     * - **Source Restrictions:** Limit to PHB-only for Adventurers League or homebrew campaigns
     * - **Language Flexibility:** Find backgrounds granting language choices for multilingual characters
     *
     * **Operator Reference:**
     * See `docs/MEILISEARCH-FILTER-OPERATORS.md` for comprehensive operator documentation.
     *
     * **Query Parameters:**
     * - `q` (string): Full-text search (searches name, description, traits)
     * - `filter` (string): Meilisearch filter expression
     * - `sort_by` (string): name, created_at, updated_at (default: name)
     * - `sort_direction` (string): asc, desc (default: asc)
     * - `per_page` (int): 1-100 (default: 15)
     * - `page` (int): Page number (default: 1)
     *
     * @param  BackgroundIndexRequest  $request  Validated request with filtering parameters
     * @param  BackgroundSearchService  $service  Service layer for background queries
     * @param  Client  $meilisearch  Meilisearch client for advanced filtering
     *
     * @response AnonymousResourceCollection<BackgroundResource>
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression. Integer fields (=,!=,>,>=,<,<=): id. String fields (=,!=): name, slug. Boolean fields (=,!=,IS NULL): grants_language_choice. Array fields (IN,NOT IN,IS EMPTY): source_codes, tag_slugs, skill_proficiencies, tool_proficiency_types. See docs/MEILISEARCH-FILTER-OPERATORS.md for details.', example: 'skill_proficiencies IN [Insight, Religion] AND source_codes IN [PHB]')]
    public function index(BackgroundIndexRequest $request, BackgroundSearchService $service, Client $meilisearch): AnonymousResourceCollection
    {
        $dto = BackgroundSearchDTO::fromRequest($request);

        // Use Meilisearch for ANY search query or filter expression
        // This enables filter-only queries without requiring ?q= parameter
        if ($dto->searchQuery !== null || $dto->meilisearchFilter !== null) {
            $backgrounds = $service->searchWithMeilisearch($dto, $meilisearch);
        } else {
            // Database query for pure pagination (no search/filter)
            $backgrounds = $service->buildDatabaseQuery($dto)->paginate($dto->perPage);
        }

        return $this->withSearchableOptions(
            BackgroundResource::collection($backgrounds),
            Background::class
        );
    }

    /**
     * Get a single background
     *
     * Returns detailed information about a specific background including proficiencies,
     * traits with random tables (personality, ideals, bonds, flaws), languages, and sources.
     * Supports selective relationship loading via the 'include' parameter.
     */
    public function show(BackgroundShowRequest $request, Background $background, EntityCacheService $cache, BackgroundSearchService $service): BackgroundResource
    {
        return $this->showWithCache(
            request: $request,
            entity: $background,
            cache: $cache,
            cacheMethod: 'getBackground',
            resourceClass: BackgroundResource::class,
            defaultRelationships: $service->getShowRelationships()
        );
    }
}
