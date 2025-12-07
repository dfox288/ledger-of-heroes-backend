<?php

namespace App\Http\Controllers\Api;

use App\DTOs\MonsterSearchDTO;
use App\Http\Controllers\Api\Concerns\AddsSearchableOptions;
use App\Http\Controllers\Api\Concerns\CachesEntityShow;
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
    use AddsSearchableOptions;
    use CachesEntityShow;

    /**
     * List all monsters
     *
     * Returns a paginated list of 598 D&D 5e monsters. Use `?filter=` for filtering and `?q=` for full-text search.
     *
     * **Common Examples:**
     * ```
     * GET /api/v1/monsters                                       # All monsters
     * GET /api/v1/monsters?filter=challenge_rating >= 20         # Boss fights (CR 20+)
     * GET /api/v1/monsters?filter=type = dragon                  # Dragons only
     * GET /api/v1/monsters?filter=is_spellcaster = true          # Spellcasting monsters
     * GET /api/v1/monsters?filter=has_legendary_actions = true   # Legendary creatures
     * GET /api/v1/monsters?filter=armor_class >= 18              # High AC tanks
     * GET /api/v1/monsters?filter=speed_fly > 0                  # Flying creatures
     * GET /api/v1/monsters?q=dragon&filter=challenge_rating >= 15 # Search + filter combined
     * ```
     *
     * **Filterable Fields by Data Type:**
     *
     * **Integer Fields** (Operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `TO`):
     * - `id` (int): Monster ID
     * - `challenge_rating` (float): Challenge rating as numeric value (0.125 = 1/8, 0.25 = 1/4, 0.5 = 1/2, 1.0-30.0)
     *   - Examples: `challenge_rating = 5`, `challenge_rating >= 10`, `challenge_rating 5 TO 15`
     *   - Use case: Balance encounters by party level
     * - `armor_class` (int): Natural or worn armor value (10-25 typical range)
     *   - Examples: `armor_class >= 18`, `armor_class < 15`
     *   - Use case: Tank enemies for melee-focused parties
     * - `hit_points_average` (int): Average hit points (1-676 range, Ancient Red Dragon = 546)
     *   - Examples: `hit_points_average > 100`, `hit_points_average >= 200`
     *   - Use case: High-HP enemies for endurance encounters
     * - `experience_points` (int): XP awarded on defeat (10-155000 range)
     *   - Examples: `experience_points >= 10000`, `experience_points < 1000`
     *   - Use case: Calculate encounter difficulty
     * - `strength` (int): Strength ability score (1-30 range)
     *   - Examples: `strength >= 20`, `strength < 10`
     *   - Use case: Grappling/shoving challenges
     * - `dexterity` (int): Dexterity ability score (1-30 range)
     *   - Examples: `dexterity >= 18`, `dexterity <= 8`
     *   - Use case: Initiative order and AC calculations
     * - `constitution` (int): Constitution ability score (1-30 range)
     *   - Examples: `constitution >= 18`
     *   - Use case: Concentration save bonuses for spellcasters
     * - `intelligence` (int): Intelligence ability score (1-30 range)
     *   - Examples: `intelligence >= 16`, `intelligence <= 3`
     *   - Use case: Investigation checks and INT saves
     * - `wisdom` (int): Wisdom ability score (1-30 range)
     *   - Examples: `wisdom >= 18`, `wisdom < 10`
     *   - Use case: Perception checks and WIS saves
     * - `charisma` (int): Charisma ability score (1-30 range)
     *   - Examples: `charisma >= 16`
     *   - Use case: Social encounters and CHA saves
     * - `speed_walk` (int): Walking speed in feet (0-120 typical range)
     *   - Examples: `speed_walk >= 40`, `speed_walk = 0`
     *   - Use case: Chase scenes and tactical positioning
     * - `speed_fly` (int): Flying speed in feet (0-120 typical range)
     *   - Examples: `speed_fly > 0`, `speed_fly >= 60`
     *   - Use case: Aerial combat encounters
     * - `speed_swim` (int): Swimming speed in feet (0-90 typical range)
     *   - Examples: `speed_swim > 0`, `speed_swim >= 40`
     *   - Use case: Underwater encounters
     * - `speed_burrow` (int): Burrowing speed in feet (0-40 typical range)
     *   - Examples: `speed_burrow > 0`
     *   - Use case: Underground or ambush encounters
     * - `speed_climb` (int): Climbing speed in feet (0-40 typical range)
     *   - Examples: `speed_climb > 0`
     *   - Use case: Vertical terrain encounters
     * - `passive_perception` (int): Passive Perception score (6-30 typical range)
     *   - Examples: `passive_perception >= 20`, `passive_perception < 12`
     *   - Use case: Stealth challenges and surprise rounds
     * - `legendary_resistance_uses` (int): Number of legendary resistance uses per day (0-3)
     *   - Examples: `legendary_resistance_uses >= 1`
     *   - Use case: Boss fights requiring save-or-suck counters
     *
     * **String Fields** (Operators: `=`, `!=`):
     * - `slug` (string): URL-friendly monster identifier
     *   - Examples: `slug = ancient-red-dragon`, `slug != goblin`
     * - `type` (string): Creature type (aberration, beast, celestial, construct, dragon, elemental, fey, fiend, giant, humanoid, monstrosity, ooze, plant, undead)
     *   - Examples: `type = dragon`, `type = undead`, `type != humanoid`
     *   - Use case: Ranger favored enemy, spell targeting
     * - `size_code` (string): Size abbreviation (T=Tiny, S=Small, M=Medium, L=Large, H=Huge, G=Gargantuan)
     *   - Examples: `size_code = L`, `size_code = G`
     *   - Use case: Grapple size limits, space requirements
     * - `size_name` (string): Full size name (Tiny, Small, Medium, Large, Huge, Gargantuan)
     *   - Examples: `size_name = Large`, `size_name = Gargantuan`
     * - `alignment` (string): Alignment descriptor (lawful good, neutral evil, unaligned, etc.)
     *   - Examples: `alignment = lawful evil`, `alignment = unaligned`
     *   - Use case: Paladin/cleric detection spells
     * - `armor_type` (string): Armor description (natural armor, plate armor, etc.)
     *   - Examples: `armor_type = natural armor`, `armor_type = plate armor`
     *
     * **Response-Only Fields** (not filterable, included in response):
     * - `languages` (string|null): Languages the monster speaks or understands
     *   - Formats: "Common, Elvish", "Deep Speech, telepathy 120 ft.", "understands Common but can't speak"
     *   - Null for creatures without language (e.g., most beasts)
     *
     * **Boolean Fields** (Operators: `=`, `!=`, `IS NULL`, `EXISTS`):
     * - `has_legendary_actions` (bool): Has legendary actions (not lair actions)
     *   - Examples: `has_legendary_actions = true`, `has_legendary_actions = false`
     *   - Use case: Epic boss fights requiring action economy
     * - `has_lair_actions` (bool): Has lair actions (location-specific)
     *   - Examples: `has_lair_actions = true`
     *   - Use case: Home-field advantage encounters
     * - `is_spellcaster` (bool): Can cast spells (129 monsters total)
     *   - Examples: `is_spellcaster = true`, `is_spellcaster = false`
     *   - Use case: Counterspell-ready encounters
     * - `has_reactions` (bool): Has reaction abilities beyond opportunity attacks
     *   - Examples: `has_reactions = true`
     *   - Use case: Tactical combat with interrupt abilities
     * - `has_legendary_resistance` (bool): Has Legendary Resistance trait (ignore failed saves)
     *   - Examples: `has_legendary_resistance = true`
     *   - Use case: Boss fights requiring sustained pressure
     * - `has_magic_resistance` (bool): Has Magic Resistance trait (advantage on saves vs spells)
     *   - Examples: `has_magic_resistance = true`
     *   - Use case: Anti-caster encounters
     * - `can_hover` (bool): Can hover while flying (immune to prone while flying)
     *   - Examples: `can_hover = true`
     *   - Use case: Aerial combat without fall risk
     * - `is_npc` (bool): Named NPC rather than generic monster
     *   - Examples: `is_npc = true`, `is_npc = false`
     *   - Use case: Story characters vs random encounters
     *
     * **Array Fields** (Operators: `IN`, `NOT IN`, `IS EMPTY`):
     * - `source_codes` (array): Source book codes (MM, VGM, MTF, etc.)
     *   - Examples: `source_codes IN [MM, VGM]`, `source_codes NOT IN [UA]`
     *   - Use case: Campaign-specific monster selection
     * - `tag_slugs` (array): Tag slugs for categorization (undead, fire-immune, shapechanger, etc.)
     *   - Examples: `tag_slugs IN [undead]`, `tag_slugs IN [fire-immune, cold-immune]`
     *   - Use case: Thematic encounters and elemental resistance
     * - `spell_slugs` (array): Spell slugs this monster can cast (1,098 relationships for 129 spellcasters)
     *   - Examples: `spell_slugs IN [fireball]`, `spell_slugs IN [counterspell, dispel-magic]`
     *   - Use case: Counter-ready encounters and spell diversity
     *
     * **Complex Filter Examples:**
     * ```
     * # Boss fights: CR 20+, legendary actions, legendary resistance
     * ?filter=challenge_rating >= 20 AND has_legendary_actions = true AND has_legendary_resistance = true
     *
     * # Tank enemies: High AC + High HP for melee-focused parties
     * ?filter=armor_class >= 18 AND hit_points_average >= 150
     *
     * # Flying spellcasters: Aerial combat with magic
     * ?filter=speed_fly > 0 AND is_spellcaster = true
     *
     * # Speed demons: Fast movement for chase scenes (CR 5-15)
     * ?filter=speed_walk >= 50 AND challenge_rating >= 5 AND challenge_rating <= 15
     *
     * # Legendary dragons: Epic dragon encounters
     * ?filter=type = dragon AND has_legendary_actions = true
     *
     * # Underwater bosses: High CR + swim speed
     * ?filter=speed_swim > 0 AND challenge_rating >= 10
     *
     * # Anti-magic tanks: Magic resistance + high saves
     * ?filter=has_magic_resistance = true AND wisdom >= 16
     *
     * # Fireball casters: Specific spell filtering
     * ?filter=spell_slugs IN [fireball] AND challenge_rating >= 5
     *
     * # Low-CR undead horde: Zombie/skeleton encounters
     * ?filter=tag_slugs IN [undead] AND challenge_rating <= 1
     *
     * # Elite guards: High stats across the board (CR 10+)
     * ?filter=strength >= 18 AND dexterity >= 16 AND challenge_rating >= 10
     * ```
     *
     * **Use Cases:**
     * - **Boss Fight Design**: Find legendary creatures with resistance mechanics
     * - **CR Balancing**: Filter by challenge rating for party-appropriate encounters
     * - **Thematic Encounters**: Use type/tag filters for campaign themes (undead, dragons, devils)
     * - **Tactical Variety**: Combine ability scores and special traits for diverse combat
     * - **Environmental Encounters**: Match monsters to terrain (swim/fly/burrow speeds)
     * - **Spell Countermeasures**: Identify spellcasters with specific spells for strategic prep
     *
     * **Operator Reference:**
     * See `docs/MEILISEARCH-FILTER-OPERATORS.md` for comprehensive operator documentation.
     *
     * **Query Parameters:**
     * - `q` (string): Full-text search (searches name, description, type, alignment)
     * - `filter` (string): Meilisearch filter expression
     * - `sort_by` (string): name, armor_class, hit_points_average, challenge_rating, experience_points, speed_walk, strength, dexterity, passive_perception (default: name)
     * - `sort_direction` (string): asc, desc (default: asc)
     * - `per_page` (int): 1-100 (default: 15)
     * - `page` (int): Page number (default: 1)
     *
     * @param  MonsterIndexRequest  $request  Validated request with filtering parameters
     * @param  MonsterSearchService  $service  Service layer for monster queries
     * @param  Client  $meilisearch  Meilisearch client for advanced filtering
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression. Supports all operators by data type: Integer (=,!=,>,>=,<,<=,TO), String (=,!=), Boolean (=,!=,IS NULL,EXISTS), Array (IN,NOT IN,IS EMPTY). Fields: challenge_rating, armor_class, hit_points_average, experience_points, strength, dexterity, constitution, intelligence, wisdom, charisma, speed_walk, speed_fly, speed_swim, speed_burrow, speed_climb, passive_perception, legendary_resistance_uses, type, size_code, size_name, alignment, armor_type, slug, has_legendary_actions, has_lair_actions, is_spellcaster, has_reactions, has_legendary_resistance, has_magic_resistance, can_hover, is_npc, source_codes, tag_slugs, spell_slugs. See docs/MEILISEARCH-FILTER-OPERATORS.md for details.', example: 'challenge_rating >= 10 AND has_legendary_actions = true')]
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

        return $this->withSearchableOptions(
            MonsterResource::collection($monsters),
            Monster::class
        );
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
        return $this->showWithCache(
            request: $request,
            entity: $monster,
            cache: $cache,
            cacheMethod: 'getMonster',
            resourceClass: MonsterResource::class,
            defaultRelationships: $service->getShowRelationships()
        );
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
     *
     * @response AnonymousResourceCollection<SpellResource>
     */
    public function spells(Monster $monster)
    {
        $monster->load(['spells' => function ($query) {
            $query->orderBy('level')->orderBy('name');
        }, 'spells.spellSchool']);

        return SpellResource::collection($monster->spells);
    }
}
