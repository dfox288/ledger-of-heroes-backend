<?php

namespace App\Http\Controllers\Api;

use App\DTOs\MonsterSearchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\MonsterIndexRequest;
use App\Http\Requests\MonsterShowRequest;
use App\Http\Resources\MonsterResource;
use App\Http\Resources\SpellResource;
use App\Models\Monster;
use App\Services\Cache\EntityCacheService;
use App\Services\MonsterSearchService;
use Dedoc\Scramble\Attributes\QueryParameter;
use MeiliSearch\Client;

class MonsterController extends Controller
{
    /**
     * List all monsters
     *
     * Returns a paginated list of D&D 5e monsters with advanced filtering capabilities.
     * Supports spell filtering (AND/OR logic), spell level, spellcasting ability, challenge rating,
     * type, size, alignment, and full-text search.
     *
     * **Basic Examples:**
     * - All monsters: `GET /api/v1/monsters`
     * - By CR range: `GET /api/v1/monsters?min_cr=5&max_cr=10`
     * - By type: `GET /api/v1/monsters?type=dragon`
     *
     * **Spell Filtering Examples:**
     * - Single spell: `GET /api/v1/monsters?spells=fireball` (11 monsters)
     * - Multiple spells (AND): `GET /api/v1/monsters?spells=fireball,lightning-bolt` (3 monsters with BOTH)
     * - Multiple spells (OR): `GET /api/v1/monsters?spells=fireball,lightning-bolt&spells_operator=OR` (17 monsters with EITHER)
     * - Spell level: `GET /api/v1/monsters?spell_level=9` (legendary spellcasters with 9th level slots)
     * - Spellcasting ability: `GET /api/v1/monsters?spellcasting_ability=INT` (wizards, liches, archmages)
     *
     * **Combined Filter Examples:**
     * - CR + Spell: `GET /api/v1/monsters?min_cr=10&spells=fireball,teleport&spells_operator=AND` (mobile damage dealers)
     * - Type + Spell Level: `GET /api/v1/monsters?type=undead&spell_level=6` (undead necromancers)
     * - Search + Spells: `GET /api/v1/monsters?q=dragon&spells=fireball` (spellcasting dragons)
     *
     * **Use Cases:**
     * - Encounter Building: Find balanced enemies for party level
     * - Spell Tracking: Identify which monsters can counterspell, teleport, or summon
     * - Themed Campaigns: All fiends with fire spells, all undead spellcasters, etc.
     * - Boss Rush: Progressive difficulty with varied spell mechanics
     *
     * **Spells Operator:**
     * - `AND` (default): Monster must know ALL specified spells
     * - `OR`: Monster must know AT LEAST ONE of the specified spells
     *
     * **Spell Level (0-9):**
     * - `0` = Cantrips (unlimited use)
     * - `1-9` = Spell slot levels (higher = more powerful)
     *
     * **Spellcasting Ability:**
     * - `INT` = Arcane casters (Wizards, Archmages, Liches, Mind Flayers)
     * - `WIS` = Divine casters (Clerics, Druids, Monks)
     * - `CHA` = Charisma casters (Sorcerers, Warlocks, Bards)
     *
     * **Advanced Meilisearch Filter Examples:**
     * - CR range: `GET /api/v1/monsters?filter=challenge_rating >= 10 AND challenge_rating <= 15`
     * - High HP: `GET /api/v1/monsters?filter=hit_points_average > 100`
     * - Multiple ranges: `GET /api/v1/monsters?filter=challenge_rating >= 5 AND hit_points_average > 50 AND armor_class >= 15`
     * - Boss fights: `GET /api/v1/monsters?filter=challenge_rating >= 20 AND experience_points >= 25000`
     * - Weak monsters: `GET /api/v1/monsters?filter=challenge_rating <= 1 AND hit_points_average < 20`
     * - Tank enemies: `GET /api/v1/monsters?filter=armor_class >= 18 AND hit_points_average >= 100`
     *
     * **Tag-Based Filtering Examples:**
     * - All fiends: `GET /api/v1/monsters?filter=tag_slugs IN [fiend]`
     * - Fire-immune creatures: `GET /api/v1/monsters?filter=tag_slugs IN [fire-immune]`
     * - Fiends OR undead: `GET /api/v1/monsters?filter=tag_slugs IN [fiend, undead]`
     * - Fire-immune dragons: `GET /api/v1/monsters?filter=tag_slugs IN [fire-immune] AND type = dragon`
     * - High CR fiends: `GET /api/v1/monsters?filter=tag_slugs IN [fiend] AND challenge_rating IN [15, 16, 17, 18, 19, 20]`
     *
     * **Spell-Based Filtering Examples:**
     * - Fireball casters: `GET /api/v1/monsters?filter=spell_slugs IN [fireball]`
     * - Fireball OR Lightning Bolt: `GET /api/v1/monsters?filter=spell_slugs IN [fireball, lightning-bolt]`
     * - Spellcasting dragons: `GET /api/v1/monsters?filter=type = dragon AND spell_slugs IN [fireball, polymorph]`
     *
     * **Meilisearch Operators:**
     * - Comparison: `=`, `!=`, `>`, `>=`, `<`, `<=`
     * - Logic: `AND`, `OR`
     * - Membership: `IN [value1, value2]`
     *
     * See `docs/API-EXAMPLES.md` and `docs/MEILISEARCH-FILTERS.md` for comprehensive examples.
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression for advanced filtering. Supports operators: =, !=, >, >=, <, <=, AND, OR, IN. Available fields: challenge_rating (string), type (string), size_code (string), alignment (string), armor_class (int), hit_points_average (int), experience_points (int), spell_slugs (array), tag_slugs (array).', example: 'challenge_rating >= 5 AND challenge_rating <= 10 AND type = dragon')]
    public function index(MonsterIndexRequest $request, MonsterSearchService $service, Client $meilisearch)
    {
        $dto = MonsterSearchDTO::fromRequest($request);

        // Use new Meilisearch filter syntax if provided
        if ($dto->meilisearchFilter !== null) {
            $monsters = $service->searchWithMeilisearch($dto, $meilisearch);
        } elseif ($dto->searchQuery !== null) {
            // Scout search - paginate first, then eager-load relationships
            $monsters = $service->buildScoutQuery($dto)->paginate($dto->perPage);
            $monsters->load($service->getDefaultRelationships());
        } else {
            // Database query - relationships already eager-loaded via with()
            $monsters = $service->buildDatabaseQuery($dto)->paginate($dto->perPage);
        }

        return MonsterResource::collection($monsters);
    }

    /**
     * Get a single monster
     *
     * Returns detailed information about a specific monster including traits, actions,
     * legendary actions, spellcasting, modifiers, conditions, and source citations.
     * Supports selective relationship loading via the 'include' parameter.
     */
    public function show(MonsterShowRequest $request, Monster $monster, EntityCacheService $cache, MonsterSearchService $service)
    {
        $validated = $request->validated();

        // Default relationships from service
        $defaultRelationships = $service->getShowRelationships();

        // Try cache first
        $cachedMonster = $cache->getMonster($monster->id);

        if ($cachedMonster) {
            // If include parameter provided, use it; otherwise load defaults
            $includes = $validated['include'] ?? $defaultRelationships;
            $cachedMonster->load($includes);

            return new MonsterResource($cachedMonster);
        }

        // Fallback to route model binding result (should rarely happen)
        $includes = $validated['include'] ?? $defaultRelationships;
        $monster->load($includes);

        return new MonsterResource($monster);
    }

    /**
     * Get all spells for a specific monster
     *
     * Returns a collection of spells that the monster can cast, ordered by spell level then name.
     * Returns an empty collection for non-spellcasters.
     *
     * **Examples:**
     * - Lich spells: `GET /api/v1/monsters/lich/spells` (26 spells from cantrips to 9th level)
     * - Archmage spells: `GET /api/v1/monsters/archmage/spells` (22 spells)
     * - Flameskull spells: `GET /api/v1/monsters/flameskull/spells` (10 spells up to 5th level)
     *
     * **Spell Data Includes:**
     * - Spell name, level, school, description
     * - Casting time, range, components, duration
     * - Damage types, saving throws, attack rolls
     * - Concentration requirement, ritual casting
     * - Source citations
     *
     * **Use Cases:**
     * - Combat Preparation: "What can this boss do?"
     * - Spell List Comparison: Compare spellcasters for encounters
     * - DM Reference: Quick lookup during gameplay
     *
     * **Data Source:**
     * Powered by SpellcasterStrategy which syncs 1,098 spell relationships
     * across 129 spellcasting monsters with 100% match rate.
     */
    public function spells(Monster $monster)
    {
        $monster->load(['entitySpells' => function ($query) {
            $query->orderBy('level')->orderBy('name');
        }, 'entitySpells.spellSchool']);

        return SpellResource::collection($monster->entitySpells);
    }
}
