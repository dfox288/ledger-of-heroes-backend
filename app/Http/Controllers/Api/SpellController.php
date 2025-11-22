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
use App\Services\SpellSearchService;
use Dedoc\Scramble\Attributes\QueryParameter;
use MeiliSearch\Client;

class SpellController extends Controller
{
    /**
     * List all spells
     *
     * Returns a paginated list of D&D 5e spells with comprehensive filtering capabilities.
     * Supports spell level, school of magic, concentration/ritual status, damage types,
     * saving throws, component requirements, Meilisearch filters, and full-text search.
     *
     * **Basic Examples:**
     * - All spells: `GET /api/v1/spells`
     * - Cantrips (level 0): `GET /api/v1/spells?level=0` (67 cantrips)
     * - Low-level spells: `GET /api/v1/spells?level=1` (93 1st level spells)
     * - High-level spells: `GET /api/v1/spells?level=9` (17 legendary 9th level spells)
     * - Evocation school: `GET /api/v1/spells?school=3` (all evocation spells)
     * - Enchantment school: `GET /api/v1/spells?school=4` (all enchantment spells)
     * - Concentration spells: `GET /api/v1/spells?concentration=true` (buffing, control)
     * - Ritual spells: `GET /api/v1/spells?ritual=true` (non-combat utility spells)
     * - Pagination: `GET /api/v1/spells?per_page=50&page=2`
     *
     * **Damage Type Filtering Examples:**
     * - Fire spells: `GET /api/v1/spells?damage_type=fire` (Fireball, Burning Hands, Scorching Ray)
     * - Fire or cold: `GET /api/v1/spells?damage_type=fire,cold` (elemental versatility)
     * - Low-level fire: `GET /api/v1/spells?damage_type=fire&level=2` (early-game fire builds)
     * - Psychic damage: `GET /api/v1/spells?damage_type=psychic` (Mind Spike, Synaptic Static)
     * - Necrotic damage: `GET /api/v1/spells?damage_type=necrotic` (Blight, Vampiric Touch)
     * - Radiant damage: `GET /api/v1/spells?damage_type=radiant` (Guiding Bolt, Sunbeam)
     *
     * **Saving Throw Filtering Examples:**
     * - DEX saves: `GET /api/v1/spells?saving_throw=DEX` (Fireball, Lightning Bolt, Grease)
     * - DEX or CON: `GET /api/v1/spells?saving_throw=DEX,CON` (physical resistance tests)
     * - Mental saves (INT/WIS/CHA): `GET /api/v1/spells?saving_throw=INT,WIS,CHA` (mind-affecting)
     * - Enchantment WIS saves: `GET /api/v1/spells?saving_throw=WIS&school=4` (Charm Person, Dominate)
     * - STR saves: `GET /api/v1/spells?saving_throw=STR` (forced movement, restraints)
     * - CON saves: `GET /api/v1/spells?saving_throw=CON` (poison, disease, exhaustion)
     *
     * **Component Filtering Examples:**
     * - Silent casting (no verbal): `GET /api/v1/spells?requires_verbal=false` (Subtle Spell candidates)
     * - Subtle spell (no somatic): `GET /api/v1/spells?requires_somatic=false` (restrained casting)
     * - No material components: `GET /api/v1/spells?requires_material=false` (imprisoned casters)
     * - Verbal only: `GET /api/v1/spells?requires_verbal=true&requires_somatic=false&requires_material=false`
     * - Verbal + Somatic: `GET /api/v1/spells?requires_verbal=true&requires_somatic=true&requires_material=false`
     *
     * **Advanced Meilisearch Filtering:**
     * - Level range: `GET /api/v1/spells?filter=level >= 1 AND level <= 3`
     * - Multiple schools: `GET /api/v1/spells?filter=school_code = EV OR school_code = C`
     * - Concentration + level: `GET /api/v1/spells?filter=concentration = true AND level <= 2`
     *
     * **Combined Filtering Examples:**
     * - Low-level fire DEX saves: `GET /api/v1/spells?damage_type=fire&saving_throw=DEX&level=1` (Burning Hands)
     * - Silent enchantment spells: `GET /api/v1/spells?school=4&requires_verbal=false` (sneaky mind control)
     * - Material-free evocation: `GET /api/v1/spells?school=3&requires_material=false` (pure arcane damage)
     * - Low-level AOE ritual: `GET /api/v1/spells?level=2&ritual=true` (utility without slots)
     * - Search + filter: `GET /api/v1/spells?q=fire&level=3` (3rd level fire-themed spells)
     *
     * **Use Cases:**
     * - Character Building: Find spells for specific builds (fire mage, enchanter, healer)
     * - Combat Tactics: Identify spells targeting specific saves to exploit enemy weaknesses
     * - Stealth Gameplay: Silent spells for sneaky casters (Subtle Spell metamagic planning)
     * - Resource Management: Material-free spells for imprisoned or resource-limited casters
     * - Spell Comparison: Compare schools, levels, and damage types for optimization
     * - Metamagic Planning: Identify Subtle Spell, Twinned Spell, or Quicken Spell candidates
     * - DM Encounter Design: Find spells by CR-appropriate level and damage type
     * - Build Optimization: Filter by damage type + save to create synergistic spell lists
     *
     * **Query Parameters:**
     * - `q` (string): Full-text search term (searches name, description, higher_level_text)
     * - `filter` (string): Meilisearch filter expression (supports =, !=, >, >=, <, <=, AND, OR)
     * - `level` (int 0-9): Spell level (0 = cantrip, 1-9 = spell slots)
     * - `school` (int): Spell school ID (1=Abjuration, 2=Conjuration, 3=Evocation, 4=Enchantment, etc.)
     * - `concentration` (bool): Requires concentration (true = buffs/control, false = instant effects)
     * - `ritual` (bool): Can be cast as ritual (true = utility without spell slots)
     * - `damage_type` (string): Comma-separated damage types (fire, cold, lightning, necrotic, etc.)
     * - `saving_throw` (string): Comma-separated ability codes (STR, DEX, CON, INT, WIS, CHA)
     * - `requires_verbal` (bool): Requires verbal component (false = silent casting)
     * - `requires_somatic` (bool): Requires somatic component (false = hands-free casting)
     * - `requires_material` (bool): Requires material component (false = no components needed)
     * - `sort_by` (string): Column to sort by (name, level, created_at, updated_at)
     * - `sort_direction` (string): Sort direction (asc, desc)
     * - `per_page` (int): Results per page (default 15, max 100)
     * - `page` (int): Page number (default 1)
     *
     * **Available Damage Types:**
     * Fire, Cold, Lightning, Thunder, Acid, Poison, Necrotic, Radiant, Psychic, Force, Bludgeoning, Piercing, Slashing
     *
     * **Available Saving Throws:**
     * STR (Strength), DEX (Dexterity), CON (Constitution), INT (Intelligence), WIS (Wisdom), CHA (Charisma)
     *
     * **Spell School IDs:**
     * 1=Abjuration, 2=Conjuration, 3=Evocation, 4=Enchantment, 5=Illusion, 6=Divination, 7=Necromancy, 8=Transmutation
     *
     * **Data Source:**
     * - 477 spells across all D&D 5e sources (PHB, XGE, TCoE, etc.)
     * - Damage/effect filtering via spell_effects table
     * - Saving throw filtering via entity_saving_throws table
     * - Component filtering via components column
     * - 8 schools of magic with 1,917 class-spell relationships
     *
     * See `docs/API-EXAMPLES.md` for comprehensive usage examples.
     *
     * @param  SpellIndexRequest  $request  Validated request with filtering parameters
     * @param  SpellSearchService  $service  Service layer for spell queries
     * @param  Client  $meilisearch  Meilisearch client for advanced filtering
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression for advanced filtering. Supports operators: =, !=, >, >=, <, <=, AND, OR. Available fields: level (int), school_code (string), concentration (bool), ritual (bool).', example: 'level >= 1 AND level <= 3 AND school_code = EV')]
    public function index(SpellIndexRequest $request, SpellSearchService $service, Client $meilisearch)
    {
        $dto = SpellSearchDTO::fromRequest($request);

        // Use new Meilisearch filter syntax if provided
        if ($dto->meilisearchFilter !== null) {
            $spells = $service->searchWithMeilisearch($dto, $meilisearch);
        } elseif ($dto->searchQuery !== null) {
            // Use Scout search with backwards-compatible filters
            $spells = $service->buildScoutQuery($dto)->paginate($dto->perPage);
        } else {
            // Fallback to database query (no search, no filters)
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
    public function show(SpellShowRequest $request, Spell $spell)
    {
        $validated = $request->validated();

        // Load relationships based on validated 'include' parameter
        $includes = $validated['include'] ?? ['spellSchool', 'sources.source', 'effects.damageType', 'classes', 'tags', 'savingThrows', 'randomTables.entries'];
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
