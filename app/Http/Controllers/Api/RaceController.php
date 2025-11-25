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

class RaceController extends Controller
{
    /**
     * List all races and subraces
     *
     * Returns a paginated list of D&D 5e races and subraces. Supports filtering by
     * proficiencies, skills, languages, size, speed, and innate spells. Includes ability
     * score modifiers, racial traits, language options, and spellcasting abilities.
     * All query parameters are validated automatically.
     *
     * **Basic Filtering Examples:**
     * - All races: `GET /api/v1/races`
     * - By size: `GET /api/v1/races?size=S` (Small races like Halfling, Gnome)
     * - By ability bonus: `GET /api/v1/races?ability_bonus=INT` (races with INT bonus)
     * - By speed: `GET /api/v1/races?min_speed=35` (fast races like Wood Elf)
     * - With darkvision: `GET /api/v1/races?has_darkvision=true`
     * - By language: `GET /api/v1/races?speaks_language=Elvish`
     * - By skill proficiency: `GET /api/v1/races?grants_skill=Perception`
     * - Combined filters: `GET /api/v1/races?ability_bonus=INT&has_darkvision=true` (smart races with darkvision)
     *
     * **Spell Filtering Examples (Meilisearch):**
     * - Single spell: `GET /api/v1/races?filter=spell_slugs IN [misty-step]` (Eladrin)
     * - Drow spells: `GET /api/v1/races?filter=spell_slugs IN [dancing-lights, faerie-fire]` (ANY of these spells)
     * - Cantrip races: `GET /api/v1/races?filter=tag_slugs IN [innate-spellcasting]`
     *
     * **Note on Spell Filtering:**
     * Spell filtering now uses Meilisearch `?filter=` syntax exclusively.
     * Legacy parameters like `?spells=`, `?spell_level=`, `?has_innate_spells=` have been removed.
     * Use `?filter=spell_slugs IN [spell1, spell2]` instead.
     *
     * **Tag-Based Filtering Examples (Meilisearch):**
     * - Darkvision races: `GET /api/v1/races?filter=tag_slugs IN [darkvision]`
     * - Fey ancestry: `GET /api/v1/races?filter=tag_slugs IN [fey-ancestry]`
     * - Multiple tags (OR): `GET /api/v1/races?filter=tag_slugs IN [darkvision, fey-ancestry]`
     * - Combined filters: `GET /api/v1/races?filter=tag_slugs IN [darkvision] AND speed >= 35`
     *
     * **Use Cases:**
     * - Character optimization: Which races get free teleportation? (`?filter=spell_slugs IN [misty-step]`)
     * - Spell synergy: Races with innate spells (`?filter=tag_slugs IN [innate-spellcasting]`)
     * - Darkvision races: Races with darkvision (`?filter=tag_slugs IN [darkvision]`)
     * - Build planning: Races with specific traits for multiclass builds
     * - Rules lookup: Quick reference for racial spellcasting features
     *
     * **Query Parameters:**
     * - `ability_bonus` (string): Filter by ability score bonus (STR, DEX, CON, INT, WIS, CHA)
     * - `size` (string): Filter by creature size (T, S, M, L, H, G)
     * - `min_speed` (int): Filter by minimum walking speed (0-100)
     * - `has_darkvision` (bool): Filter races with darkvision trait
     * - `speaks_language` (string): Filter by spoken language
     * - `grants_skill` (string): Filter by skill proficiency granted
     * - `grants_proficiency` (string): Filter by general proficiency
     * - `grants_proficiency_type` (string): Filter by proficiency type/category
     * - `language_choice_count` (integer): Filter by number of language choices
     * - `grants_languages` (boolean): Filter races that grant any languages
     *
     * **Data Source:**
     * - 21 racial spell relationships across 13 races with innate spellcasting
     * - Spell filtering powered by `entity_spells` polymorphic table
     * - Case-insensitive spell slug matching for user-friendly queries
     *
     * **Examples of Racial Innate Spells:**
     * - Drow (Dark Elf): Dancing Lights (cantrip), Faerie Fire (1st level), Darkness (2nd level)
     * - Tiefling: Thaumaturgy (cantrip), Hellish Rebuke (1st level), Darkness (2nd level)
     * - High Elf: 1 wizard cantrip (player's choice from any wizard cantrip)
     * - Forest Gnome: Minor Illusion (cantrip)
     * - Eladrin: Misty Step (2nd level, usable once per short rest)
     *
     * **Related Endpoints:**
     * - `GET /api/v1/races/{id}/spells` - Get all innate spells for a specific race
     * - `GET /api/v1/spells/{id}/races` - Get all races that know a specific spell
     *
     * See `docs/API-EXAMPLES.md` for comprehensive usage examples and best practices.
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression for advanced filtering. Supports operators: =, !=, >, >=, <, <=, AND, OR, IN. Available fields: size_code (string), speed (int), has_darkvision (bool), darkvision_range (int), spell_slugs (array), tag_slugs (array).', example: 'speed >= 30 AND tag_slugs IN [darkvision]')]
    public function index(RaceIndexRequest $request, RaceSearchService $service)
    {
        $dto = RaceSearchDTO::fromRequest($request);

        if ($dto->searchQuery !== null) {
            // Scout search - paginate first, then eager-load relationships
            $races = $service->buildScoutQuery($dto)->paginate($dto->perPage);
            $races->load($service->getDefaultRelationships());
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
