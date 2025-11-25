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
     * Returns a paginated list of D&D 5e monsters with advanced filtering via Meilisearch.
     * All filtering uses the `?filter=` parameter with Meilisearch syntax.
     *
     * **Basic Search:**
     * - Full-text search: `GET /api/v1/monsters?q=dragon`
     * - All monsters: `GET /api/v1/monsters`
     *
     * **Challenge Rating Filters:**
     * - Exact CR: `?filter=challenge_rating = 5`
     * - CR range: `?filter=challenge_rating >= 10 AND challenge_rating <= 20`
     * - Boss fights: `?filter=challenge_rating >= 20`
     *
     * **Type & Size Filters:**
     * - Dragons: `?filter=type = dragon`
     * - Large creatures: `?filter=size_code = L`
     * - Large dragons: `?filter=type = dragon AND size_code = L`
     *
     * **Combat Stats Filters:**
     * - High AC: `?filter=armor_class >= 18`
     * - High HP: `?filter=hit_points_average > 100`
     * - Tank enemies: `?filter=armor_class >= 18 AND hit_points_average >= 100`
     *
     * **Spell-Based Filters:**
     * - Fireball casters: `?filter=spell_slugs IN [fireball]`
     * - Multiple spells: `?filter=spell_slugs IN [fireball, lightning-bolt]`
     * - High-CR casters: `?filter=challenge_rating >= 10 AND spell_slugs IN [fireball]`
     * - Spellcasting dragons: `?filter=type = dragon AND spell_slugs IN [polymorph]`
     *
     * **Tag-Based Filters:**
     * - All undead: `?filter=tag_slugs IN [undead]`
     * - Fire-immune: `?filter=tag_slugs IN [fire-immune]`
     * - Undead OR fiend: `?filter=tag_slugs IN [undead, fiend]`
     *
     * **Combined Examples:**
     * - Powerful undead: `?filter=tag_slugs IN [undead] AND challenge_rating >= 10`
     * - Search + filter: `?q=dragon&filter=challenge_rating >= 15`
     * - Multi-condition: `?filter=type = fiend AND armor_class >= 15 AND hit_points_average > 100`
     *
     * **Available Filterable Fields:**
     * - Stats: `challenge_rating`, `armor_class`, `hit_points_average`, `experience_points`
     * - Type: `type`, `size_code`, `alignment`
     * - Abilities: `strength`, `dexterity`, `constitution`, `intelligence`, `wisdom`, `charisma`
     * - Speed: `speed_walk`, `speed_fly`, `speed_swim`, `speed_burrow`, `speed_climb`
     * - Arrays: `spell_slugs`, `tag_slugs`, `source_codes`
     * - Other: `passive_perception`, `can_hover`, `is_npc`
     *
     * **Operators:**
     * - Comparison: `=`, `!=`, `>`, `>=`, `<`, `<=`
     * - Logic: `AND`, `OR`
     * - Arrays: `IN [value1, value2]`
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression. Operators: =, !=, >, >=, <, <=, AND, OR, IN. Fields: challenge_rating, type, size_code, alignment, armor_class, hit_points_average, experience_points, strength, dexterity, constitution, intelligence, wisdom, charisma, speed_walk, speed_fly, passive_perception, spell_slugs, tag_slugs, source_codes, can_hover, is_npc.', example: 'challenge_rating >= 10 AND spell_slugs IN [fireball]')]
    public function index(MonsterIndexRequest $request, MonsterSearchService $service, Client $meilisearch)
    {
        $dto = MonsterSearchDTO::fromRequest($request);

        // Use Meilisearch for ANY search query or filter expression
        // This enables filter-only queries without requiring ?q= parameter
        if ($dto->searchQuery !== null || $dto->meilisearchFilter !== null) {
            $monsters = $service->searchWithMeilisearch($dto, $meilisearch);
        } else {
            // Database query for pure pagination (no search/filter)
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
