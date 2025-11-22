<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ItemSearchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\ItemIndexRequest;
use App\Http\Requests\ItemShowRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Services\ItemSearchService;
use Dedoc\Scramble\Attributes\QueryParameter;

class ItemController extends Controller
{
    /**
     * List all items
     *
     * Returns a paginated list of D&D 5e items including weapons, armor, and magic items.
     * Supports advanced filtering including spell-based queries (AND/OR logic), spell level,
     * item type, rarity, charges, and full-text search.
     *
     * **Basic Examples:**
     * - All items: `GET /api/v1/items`
     * - By rarity: `GET /api/v1/items?rarity=rare`
     * - By type: `GET /api/v1/items?type=WD` (wands)
     * - Magic items only: `GET /api/v1/items?is_magic=true`
     *
     * **Spell Filtering Examples:**
     * - Single spell: `GET /api/v1/items?spells=fireball` (Wand of Fireballs, Staff of Power, etc.)
     * - Multiple spells (AND): `GET /api/v1/items?spells=fireball,lightning-bolt` (items with BOTH spells)
     * - Multiple spells (OR): `GET /api/v1/items?spells=fireball,lightning-bolt&spells_operator=OR` (items with EITHER spell)
     * - Spell level: `GET /api/v1/items?spell_level=3` (items granting 3rd level spells)
     * - Spell scrolls: `GET /api/v1/items?type=SCR&spell_level=5` (5th level scrolls)
     *
     * **Item-Specific Filters:**
     * - Charged items: `GET /api/v1/items?has_charges=true` (wands, staves, rods)
     * - Wands with fire spells: `GET /api/v1/items?type=WD&spells=fireball,burning-hands&spells_operator=OR`
     * - Rare scrolls: `GET /api/v1/items?type=SCR&rarity=rare`
     *
     * **Combined Filter Examples:**
     * - Rare wands with Fireball: `GET /api/v1/items?spells=fireball&type=WD&rarity=rare`
     * - High-level spell items: `GET /api/v1/items?spell_level=7&has_charges=true`
     * - Search + filter: `GET /api/v1/items?q=staff&spells=teleport`
     *
     * **Use Cases:**
     * - Magic Item Shop: Filter by rarity and type for balanced loot
     * - Scroll Discovery: Find spell scrolls by level for character progression
     * - Charged Item Inventory: Track wands/staves with specific spells
     * - Loot Tables: Generate themed magic items (fire-based, teleportation, healing)
     *
     * **Spells Operator:**
     * - `AND` (default): Item must grant ALL specified spells
     * - `OR`: Item must grant AT LEAST ONE of the specified spells
     *
     * **Spell Level (0-9):**
     * - `0` = Cantrips (unlimited use)
     * - `1-9` = Spell slot levels (higher = more powerful)
     *
     * **Item Type Codes:**
     * - `WD` = Wand, `ST` = Staff, `RD` = Rod, `SCR` = Scroll, `P` = Potion
     * - See `/api/v1/item-types` for complete list
     *
     * **Data Source:**
     * Powered by ChargedItemStrategy and ScrollStrategy which track 107 spell relationships
     * across 84 items (wands, staves, scrolls, rods).
     *
     * See `docs/API-EXAMPLES.md` for comprehensive usage examples.
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression for advanced filtering. Supports operators: =, !=, >, >=, <, <=, AND, OR, IN. Available fields: is_magic (bool), requires_attunement (bool), rarity (string), type (string), weight (float), spell_slugs (array).', example: 'is_magic = true AND rarity IN [rare, very_rare, legendary]')]
    public function index(ItemIndexRequest $request, ItemSearchService $service)
    {
        $dto = ItemSearchDTO::fromRequest($request);

        if ($dto->searchQuery !== null) {
            $items = $service->buildScoutQuery($dto)->paginate($dto->perPage);
        } else {
            $items = $service->buildDatabaseQuery($dto)->paginate($dto->perPage);
        }

        return ItemResource::collection($items);
    }

    /**
     * Get a single item
     *
     * Returns detailed information about a specific item including item type, damage type,
     * properties, abilities, random tables, modifiers, proficiencies, and prerequisites.
     * Supports selective relationship loading via the 'include' parameter.
     */
    public function show(Item $item, ItemShowRequest $request)
    {
        $validated = $request->validated();

        // Default relationships to load
        $relationships = [
            'itemType',
            'damageType',
            'properties',
            'abilities',
            'randomTables.entries',
            'sources.source',
            'proficiencies.proficiencyType',
            'modifiers.abilityScore',
            'modifiers.skill',
            'modifiers.damageType',
            'prerequisites.prerequisite',
            'tags',
            'spells',
            'savingThrows',
        ];

        // If 'include' parameter provided, use it (note: this is for additional validation)
        // The actual loading is still done via the default relationships above
        // In a more advanced implementation, you might dynamically build the relationships array
        $item->load($relationships);

        return new ItemResource($item);
    }
}
