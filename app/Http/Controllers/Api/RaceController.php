<?php

namespace App\Http\Controllers\Api;

use App\DTOs\RaceSearchDTO;
use App\Http\Controllers\Api\Concerns\AddsSearchableOptions;
use App\Http\Controllers\Api\Concerns\CachesEntityShow;
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
    use AddsSearchableOptions;
    use CachesEntityShow;

    /**
     * List all races and subraces
     *
     * Returns a paginated list of 115 D&D 5e races and subraces. Use `?filter=` for filtering and `?q=` for full-text search.
     *
     * **Common Examples:**
     * ```
     * GET /api/v1/races                                              # All races
     * GET /api/v1/races?filter=ability_int_bonus >= 2                # Wizard races (High Elf, Gnome)
     * GET /api/v1/races?filter=ability_dex_bonus >= 2                # Rogue races (Wood Elf, Lightfoot Halfling)
     * GET /api/v1/races?filter=ability_str_bonus >= 1 AND ability_con_bonus >= 1  # Barbarian races
     * GET /api/v1/races?filter=speed >= 35                           # Fast races (35 ft)
     * GET /api/v1/races?filter=tag_slugs IN [darkvision]             # Races with darkvision
     * GET /api/v1/races?q=elf&filter=ability_dex_bonus >= 1          # Search + filter combined
     * ```
     *
     * **Filterable Fields by Data Type:**
     *
     * **Integer Fields** (Operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `TO`):
     * - `id` (int): Race ID
     * - `speed` (int): Base walking speed in feet (typically 25-35)
     *   - Examples: `speed = 30`, `speed >= 35`, `speed 25 TO 35`
     * - **`ability_str_bonus` (0-2)**: Strength bonus for martial characters
     *   - Examples: `ability_str_bonus >= 2` (Mountain Dwarf, Dragonborn), `ability_str_bonus >= 1` (Half-Orc)
     * - **`ability_dex_bonus` (0-2)**: Dexterity bonus for rogues, rangers, monks
     *   - Examples: `ability_dex_bonus >= 2` (Wood Elf, Lightfoot Halfling, Goblin), `ability_dex_bonus = 1`
     * - **`ability_con_bonus` (0-2)**: Constitution bonus for durability
     *   - Examples: `ability_con_bonus >= 2` (Hill Dwarf, Stout Halfling), `ability_con_bonus >= 1`
     * - **`ability_int_bonus` (0-2)**: Intelligence bonus for wizards, artificers
     *   - Examples: `ability_int_bonus >= 2` (High Elf, Gnome), `ability_int_bonus >= 1` (Tiefling)
     * - **`ability_wis_bonus` (0-2)**: Wisdom bonus for clerics, druids, rangers
     *   - Examples: `ability_wis_bonus >= 2` (Firbolg, Kalashtar), `ability_wis_bonus >= 1` (Wood Elf, Hill Dwarf)
     * - **`ability_cha_bonus` (0-2)**: Charisma bonus for bards, sorcerers, warlocks, paladins
     *   - Examples: `ability_cha_bonus >= 2` (Half-Elf, Tiefling, Dragonborn), `ability_cha_bonus >= 1` (Drow, Changeling)
     *
     * **String Fields** (Operators: `=`, `!=`):
     * - `slug` (string): URL-friendly identifier
     *   - Examples: `slug = high-elf`, `slug != human`
     * - `size_code` (string): Size code (T, S, M, L, H, G)
     *   - Examples: `size_code = M`, `size_code = S`
     * - `size_name` (string): Size name (Tiny, Small, Medium, Large, Huge, Gargantuan)
     *   - Examples: `size_name = Medium`, `size_name = Small`
     * - `parent_race_name` (string): Parent race name for subraces
     *   - Examples: `parent_race_name = Elf`, `parent_race_name = Dwarf`
     *
     * **Boolean Fields** (Operators: `=`, `!=`, `IS NULL`, `EXISTS`):
     * - `is_subrace` (bool): Whether this is a subrace
     *   - Examples: `is_subrace = true`, `is_subrace = false`
     * - `has_innate_spells` (bool): Whether race grants innate spellcasting
     *   - Examples: `has_innate_spells = true`, `has_innate_spells = false`
     *
     * **Array Fields** (Operators: `IN`, `NOT IN`, `IS EMPTY`):
     * - `source_codes` (array): Source book codes (PHB, XGE, TCoE, etc.)
     *   - Examples: `source_codes IN [PHB, XGE]`, `source_codes NOT IN [UA]`
     * - `tag_slugs` (array): Trait tags (darkvision, fey-ancestry, innate-spellcasting, etc.)
     *   - Examples: `tag_slugs IN [darkvision]`, `tag_slugs IN [fey-ancestry, innate-spellcasting]`
     * - `spell_slugs` (array): Innate spell slugs (13 races have innate spells)
     *   - Examples: `spell_slugs IN [misty-step]`, `spell_slugs IN [dancing-lights, faerie-fire, darkness]`
     *
     * **Complex Filter Examples:**
     * - Wizard races: `?filter=ability_int_bonus >= 2`
     * - Barbarian races: `?filter=ability_str_bonus >= 1 AND ability_con_bonus >= 1`
     * - Rogue/Dex races: `?filter=ability_dex_bonus >= 2`
     * - Charisma casters: `?filter=ability_cha_bonus >= 2`
     * - Fast darkvision races: `?filter=speed >= 35 AND tag_slugs IN [darkvision]`
     * - Races with teleportation: `?filter=spell_slugs IN [misty-step]`
     * - Medium-sized races with +2 Dex: `?filter=size_code = M AND ability_dex_bonus >= 2`
     * - Base races only: `?filter=is_subrace = false`
     * - Subraces of Elf: `?filter=parent_race_name = Elf`
     *
     * **Operator Reference:**
     * See `docs/MEILISEARCH-FILTER-OPERATORS.md` for comprehensive operator documentation.
     *
     * **Query Parameters:**
     * - `q` (string): Full-text search (searches name, size name, parent race name)
     * - `filter` (string): Meilisearch filter expression
     * - `sort_by` (string): name, speed, created_at, updated_at (default: name)
     * - `sort_direction` (string): asc, desc (default: asc)
     * - `per_page` (int): 1-100 (default: 15)
     * - `page` (int): Page number (default: 1)
     *
     * @param  RaceIndexRequest  $request  Validated request with filtering parameters
     * @param  RaceSearchService  $service  Service layer for race queries
     * @param  MeilisearchClient  $meilisearch  Meilisearch client for advanced filtering
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression. Supports all operators by data type: Integer (=,!=,>,>=,<,<=,TO), String (=,!=), Boolean (=,!=,IS NULL,EXISTS), Array (IN,NOT IN,IS EMPTY). See docs/MEILISEARCH-FILTER-OPERATORS.md for details.', example: 'ability_int_bonus >= 2 AND speed >= 30')]
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

        return $this->withSearchableOptions(
            RaceResource::collection($races),
            Race::class
        );
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
        return $this->showWithCache(
            request: $request,
            entity: $race,
            cache: $cache,
            cacheMethod: 'getRace',
            resourceClass: RaceResource::class,
            defaultRelationships: $service->getShowRelationships()
        );
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
     * @response array{data: array<int, array{id: int, slug: string, full_slug: string, name: string, level: int, school: array{id: int, name: string, code: string}|null}>}
     */
    public function spells(Race $race)
    {
        $race->load(['spells' => function ($query) {
            $query->orderBy('level')->orderBy('name');
        }, 'spells.spellSchool']);

        return SpellResource::collection($race->spells);
    }
}
