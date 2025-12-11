<?php

namespace App\Http\Controllers\Api;

use App\DTOs\SpellSearchDTO;
use App\Http\Controllers\Api\Concerns\AddsSearchableOptions;
use App\Http\Controllers\Api\Concerns\CachesEntityShow;
use App\Http\Controllers\Controller;
use App\Http\Requests\SpellIndexRequest;
use App\Http\Requests\SpellShowRequest;
use App\Http\Resources\ClassResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\MonsterResource;
use App\Http\Resources\RaceResource;
use App\Http\Resources\SpellResource;
use App\Models\Spell;
use App\Services\Cache\EntityCacheService;
use App\Services\SpellSearchService;
use Dedoc\Scramble\Attributes\QueryParameter;
use MeiliSearch\Client;

class SpellController extends Controller
{
    use AddsSearchableOptions;
    use CachesEntityShow;

    /**
     * List all spells
     *
     * Returns a paginated list of 477 D&D 5e spells. Use `?filter=` for filtering and `?q=` for full-text search.
     *
     * **Common Examples:**
     * ```
     * GET /api/v1/spells                                    # All spells
     * GET /api/v1/spells?filter=level = 0                   # Cantrips (44 spells)
     * GET /api/v1/spells?filter=level <= 3                  # Low-level spells
     * GET /api/v1/spells?filter=school_code = EV            # Evocation spells
     * GET /api/v1/spells?filter=class_slugs IN [bard]       # Bard spells (147 spells)
     * GET /api/v1/spells?filter=concentration = true        # Concentration spells
     * GET /api/v1/spells?q=fire                             # Full-text search for "fire"
     * GET /api/v1/spells?q=fire&filter=level <= 3           # Search + filter combined
     * ```
     *
     * **Filterable Fields by Data Type:**
     *
     * **Integer Fields** (Operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `TO`):
     * - `id` (int): Spell ID
     * - `level` (0-9): Spell level (0 = cantrip)
     *   - Examples: `level = 3`, `level >= 5`, `level 1 TO 5`
     *
     * **String Fields** (Operators: `=`, `!=`):
     * - `school_code` (string): Two-letter spell school code (EV, EN, AB, C, D, I, N, T)
     *   - Examples: `school_code = EV`, `school_code != EV`
     * - `school_name` (string): Full spell school name (Evocation, Enchantment, etc.)
     * - `casting_time`, `range`, `duration` (string): Descriptive text fields
     *
     * **Boolean Fields** (Operators: `=`, `!=`, `IS NULL`, `EXISTS`):
     * - `concentration` (bool): Requires concentration
     *   - Examples: `concentration = true`, `concentration = false`
     * - `ritual` (bool): Can be cast as ritual
     * - `requires_verbal` (bool): Requires verbal component (castable in Silence when false)
     * - `requires_somatic` (bool): Requires somatic component (castable while grappled when false)
     * - `requires_material` (bool): Requires material component
     * - `material_consumed` (bool): Material component is consumed by the spell
     *   - Examples: `material_consumed = true`, `material_consumed = false`
     *   - Use case: Find reusable-focus vs consumed-component spells
     *
     * **Computed Fields** (Issues #27, #28):
     * - `material_cost_gp` (int): Gold piece cost of material components
     *   - Examples: `material_cost_gp >= 100`, `material_cost_gp EXISTS`
     *   - Use case: Find expensive spells, budget-friendly options
     * - `aoe_type` (string): Area of effect shape (cone, sphere, cube, line, cylinder)
     *   - Examples: `aoe_type = sphere`, `aoe_type = cone`
     *   - Use case: Find AoE spells by shape
     * - `aoe_size` (int): Primary dimension of area in feet
     *   - Examples: `aoe_size >= 20`, `aoe_size = 15`
     *   - Use case: Find large AoE spells
     *
     * **Array Fields** (Operators: `IN`, `NOT IN`, `IS EMPTY`):
     * - `class_slugs` (array): Class slugs that can learn this spell
     *   - Examples: `class_slugs IN [wizard, sorcerer]`, `class_slugs NOT IN [wizard]`
     * - `tag_slugs` (array): Tag slugs (Note: Only 22% of spells have tags)
     *   - Examples: `tag_slugs IN [fire]`, `tag_slugs IS EMPTY`
     * - `source_codes` (array): Source book codes (PHB, XGE, TCoE, etc.)
     *   - Examples: `source_codes IN [PHB, XGE]`, `source_codes NOT IN [UA]`
     * - `damage_types` (array): Damage type codes (F=Fire, C=Cold, O=Force, etc.)
     *   - Examples: `damage_types IN [F]`, `damage_types IS EMPTY` (utility spells)
     * - `saving_throws` (array): Ability codes (STR, DEX, CON, INT, WIS, CHA)
     *   - Examples: `saving_throws IN [DEX]`, `saving_throws IS EMPTY` (auto-hit spells)
     * - `effect_types` (array): Effect type strings
     *
     * **Complex Filter Examples:**
     * - Range query: `?filter=level >= 3 AND level <= 5` OR `?filter=level 3 TO 5`
     * - Multiple conditions: `?filter=class_slugs IN [wizard] AND level <= 3 AND concentration = true`
     * - Array membership: `?filter=damage_types IN [F, C] AND level > 0`
     * - Empty arrays: `?filter=damage_types IS EMPTY` (utility spells with no damage)
     * - Subtle Spell candidates: `?filter=requires_verbal = false AND requires_somatic = false`
     * - Expensive spells: `?filter=material_cost_gp >= 100`
     * - Consumed materials: `?filter=material_consumed = true AND material_cost_gp >= 50`
     * - Fireball-style spells: `?filter=aoe_type = sphere AND aoe_size >= 20`
     * - All cone spells: `?filter=aoe_type = cone`
     * - Large AoE spells: `?filter=aoe_size >= 30`
     *
     * **Operator Reference:**
     * See `docs/MEILISEARCH-FILTER-OPERATORS.md` for comprehensive operator documentation.
     *
     * **Query Parameters:**
     * - `q` (string): Full-text search (searches name, description)
     * - `filter` (string): Meilisearch filter expression
     * - `sort_by` (string): name, level, created_at, updated_at (default: name)
     * - `sort_direction` (string): asc, desc (default: asc)
     * - `per_page` (int): 1-100 (default: 15)
     * - `page` (int): Page number (default: 1)
     *
     * @param  SpellIndexRequest  $request  Validated request with filtering parameters
     * @param  SpellSearchService  $service  Service layer for spell queries
     * @param  Client  $meilisearch  Meilisearch client for advanced filtering
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression. Supports all operators by data type: Integer (=,!=,>,>=,<,<=,TO), String (=,!=), Boolean (=,!=,IS NULL,EXISTS), Array (IN,NOT IN,IS EMPTY). Filterable fields include: id, level, school_code, school_name, concentration, ritual, requires_verbal, requires_somatic, requires_material, class_slugs, tag_slugs, source_codes, damage_types, saving_throws, effect_types, material_cost_gp, material_consumed, aoe_type, aoe_size. See docs/MEILISEARCH-FILTER-OPERATORS.md for details.', example: 'aoe_type = sphere AND aoe_size >= 20')]
    public function index(SpellIndexRequest $request, SpellSearchService $service, Client $meilisearch)
    {
        $dto = SpellSearchDTO::fromRequest($request);

        // Use Meilisearch for ANY search query or filter expression
        // This enables filter-only queries without requiring ?q= parameter
        if ($dto->searchQuery !== null || $dto->meilisearchFilter !== null) {
            $spells = $service->searchWithMeilisearch($dto, $meilisearch);
        } else {
            // Database query for pure pagination (no search/filter)
            $spells = $service->buildDatabaseQuery($dto)->paginate($dto->perPage);
        }

        return $this->withSearchableOptions(
            SpellResource::collection($spells),
            Spell::class
        );
    }

    /**
     * Get a single spell
     *
     * Returns detailed information about a specific spell including relationships
     * like spell school, sources, damage effects, and associated classes.
     * Supports selective relationship loading via the 'include' parameter.
     */
    public function show(SpellShowRequest $request, Spell $spell, EntityCacheService $cache, SpellSearchService $service)
    {
        return $this->showWithCache(
            request: $request,
            entity: $spell,
            cache: $cache,
            cacheMethod: 'getSpell',
            resourceClass: SpellResource::class,
            defaultRelationships: $service->getShowRelationships()
        );
    }

    /**
     * Get all classes that can learn this spell
     *
     * Returns a list of D&D 5e character classes that have this spell in their spell list,
     * ordered alphabetically by class name. This includes both base classes and subclasses
     * that have access to the spell through their class spell lists.
     *
     * **Examples:**
     * - Wizard classes: `GET /api/v1/spells/fireball/classes`
     * - Healing classes: `GET /api/v1/spells/cure-wounds/classes`
     * - Cantrip classes: `GET /api/v1/spells/prestidigitation/classes`
     *
     * **Use Cases:**
     * - Character Building: "Can my Cleric learn this spell?"
     * - Multiclass Planning: "Which classes get access to Counterspell?"
     * - Spell Comparison: "Is this a Wizard-only spell or can multiple classes learn it?"
     * - Class Analysis: "How many classes can cast healing spells?"
     *
     * **Data Source:**
     * Powered by the `class_spells` pivot table which tracks 1,917 class-spell relationships
     * across 131 classes/subclasses and 477 spells imported from official D&D sourcebooks.
     *
     * @param  Spell  $spell  The spell to find classes for (accepts ID or slug)
     *
     * @response array{data: array<int, array{id: int, slug: string, full_slug: string, name: string, hit_die: int, is_base_class: bool}>}
     */
    public function classes(Spell $spell)
    {
        $spell->load(['classes' => function ($query) {
            $query->orderBy('name');
        }]);

        return ClassResource::collection($spell->classes);
    }

    /**
     * Get all monsters that can cast this spell
     *
     * Returns a list of D&D 5e monsters that can cast this spell, ordered alphabetically
     * by monster name. This includes spellcasting monsters like liches, archmages, dragons,
     * and other creatures with innate spellcasting or prepared spells.
     *
     * **Examples:**
     * - Fireball casters: `GET /api/v1/spells/fireball/monsters` (11 monsters including Lich, Archmage)
     * - Counterspell users: `GET /api/v1/spells/counterspell/monsters` (tactical spellcasters)
     * - Teleport users: `GET /api/v1/spells/teleport/monsters` (mobile bosses)
     *
     * **Use Cases:**
     * - Encounter Building: "Which monsters can use this spell against my party?"
     * - Boss Selection: "Find legendary spellcasters for high-level encounters"
     * - Spell Tracking: "Does this enemy have access to teleportation?"
     * - DM Reference: "Quick lookup of spell-using monsters for improvisation"
     *
     * **Data Source:**
     * Powered by the `entity_spells` polymorphic table which tracks 1,098 spell relationships
     * across 129 spellcasting monsters. Synced automatically by SpellcasterStrategy during
     * monster imports with 100% spell name match rate.
     *
     * @param  Spell  $spell  The spell to find monsters for (accepts ID or slug)
     *
     * @response array{data: array<int, array{id: int, slug: string, full_slug: string, name: string, challenge_rating: string|null, type: string|null}>}
     */
    public function monsters(Spell $spell)
    {
        $spell->load(['monsters' => function ($query) {
            $query->orderBy('name');
        }]);

        return MonsterResource::collection($spell->monsters);
    }

    /**
     * Get all magic items that contain this spell
     *
     * Returns a list of D&D 5e magic items that contain or can cast this spell, ordered
     * alphabetically by item name. This includes spell scrolls, charged items (staves, wands,
     * rods), and other magical equipment that grants access to spells.
     *
     * **Examples:**
     * - Fireball items: `GET /api/v1/spells/fireball/items` (Wand of Fireballs, Necklace of Fireballs)
     * - Healing items: `GET /api/v1/spells/cure-wounds/items` (Spell Scrolls, healing staves)
     * - Utility items: `GET /api/v1/spells/detect-magic/items` (wands, rods, scrolls)
     *
     * **Use Cases:**
     * - Treasure Generation: "What magic items grant access to this spell?"
     * - Item Identification: "The party found a wand - what spells can it cast?"
     * - Character Equipment: "Can I get this spell without multiclassing?"
     * - Economy Balancing: "How many items in the game provide teleportation?"
     *
     * **Data Source:**
     * Powered by the `entity_spells` polymorphic table which tracks 107 spell relationships
     * across charged items, spell scrolls, and magical equipment. Synced automatically by
     * ChargedItemStrategy during item imports using case-insensitive spell name matching.
     *
     * @param  Spell  $spell  The spell to find items for (accepts ID or slug)
     *
     * @response array{data: array<int, array{id: int, slug: string, full_slug: string, name: string, type: string|null, rarity: string|null}>}
     */
    public function items(Spell $spell)
    {
        $spell->load(['items' => function ($query) {
            $query->orderBy('name');
        }]);

        return ItemResource::collection($spell->items);
    }

    /**
     * Get all races that can cast this spell
     *
     * Returns a list of D&D 5e races and subraces that can cast this spell through racial
     * abilities, ordered alphabetically by race name. This includes innate spellcasting
     * granted by racial traits like Drow Magic, High Elf Cantrip, or Tiefling spells.
     *
     * **Examples:**
     * - Dancing Lights: `GET /api/v1/spells/dancing-lights/races` (Drow innate cantrip)
     * - Faerie Fire: `GET /api/v1/spells/faerie-fire/races` (Drow 3rd level racial spell)
     * - Prestidigitation: `GET /api/v1/spells/prestidigitation/races` (High Elf cantrip choice)
     *
     * **Use Cases:**
     * - Character Creation: "Can I get this spell from my race?"
     * - Build Optimization: "Which races grant access to utility cantrips?"
     * - Race Comparison: "What innate spellcasting do different races provide?"
     * - Campaign Balance: "How common are racial teleportation spells?"
     *
     * **Data Source:**
     * Powered by the `entity_spells` polymorphic table which tracks 21 spell relationships
     * across races and subraces, representing innate racial spellcasting abilities from
     * official D&D sourcebooks.
     *
     * @param  Spell  $spell  The spell to find races for (accepts ID or slug)
     *
     * @response array{data: array<int, array{id: int, slug: string, full_slug: string, name: string, speed: int|null, is_subrace: bool}>}
     */
    public function races(Spell $spell)
    {
        $spell->load(['races' => function ($query) {
            $query->orderBy('name');
        }]);

        return RaceResource::collection($spell->races);
    }
}
