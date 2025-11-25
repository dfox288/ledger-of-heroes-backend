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
     * Uses Meilisearch for full-text search and advanced filtering.
     *
     * **Search Examples:**
     * - Text search: `GET /api/v1/items?q=staff`
     * - Search + filter: `GET /api/v1/items?q=sword&filter=rarity = legendary`
     *
     * **Filter Examples:**
     * - By rarity: `GET /api/v1/items?filter=rarity IN [rare, legendary]`
     * - By type: `GET /api/v1/items?filter=type_code = WD` (wands)
     * - Magic items: `GET /api/v1/items?filter=is_magic = true`
     * - Requires attunement: `GET /api/v1/items?filter=requires_attunement = true`
     * - Has charges: `GET /api/v1/items?filter=has_charges = true`
     * - By spell: `GET /api/v1/items?filter=spell_slugs IN [fireball]`
     * - By cost: `GET /api/v1/items?filter=cost_cp >= 5000` (50+ gold)
     * - By weight: `GET /api/v1/items?filter=weight <= 1.0`
     *
     * **Combined Filters:**
     * - Rare wands with Fireball: `GET /api/v1/items?filter=spell_slugs IN [fireball] AND type_code = WD AND rarity = rare`
     * - Legendary magic items: `GET /api/v1/items?filter=is_magic = true AND rarity = legendary`
     * - Lightweight scrolls: `GET /api/v1/items?filter=type_code = SCR AND weight <= 0.1`
     *
     * **Common Item Type Codes:**
     * - `WD` = Wand, `ST` = Staff, `RD` = Rod, `SCR` = Scroll, `P` = Potion
     * - See `/api/v1/item-types` for complete list
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression. Available fields: is_magic (bool), requires_attunement (bool), has_charges (bool), rarity (string: common, uncommon, rare, very_rare, legendary, artifact), type_code (string: WD, ST, RD, SCR, P, etc.), weight (float), cost_cp (int), spell_slugs (array), tag_slugs (array). Operators: =, !=, >, >=, <, <=, AND, OR, IN.', example: 'spell_slugs IN [fireball] AND rarity = rare')]
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
