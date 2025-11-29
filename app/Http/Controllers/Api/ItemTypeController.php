<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemTypeIndexRequest;
use App\Http\Resources\ItemTypeResource;
use App\Models\ItemType;
use Dedoc\Scramble\Attributes\QueryParameter;

class ItemTypeController extends Controller
{
    /**
     * List all item types
     *
     * Returns D&D 5e item type categories used to classify equipment and magical items.
     * Each item in the database belongs to exactly one item type.
     *
     * **Examples:**
     * ```
     * GET /api/v1/lookups/item-types              # All item types
     * GET /api/v1/lookups/item-types?q=weapon     # Search by name
     * GET /api/v1/lookups/item-types?q=wondrous   # Find wondrous items category
     * ```
     *
     * **Common Item Types:**
     * - **Weapon (W):** Swords, axes, bows, crossbows, etc.
     * - **Armor (LA/MA/HA):** Light, Medium, Heavy armor and shields
     * - **Potion (P):** Consumable magical liquids
     * - **Scroll (SC):** Single-use spell scrolls
     * - **Wand (WD):** Spellcasting focus items
     * - **Rod (RD):** Magical rod items
     * - **Staff (ST):** Magical staves
     * - **Ring (RG):** Magical rings
     * - **Wondrous Item (G):** Miscellaneous magic items (cloaks, boots, etc.)
     * - **Adventuring Gear (G):** Non-magical equipment
     *
     * **Query Parameters:**
     * - `q` (string): Search item types by name (partial match)
     * - `per_page` (int): Results per page, 1-100 (default: 50)
     *
     * **Use Cases:**
     * - **Item Browsing:** Filter the /items endpoint by type_code to browse categories
     * - **Treasure Generation:** Pick random items from specific categories
     * - **Character Equipment:** Find all weapons or armor available
     * - **Magic Item Shopping:** Browse wondrous items, rings, or wands
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    #[QueryParameter('q', description: 'Search item types by name', example: 'weapon')]
    public function index(ItemTypeIndexRequest $request)
    {
        $query = ItemType::query();

        // Search by name
        if ($request->has('q')) {
            $search = $request->validated('q');
            $query->where('name', 'like', "%{$search}%");
        }

        // Pagination
        $perPage = $request->validated('per_page', 50);
        $itemTypes = $query->paginate($perPage);

        return ItemTypeResource::collection($itemTypes);
    }

    /**
     * Get a single item type
     *
     * Returns detailed information about a specific item type category.
     * Item types can be retrieved by ID, code, slug, or name.
     *
     * **Examples:**
     * ```
     * GET /api/v1/lookups/item-types/5           # By ID
     * GET /api/v1/lookups/item-types/W           # By code
     * GET /api/v1/lookups/item-types/weapon      # By slug
     * GET /api/v1/lookups/item-types/Weapon      # By name
     * ```
     *
     * **Response includes:**
     * - `id`, `code`, `name`, `slug`: Item type identification
     * - `description`: What this item type represents
     *
     * **Related:** Use `/api/v1/items?filter=type_code = W` to list all items of this type.
     */
    public function show(ItemType $itemType)
    {
        return new ItemTypeResource($itemType);
    }
}
