<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ItemSearchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\ItemIndexRequest;
use App\Http\Requests\ItemShowRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Services\Cache\EntityCacheService;
use App\Services\ItemSearchService;
use Dedoc\Scramble\Attributes\QueryParameter;
use MeiliSearch\Client;

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
     * **Spell Filtering Examples (Meilisearch):**
     * - Single spell: `GET /api/v1/items?filter=spell_slugs IN [fireball]` (Wand of Fireballs, Staff of Power, etc.)
     * - Multiple spells (ANY): `GET /api/v1/items?filter=spell_slugs IN [fireball, lightning-bolt]` (items with EITHER spell)
     * - Spell scrolls: `GET /api/v1/items?filter=type_code = SCR AND spell_slugs IN [wish]` (high-level scrolls)
     *
     * **Item-Specific Filters:**
     * - Charged items: `GET /api/v1/items?has_charges=true` (wands, staves, rods)
     * - Rare scrolls: `GET /api/v1/items?type=SCR&rarity=rare`
     * - Magic items: `GET /api/v1/items?is_magic=true&rarity=legendary`
     *
     * **Combined Filter Examples:**
     * - Rare wands with Fireball: `GET /api/v1/items?filter=spell_slugs IN [fireball] AND type_code = WD AND rarity = rare`
     * - Charged spell items: `GET /api/v1/items?filter=spell_slugs IN [teleport] AND has_charges = true`
     * - Search + filter: `GET /api/v1/items?q=staff&filter=spell_slugs IN [teleport]`
     *
     * **Use Cases:**
     * - Magic Item Shop: Filter by rarity and type for balanced loot
     * - Scroll Discovery: Find spell scrolls by level for character progression
     * - Charged Item Inventory: Track wands/staves with specific spells
     * - Loot Tables: Generate themed magic items (fire-based, teleportation, healing)
     *
     * **Note on Spell Filtering:**
     * Spell filtering now uses Meilisearch `?filter=` syntax exclusively.
     * Legacy parameters like `?spells=`, `?spell_level=` have been removed.
     * Use `?filter=spell_slugs IN [spell1, spell2]` instead.
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
    #[QueryParameter('filter', description: 'Meilisearch filter expression for advanced filtering. Supports operators: =, !=, >, >=, <, <=, AND, OR, IN. Available fields: is_magic (bool), requires_attunement (bool), rarity (string), type_code (string), weight (float), cost_cp (int), spell_slugs (array), tag_slugs (array).', example: 'is_magic = true AND rarity IN [rare, very_rare, legendary]')]
    public function index(ItemIndexRequest $request, ItemSearchService $service, Client $meilisearch)
    {
        $dto = ItemSearchDTO::fromRequest($request);

        // Use Meilisearch for ANY search query or filter expression
        // This enables filter-only queries without requiring ?q= parameter
        if ($dto->searchQuery !== null || $dto->meilisearchFilter !== null) {
            $items = $service->searchWithMeilisearch($dto, $meilisearch);
        } else {
            // Database query for pure pagination (no search/filter)
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
    public function show(ItemShowRequest $request, Item $item, EntityCacheService $cache, ItemSearchService $service)
    {
        $validated = $request->validated();

        // Default relationships from service
        $defaultRelationships = $service->getShowRelationships();

        // Try cache first
        $cachedItem = $cache->getItem($item->id);

        if ($cachedItem) {
            // If include parameter provided, use it; otherwise load defaults
            $includes = $validated['include'] ?? $defaultRelationships;
            $cachedItem->load($includes);

            return new ItemResource($cachedItem);
        }

        // Fallback to route model binding result (should rarely happen)
        $includes = $validated['include'] ?? $defaultRelationships;
        $item->load($includes);

        return new ItemResource($item);
    }
}
