<?php

namespace App\Http\Controllers\Api;

use App\DTOs\RaceSearchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\RaceIndexRequest;
use App\Http\Requests\RaceShowRequest;
use App\Http\Resources\RaceResource;
use App\Http\Resources\SpellResource;
use App\Models\Race;
use App\Services\Cache\EntityCacheService;
use App\Services\RaceSearchService;
use Dedoc\Scramble\Attributes\QueryParameter;
use MeiliSearch\Client as MeilisearchClient;

class RaceController extends Controller
{
    /**
     * List all races and subraces
     *
     * Returns a paginated list of D&D 5e races and subraces. Use `?filter=` for filtering and `?q=` for full-text search.
     *
     * **Common Examples:**
     * ```
     * GET /api/v1/races                                      # All races
     * GET /api/v1/races?filter=size_code = M                 # Medium races
     * GET /api/v1/races?filter=speed >= 35                   # Fast races (Wood Elf, etc.)
     * GET /api/v1/races?q=elf                                # Full-text search for "elf"
     * GET /api/v1/races?q=elf&filter=speed >= 35             # Search + filter combined
     * GET /api/v1/races?filter=tag_slugs IN [darkvision] AND speed >= 35  # Fast races with darkvision
     * ```
     *
     * **Tag Filtering:**
     * - Darkvision: `GET /api/v1/races?filter=tag_slugs IN [darkvision]`
     * - Fey ancestry: `GET /api/v1/races?filter=tag_slugs IN [fey-ancestry]`
     * - Innate spells: `GET /api/v1/races?filter=tag_slugs IN [innate-spellcasting]`
     *
     * **Filterable Fields:**
     * - `size_code` (string: T, S, M, L, H, G)
     * - `size_name` (string: Tiny, Small, Medium, Large, Huge, Gargantuan)
     * - `speed` (int: movement speed in feet)
     * - `is_subrace` (bool: true for subraces, false for base races)
     * - `parent_race_name` (string: parent race name for subraces)
     * - `tag_slugs` (array: darkvision, fey-ancestry, innate-spellcasting, etc.)
     * - `source_codes` (array: PHB, XGE, TCoE, etc.)
     *
     * **Operators:**
     * - Comparison: `=`, `!=`, `>`, `>=`, `<`, `<=`
     * - Logic: `AND`, `OR`
     * - Membership: `IN [value1, value2, ...]`
     *
     * **Query Parameters:**
     * - `q` (string): Full-text search (searches name, size name, parent race name, sources)
     * - `filter` (string): Meilisearch filter expression
     * - `sort_by` (string): name, speed (default: name)
     * - `sort_direction` (string): asc, desc (default: asc)
     * - `per_page` (int): 1-100 (default: 15)
     * - `page` (int): Page number (default: 1)
     *
     * **Use Cases:**
     * - Character creation: Find races with specific traits (`?filter=tag_slugs IN [innate-spellcasting]`)
     * - Build optimization: Fast races (`?filter=speed >= 35`)
     * - Source filtering: Races from specific books (`?filter=source_codes IN [XGE]`)
     *
     * **Data Source:**
     * - 21 racial spell relationships across 13 races with innate spellcasting
     * - Examples: Drow (Dancing Lights, Faerie Fire, Darkness), Tiefling (Thaumaturgy, Hellish Rebuke), Eladrin (Misty Step)
     *
     * **Related Endpoints:**
     * - `GET /api/v1/races/{id}/spells` - Get all innate spells for a specific race
     * - `GET /api/v1/spells/{id}/races` - Get all races that know a specific spell
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression for advanced filtering. Supports operators: =, !=, >, >=, <, <=, AND, OR, IN. Available fields: size_code (string: T, S, M, L, H, G), size_name (string), speed (int), is_subrace (bool), parent_race_name (string), tag_slugs (array), source_codes (array).', example: 'speed >= 30 AND tag_slugs IN [darkvision]')]
    public function index(RaceIndexRequest $request, RaceSearchService $service, MeilisearchClient $meilisearch)
    {
        $dto = RaceSearchDTO::fromRequest($request);

        // Always use Meilisearch when filter or search query is present
        if ($dto->meilisearchFilter !== null || $dto->searchQuery !== null) {
            $races = $service->searchWithMeilisearch($dto, $meilisearch);
        } else {
            // Database query - relationships already eager-loaded via with()
            $races = $service->buildDatabaseQuery($dto)->paginate($dto->perPage);
        }

        return RaceResource::collection($races);
    }

    /**
     * Get a single race
     *
     * Returns detailed information about a specific race or subrace including parent race,
     * subraces, ability modifiers, proficiencies, traits, languages, and spells.
     * Supports selective relationship loading via the 'include' parameter.
     */
    public function show(RaceShowRequest $request, Race $race, EntityCacheService $cache, RaceSearchService $service)
    {
        $validated = $request->validated();

        // Default relationships from service
        $defaultRelationships = $service->getShowRelationships();

        // Try cache first
        $cachedRace = $cache->getRace($race->id);

        if ($cachedRace) {
            // If include parameter provided, use it; otherwise load defaults
            $includes = $validated['include'] ?? $defaultRelationships;
            $cachedRace->load($includes);

            return new RaceResource($cachedRace);
        }

        // Fallback to route model binding result (should rarely happen)
        $includes = $validated['include'] ?? $defaultRelationships;
        $race->load($includes);

        return new RaceResource($race);
    }

    /**
     * Get innate spells for a race
     *
     * Returns all innate spells granted by a specific race or subrace,
     * sorted by spell level and then alphabetically. Includes spell school
     * information for filtering and categorization.
     *
     * **Use Cases:**
     * - Character creation: View all spells this race grants
     * - Build planning: Compare innate spellcasting between races
     * - Rules reference: Quick lookup of racial spell access
     * - API integration: Programmatic access to racial spell lists
     *
     * **Examples of Racial Innate Spells:**
     * - Drow: Dancing Lights (0), Faerie Fire (1), Darkness (2)
     * - Tiefling: Thaumaturgy (0), Hellish Rebuke (1), Darkness (2)
     * - High Elf: 1 wizard cantrip (player's choice)
     * - Forest Gnome: Minor Illusion (0)
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function spells(Race $race)
    {
        $race->load(['entitySpells' => function ($query) {
            $query->orderBy('level')->orderBy('name');
        }, 'entitySpells.spellSchool']);

        return SpellResource::collection($race->entitySpells);
    }
}
