<?php

namespace App\Http\Controllers\Api;

use App\DTOs\FeatSearchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\FeatIndexRequest;
use App\Http\Requests\FeatShowRequest;
use App\Http\Resources\FeatResource;
use App\Models\Feat;
use App\Services\FeatSearchService;
use Dedoc\Scramble\Attributes\QueryParameter;

class FeatController extends Controller
{
    /**
     * List all feats
     *
     * Returns a paginated list of D&D 5e feats with comprehensive filtering capabilities.
     * Supports prerequisite filtering (race, ability score, proficiency), granted benefits
     * (skills, proficiencies, ability score increases), modifier tracking, and full-text search.
     * Feats provide character customization alternatives to ability score increases.
     *
     * **Basic Examples:**
     * - All feats: `GET /api/v1/feats`
     * - By race prerequisite: `GET /api/v1/feats?prerequisite_race=dwarf` (Squat Nimbleness)
     * - By ability prerequisite: `GET /api/v1/feats?prerequisite_ability=DEX` (Sharpshooter, Crossbow Expert)
     * - By min ability score: `GET /api/v1/feats?prerequisite_ability=STR&min_value=13` (Heavy Armor Master)
     * - Pagination: `GET /api/v1/feats?per_page=25&page=1`
     *
     * **Prerequisite Filtering Examples:**
     * - Race prerequisites: `GET /api/v1/feats?prerequisite_race=elf` (Elven Accuracy, Fey Teleportation)
     * - Ability score prerequisites: `GET /api/v1/feats?prerequisite_ability=INT&min_value=13` (Ritual Caster)
     * - Proficiency prerequisites: `GET /api/v1/feats?prerequisite_proficiency=heavy-armor` (Heavy Armor Master)
     * - No prerequisites: `GET /api/v1/feats?has_prerequisites=false` (accessible to all characters)
     * - Has prerequisites: `GET /api/v1/feats?has_prerequisites=true` (restricted by race/ability/prof)
     *
     * **Granted Benefit Filtering:**
     * - Skill proficiency grants: `GET /api/v1/feats?grants_skill=stealth` (Skulker, Stealthy)
     * - Proficiency grants: `GET /api/v1/feats?grants_proficiency=heavy-armor` (Heavily Armored)
     * - Weapon proficiency: `GET /api/v1/feats?grants_proficiency=longsword` (Weapon Master)
     * - Tool proficiency: `GET /api/v1/feats?grants_proficiency=thieves-tools` (Skilled)
     *
     * **Search Examples:**
     * - Search by name: `GET /api/v1/feats?q=war` (War Caster, Martial Adept)
     * - Search by description: `GET /api/v1/feats?q=spellcasting` (War Caster, Ritual Caster)
     * - Search by mechanic: `GET /api/v1/feats?q=advantage` (Lucky, Elven Accuracy)
     *
     * **Combined Filtering Examples:**
     * - Race + ability: `GET /api/v1/feats?prerequisite_race=elf&prerequisite_ability=DEX` (Elven Accuracy)
     * - Ability + grant: `GET /api/v1/feats?prerequisite_ability=STR&grants_proficiency=heavy-armor` (Heavily Armored)
     * - Search + filter: `GET /api/v1/feats?q=armor&grants_proficiency=heavy-armor` (armor feats)
     *
     * **Use Cases:**
     * - Character Optimization: Find feats matching race and class build (Elf DEX builds)
     * - Build Planning: Identify feats granting specific proficiencies to round out character
     * - ASI Decisions: Compare feat benefits vs +2 ability score increase at level 4/8/12/16/19
     * - Prerequisite Planning: Find feats character qualifies for based on race/ability scores
     * - Multiclass Synergies: Match feats with class features (War Caster for Sorcerer/Paladin)
     * - Min-Max Optimization: Filter by granted bonuses (Lucky, Great Weapon Master)
     *
     * **Query Parameters:**
     * - `q` (string): Full-text search term (searches name, description, text)
     * - `filter` (string): Meilisearch filter expression (limited - use legacy parameters)
     * - `prerequisite_race` (string): Filter by race prerequisite (elf, dwarf, halfling, etc.)
     * - `prerequisite_ability` (string): Filter by ability score prerequisite (STR, DEX, CON, INT, WIS, CHA)
     * - `min_value` (int 1-30): Minimum ability score value for prerequisite (13 is common)
     * - `prerequisite_proficiency` (string): Filter by proficiency prerequisite (heavy-armor, spellcasting)
     * - `has_prerequisites` (bool): Has any prerequisites (true) or accessible to all (false)
     * - `grants_proficiency` (string): Filter by granted proficiency (weapon, armor, tool)
     * - `grants_skill` (string): Filter by granted skill proficiency (stealth, insight, etc.)
     * - `sort_by` (string): Column to sort by (name, created_at, updated_at)
     * - `sort_direction` (string): Sort direction (asc, desc)
     * - `per_page` (int): Results per page (default 15, max 100)
     * - `page` (int): Page number (default 1)
     *
     * **Data Source:**
     * - D&D 5e feats from PHB, XGE, TCoE, and other sourcebooks
     * - Prerequisites (race, ability, proficiency) via entity_prerequisites polymorphic table
     * - Modifiers (ASI, skill bonuses) via modifiers table
     * - Proficiency grants (weapon, armor, tool, skill) via proficiencies table
     * - Conditions applied via entity_conditions table
     *
     * **Unique Features:**
     * - Ability score increases (ASI alternatives) - +1 to one or two ability scores
     * - Proficiency grants (weapon, armor, tool, skill) - expand character capabilities
     * - Conditional bonuses (advantage on attacks, bonus actions, reactions)
     * - Special abilities (Lucky rerolls, Sentinel opportunity attacks, Alert initiative)
     * - Prerequisites create restricted "prestige" feats (Elven Accuracy, Squat Nimbleness)
     *
     * **Common Ability Score Prerequisites:**
     * - 13+ for spellcasting feats (Ritual Caster, Magic Initiate requirements)
     * - 13+ for combat feats (Heavy Armor Master, Heavily Armored)
     * - No minimum for most feats (accessible at level 1 via Variant Human)
     *
     * See `docs/API-EXAMPLES.md` for comprehensive usage examples.
     *
     * @param  FeatIndexRequest  $request  Validated request with filtering parameters
     * @param  FeatSearchService  $service  Service layer for feat queries
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression for advanced filtering. Note: Prerequisites are stored relationally. Use legacy parameters (prerequisite_race, prerequisite_ability) for prerequisite filtering.', example: 'name = "War Caster"')]
    public function index(FeatIndexRequest $request, FeatSearchService $service)
    {
        $dto = FeatSearchDTO::fromRequest($request);

        if ($dto->searchQuery !== null) {
            $feats = $service->buildScoutQuery($dto->searchQuery)->paginate($dto->perPage);
        } else {
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
    public function show(FeatShowRequest $request, Feat $feat)
    {
        $validated = $request->validated();

        // Default relationships
        $with = [
            'sources.source',
            'modifiers.abilityScore',
            'modifiers.skill',
            'modifiers.damageType',
            'proficiencies.skill.abilityScore',
            'proficiencies.proficiencyType',
            'conditions',
            'prerequisites.prerequisite',
            'tags',
        ];

        // Use validated include parameter if provided
        if (isset($validated['include'])) {
            $with = $validated['include'];
        }

        $feat->load($with);

        return new FeatResource($feat);
    }
}
