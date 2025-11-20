<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemPropertyIndexRequest;
use App\Http\Resources\ItemPropertyResource;
use App\Models\ItemProperty;

class ItemPropertyController extends Controller
{
    /**
     * List all item properties
     *
     * Returns a paginated list of D&D 5e item properties (Versatile, Finesse, Two-Handed, etc.).
     * These special properties modify how weapons and equipment function.
     */
    public function index(ItemPropertyIndexRequest $request)
    {
        $query = ItemProperty::query();

        // Search by name
        if ($request->has('search')) {
            $search = $request->validated('search');
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
     * Returns detailed information about a specific item property including its name
     * and rules text describing how it affects gameplay.
     */
    public function show(ItemProperty $itemProperty)
    {
        return new ItemPropertyResource($itemProperty);
    }
}
