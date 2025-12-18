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
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use MeiliSearch\Client;

class FeatController extends Controller
{
    use Concerns\AddsSearchableOptions;
    use Concerns\CachesEntityShow;

    /**
     * List all feats
     *
     * Returns a paginated list of 138 D&D 5e feats. Use `?filter=` for filtering and `?q=` for full-text search.
     *
     * **Common Examples:**
     * ```
     * GET /api/v1/feats                                              # All feats
     * GET /api/v1/feats?filter=has_prerequisites = false             # Feats without prerequisites (18 feats)
     * GET /api/v1/feats?filter=improved_abilities IN [STR]           # STR-boosting feats (Heavy Armor Master, Athlete)
     * GET /api/v1/feats?filter=prerequisite_types IN [Race]          # Race-specific feats (Elven Accuracy, Dwarven Fortitude)
     * GET /api/v1/feats?filter=grants_proficiencies = true           # Feats that grant proficiencies (Weapon Master, Skill Expert)
     * GET /api/v1/feats?filter=tag_slugs IN [combat]                 # Combat feats
     * GET /api/v1/feats?q=advantage                                  # Full-text search for "advantage"
     * GET /api/v1/feats?q=spell&filter=improved_abilities IN [INT, WIS, CHA]  # Magic feats with ASI
     * ```
     *
     * **Filterable Fields by Data Type:**
     *
     * **Integer Fields** (Operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `TO`):
     * - `id` (int): Feat ID
     *   - Examples: `id = 42`, `id >= 10`, `id 1 TO 50`
     *   - Use case: Direct reference or batch processing ranges
     *
     * **String Fields** (Operators: `=`, `!=`):
     * - `slug` (string): URL-friendly feat identifier
     *   - Examples: `slug = heavy-armor-master`, `slug != alert`
     *   - Use case: Direct lookup by slug for permalinks
     *
     * **Boolean Fields** (Operators: `=`, `!=`, `IS NULL`, `EXISTS`):
     * - `has_prerequisites` (bool): Feat has prerequisites (race, ability, or proficiency)
     *   - Examples: `has_prerequisites = false`, `has_prerequisites = true`
     *   - Use case: Find feats accessible at level 1 without requirements
     * - `grants_proficiencies` (bool): Feat grants weapon, armor, tool, or skill proficiencies
     *   - Examples: `grants_proficiencies = true`, `grants_proficiencies = false`
     *   - Use case: Find proficiency-granting feats for skill/equipment access
     * - `is_half_feat` (bool): Feat grants +1 to an ability score (half-feats)
     *   - Examples: `is_half_feat = true`, `is_half_feat = false`
     *   - Use case: Find feats for odd ability scores (15 STR → 16 STR + feat benefits)
     *
     * **Array Fields** (Operators: `IN`, `NOT IN`, `IS EMPTY`):
     * - `improved_abilities` (array): Ability codes improved by this feat (STR, DEX, CON, INT, WIS, CHA)
     *   - Examples: `improved_abilities IN [STR]`, `improved_abilities IN [INT, WIS, CHA]`, `improved_abilities IS EMPTY`
     *   - Use case: ASI decisions - find feats that boost specific abilities (Half-Feats)
     *   - Critical for: Heavy Armor Master (+1 STR), Resilient (+1 any), Fey Touched (+1 INT/WIS/CHA)
     * - `prerequisite_types` (array): Type of prerequisites required (Race, AbilityScore, ProficiencyType)
     *   - Examples: `prerequisite_types IN [Race]`, `prerequisite_types IN [AbilityScore]`, `prerequisite_types IS EMPTY`
     *   - Use case: Find race-locked feats (Elven Accuracy) or ability-gated feats (Heavy Armor Master requires STR 13)
     * - `tag_slugs` (array): Tag slugs categorizing feat purpose (combat, magic, skill-improvement, etc.)
     *   - Examples: `tag_slugs IN [combat]`, `tag_slugs IN [magic]`, `tag_slugs IS EMPTY`
     *   - Use case: Browse feats by theme or build archetype
     * - `source_codes` (array): Source book codes (PHB, XGE, TCoE, etc.)
     *   - Examples: `source_codes IN [PHB]`, `source_codes IN [XGE, TCoE]`, `source_codes NOT IN [UA]`
     *   - Use case: Filter by allowed sourcebooks for campaign restrictions
     *
     * **String Fields** (Operators: `=`, `!=`):
     * - `parent_feat_slug` (string|null): Parent slug for variant feats (Resilient, Elemental Adept, etc.)
     *   - Examples: `parent_feat_slug = resilient`, `parent_feat_slug = elemental-adept`
     *   - Use case: Group all variants of a feat together (e.g., all Resilient variants)
     *   - Note: Returns null for non-variant feats (Great Weapon Master, Lucky, etc.)
     *
     * **Complex Filter Examples:**
     * - STR combat feats: `?filter=improved_abilities IN [STR] AND tag_slugs IN [combat]`
     * - Feats without prerequisites: `?filter=has_prerequisites = false`
     * - Race-specific feats with ASI: `?filter=prerequisite_types IN [Race] AND improved_abilities IS NOT EMPTY`
     * - Magic feats with ability boosts: `?filter=tag_slugs IN [magic] AND improved_abilities IN [INT, WIS, CHA]`
     * - Proficiency-granting feats: `?filter=grants_proficiencies = true AND has_prerequisites = false`
     * - PHB-only feats without prerequisites: `?filter=source_codes IN [PHB] AND has_prerequisites = false`
     * - Combat feats with DEX or STR boost: `?filter=tag_slugs IN [combat] AND improved_abilities IN [STR, DEX]`
     * - Level 1 accessible ASI feats: `?filter=has_prerequisites = false AND improved_abilities IS NOT EMPTY`
     * - All half-feats: `?filter=is_half_feat = true`
     * - Half-feats with STR boost: `?filter=is_half_feat = true AND improved_abilities IN [STR]`
     * - All Resilient variants: `?filter=parent_feat_slug = resilient`
     * - All Elemental Adept variants: `?filter=parent_feat_slug = elemental-adept`
     *
     * **Use Cases:**
     * - **Character Building:** "Which feats boost STR and help in combat?" (`improved_abilities IN [STR] AND tag_slugs IN [combat]`)
     * - **ASI Decisions:** "Should I take +2 ASI or a half-feat?" (`improved_abilities IS NOT EMPTY`)
     * - **Race Synergy:** "What feats are exclusive to Elves?" (`prerequisite_types IN [Race]` + full-text search)
     * - **Level 1 Variant Human:** "Which feats can I take at level 1?" (`has_prerequisites = false`)
     * - **Build Planning:** "Find magic feats with INT boost" (`tag_slugs IN [magic] AND improved_abilities IN [INT]`)
     * - **Equipment Access:** "Which feats grant armor proficiencies?" (`grants_proficiencies = true` + search "armor")
     *
     * **Operator Reference:**
     * See `docs/MEILISEARCH-FILTER-OPERATORS.md` for comprehensive operator documentation.
     *
     * **Query Parameters:**
     * - `q` (string): Full-text search (searches name, description, prerequisites_text)
     * - `filter` (string): Meilisearch filter expression
     * - `sort_by` (string): name, created_at, updated_at (default: name)
     * - `sort_direction` (string): asc, desc (default: asc)
     * - `per_page` (int): 1-100 (default: 15)
     * - `page` (int): Page number (default: 1)
     *
     * @param  FeatIndexRequest  $request  Validated request with filtering parameters
     * @param  FeatSearchService  $service  Service layer for feat queries
     * @param  Client  $meilisearch  Meilisearch client for advanced filtering
     *
     * @response AnonymousResourceCollection<FeatResource>
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression. Supports all operators by data type: Integer (=,!=,>,>=,<,<=,TO), String (=,!=), Boolean (=,!=,IS NULL,EXISTS), Array (IN,NOT IN,IS EMPTY). Filterable fields: id, slug, source_codes, tag_slugs, has_prerequisites, grants_proficiencies, is_half_feat, improved_abilities, prerequisite_types, parent_feat_slug. See docs/MEILISEARCH-FILTER-OPERATORS.md for details.', example: 'is_half_feat = true AND improved_abilities IN [STR]')]
    public function index(FeatIndexRequest $request, FeatSearchService $service, Client $meilisearch): AnonymousResourceCollection
    {
        $dto = FeatSearchDTO::fromRequest($request);

        // Use Meilisearch for ANY search query or filter expression
        // This enables filter-only queries without requiring ?q= parameter
        if ($dto->searchQuery !== null || $dto->meilisearchFilter !== null) {
            $feats = $service->searchWithMeilisearch($dto, $meilisearch);
        } else {
            // Database query for pure pagination (no search/filter)
            $feats = $service->buildDatabaseQuery($dto)->paginate($dto->perPage);
        }

        return $this->withSearchableOptions(
            FeatResource::collection($feats),
            Feat::class
        );
    }

    /**
     * Get a single feat
     *
     * Returns detailed information about a specific feat including modifiers, proficiencies,
     * conditions, prerequisites, and source citations. Supports selective relationship loading.
     */
    public function show(FeatShowRequest $request, Feat $feat, EntityCacheService $cache, FeatSearchService $service): FeatResource
    {
        return $this->showWithCache(
            request: $request,
            entity: $feat,
            cache: $cache,
            cacheMethod: 'getFeat',
            resourceClass: FeatResource::class,
            defaultRelationships: $service->getShowRelationships()
        );
    }
}
