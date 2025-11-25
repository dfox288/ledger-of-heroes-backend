<?php

namespace App\Http\Controllers\Api;

use App\DTOs\SpellSearchDTO;
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
     * GET /api/v1/spells?filter=class_slugs IN [bard] AND level <= 3   # Low-level bard spells
     * ```
     *
     * **Damage Type Filtering:**
     * - Fire damage: `GET /api/v1/spells?filter=damage_types IN [F]`
     * - Force or radiant: `GET /api/v1/spells?filter=damage_types IN [O, R]`
     * - Fire cantrips: `GET /api/v1/spells?filter=damage_types IN [F] AND level = 0`
     * - Utility spells (no damage): `GET /api/v1/spells?filter=damage_types IS EMPTY`
     *
     * **Saving Throw Filtering:**
     * - DEX saves: `GET /api/v1/spells?filter=saving_throws IN [DEX]`
     * - WIS or CHA saves: `GET /api/v1/spells?filter=saving_throws IN [WIS, CHA]`
     * - Auto-hit spells (no saves): `GET /api/v1/spells?filter=saving_throws IS EMPTY`
     *
     * **Component Filtering:**
     * - Castable in Silence (no verbal): `GET /api/v1/spells?filter=requires_verbal = false`
     * - Castable while grappled (no somatic): `GET /api/v1/spells?filter=requires_somatic = false`
     * - Subtle Spell candidates: `GET /api/v1/spells?filter=requires_verbal = false AND requires_somatic = false`
     * - No components needed: `GET /api/v1/spells?filter=requires_verbal = false AND requires_somatic = false AND requires_material = false`
     *
     * **Filterable Fields:**
     * - `level` (0-9), `school_code` (EV, EN, AB, C, D, I, N, T), `school_name`
     * - `concentration` (bool), `ritual` (bool)
     * - `class_slugs` (array: wizard, cleric, bard, druid, etc.)
     * - `tag_slugs` (array: ritual-caster, touch-spells, etc.) - Only 22% of spells have tags
     * - `source_codes` (array: PHB, XGE, TCoE, etc.)
     * - `damage_types` (array: F, C, O, etc.)
     * - `saving_throws` (array: STR, DEX, CON, INT, WIS, CHA)
     * - `requires_verbal`, `requires_somatic`, `requires_material` (bool)
     *
     * **Operators:**
     * - Comparison: `=`, `!=`, `>`, `>=`, `<`, `<=`
     * - Logic: `AND`, `OR`
     * - Membership: `IN [value1, value2, ...]`
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
    #[QueryParameter('filter', description: 'Meilisearch filter expression for advanced filtering. Supports operators: =, !=, >, >=, <, <=, AND, OR, IN, IS EMPTY. Available fields: level (int), school_code/school_name (string), concentration/ritual (bool), class_slugs/tag_slugs/source_codes (array), damage_types (array: F, C, O, etc.), saving_throws (array: STR, DEX, CON, INT, WIS, CHA), requires_verbal/requires_somatic/requires_material (bool).', example: 'damage_types IN [F] AND level <= 3')]
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

        return SpellResource::collection($spells);
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
        $validated = $request->validated();

        // Default relationships from service
        $defaultRelationships = $service->getShowRelationships();

        // Try cache first
        $cachedSpell = $cache->getSpell($spell->id);

        if ($cachedSpell) {
            // If include parameter provided, use it; otherwise load defaults
            $includes = $validated['include'] ?? $defaultRelationships;
            $cachedSpell->load($includes);

            return new SpellResource($cachedSpell);
        }

        // Fallback to route model binding result (should rarely happen)
        $includes = $validated['include'] ?? $defaultRelationships;
        $spell->load($includes);

        return new SpellResource($spell);
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
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
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
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
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
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
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
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function races(Spell $spell)
    {
        $spell->load(['races' => function ($query) {
            $query->orderBy('name');
        }]);

        return RaceResource::collection($spell->races);
    }
}
