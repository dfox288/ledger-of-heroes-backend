<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemPropertyIndexRequest;
use App\Http\Resources\ItemPropertyResource;
use App\Models\ItemProperty;
use Dedoc\Scramble\Attributes\QueryParameter;

class ItemPropertyController extends Controller
{
    /**
     * List all item properties
     *
     * Returns D&D 5e item properties - special characteristics that modify how weapons
     * and equipment function in combat and gameplay.
     *
     * **Examples:**
     * ```
     * GET /api/v1/lookups/item-properties              # All properties
     * GET /api/v1/lookups/item-properties?q=finesse    # Search by name
     * GET /api/v1/lookups/item-properties?q=two        # Find two-handed, etc.
     * ```
     *
     * **Common Weapon Properties:**
     * - **Finesse (F):** Use DEX or STR for attack/damage (Rapier, Dagger, Shortsword)
     * - **Versatile (V):** One or two-handed with different damage dice (Longsword, Battleaxe)
     * - **Two-Handed (2H):** Requires both hands (Greatsword, Greataxe, Longbow)
     * - **Light (L):** Can be used for two-weapon fighting (Dagger, Shortsword, Handaxe)
     * - **Heavy (H):** Small creatures have disadvantage (Greataxe, Heavy Crossbow)
     * - **Reach (R):** 10 ft. reach instead of 5 ft. (Glaive, Halberd, Pike)
     * - **Thrown (T):** Can be thrown for ranged attacks (Javelin, Handaxe, Dagger)
     * - **Loading (LD):** One attack per action regardless of Extra Attack (Crossbows)
     * - **Ammunition (A):** Requires ammunition (Bows, Crossbows)
     * - **Special (S):** Unique rules (Lance, Net)
     *
     * **Query Parameters:**
     * - `q` (string): Search properties by name (partial match)
     * - `per_page` (int): Results per page, 1-100 (default: 50)
     *
     * **Use Cases:**
     * - **Weapon Selection:** Find finesse weapons for DEX-based fighters
     * - **Two-Weapon Fighting:** Browse light weapons for dual wielding
     * - **Build Optimization:** Find versatile weapons for flexibility
     * - **Feat Planning:** Identify heavy weapons for Great Weapon Master
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    #[QueryParameter('q', description: 'Search properties by name', example: 'finesse')]
    public function index(ItemPropertyIndexRequest $request)
    {
        $query = ItemProperty::query();

        // Search by name
        if ($request->has('q')) {
            $search = $request->validated('q');
            $query->where('name', 'like', "%{$search}%");
        }

        // Pagination
        $perPage = $request->validated('per_page', 50);
        $itemProperties = $query->paginate($perPage);

        return ItemPropertyResource::collection($itemProperties);
    }

    /**
     * Get a single item property
     *
     * Returns detailed information about a specific item property.
     * Properties can be retrieved by ID, code, slug, or name.
     *
     * **Examples:**
     * ```
     * GET /api/v1/lookups/item-properties/3           # By ID
     * GET /api/v1/lookups/item-properties/F           # By code
     * GET /api/v1/lookups/item-properties/finesse     # By slug
     * GET /api/v1/lookups/item-properties/Finesse     # By name
     * ```
     *
     * **Response includes:**
     * - `id`, `code`, `name`, `slug`: Property identification
     * - `description`: Rules text explaining how this property works
     *
     * **Related:** Use `/api/v1/items?filter=property_codes IN [F]` to list all items with this property.
     */
    public function show(ItemProperty $itemProperty)
    {
        return new ItemPropertyResource($itemProperty);
    }
}
