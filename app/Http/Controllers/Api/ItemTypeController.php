<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemTypeIndexRequest;
use App\Http\Resources\ItemTypeResource;
use App\Models\ItemType;

class ItemTypeController extends Controller
{
    /**
     * List all item types
     *
     * Returns a paginated list of D&D 5e item types (Weapon, Armor, Potion, Wondrous Item, etc.).
     * Used to categorize equipment and magical items.
     */
    public function index(ItemTypeIndexRequest $request)
    {
        $query = ItemType::query();

        // Search by name
        if ($request->has('search')) {
            $search = $request->validated('search');
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
     * Returns detailed information about a specific item type category including all items
     * that belong to this type.
     */
    public function show(ItemType $itemType)
    {
        return new ItemTypeResource($itemType);
    }
}
