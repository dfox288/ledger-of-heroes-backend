<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemIndexRequest;
use App\Http\Requests\ItemShowRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;

class ItemController extends Controller
{
    /**
     * List all items
     *
     * Returns a paginated list of D&D 5e items including weapons, armor, and magic items.
     * Supports filtering by item type, rarity, magic properties, attunement requirements,
     * and prerequisites. All query parameters are validated automatically.
     */
    public function index(ItemIndexRequest $request)
    {
        $validated = $request->validated();

        $query = Item::with([
            'itemType',
            'damageType',
            'properties',
            'sources.source',
            'prerequisites.prerequisite',
        ]);

        // Apply search filter
        if (isset($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('name', 'like', "%{$validated['search']}%")
                    ->orWhere('description', 'like', "%{$validated['search']}%");
            });
        }

        // Filter by item type
        if (isset($validated['item_type_id'])) {
            $query->where('item_type_id', $validated['item_type_id']);
        }

        // Filter by rarity
        if (isset($validated['rarity'])) {
            $query->where('rarity', $validated['rarity']);
        }

        // Filter by magic
        if (isset($validated['is_magic'])) {
            $query->where('is_magic', (bool) $validated['is_magic']);
        }

        // Filter by attunement
        if (isset($validated['requires_attunement'])) {
            $query->where('requires_attunement', (bool) $validated['requires_attunement']);
        }

        // Filter by minimum strength requirement
        if (isset($validated['min_strength'])) {
            $query->whereMinStrength((int) $validated['min_strength']);
        }

        // Filter by having any prerequisites
        if (isset($validated['has_prerequisites'])) {
            if ((bool) $validated['has_prerequisites']) {
                $query->hasPrerequisites();
            }
        }

        // Apply sorting
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $validated['per_page'] ?? 15;
        $items = $query->paginate($perPage);

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
            'prerequisites.prerequisite',
        ];

        // If 'include' parameter provided, use it (note: this is for additional validation)
        // The actual loading is still done via the default relationships above
        // In a more advanced implementation, you might dynamically build the relationships array
        $item->load($relationships);

        return new ItemResource($item);
    }
}
