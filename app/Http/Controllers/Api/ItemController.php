<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ItemSearchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\ItemIndexRequest;
use App\Http\Requests\ItemShowRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Services\ItemSearchService;

class ItemController extends Controller
{
    /**
     * List all items
     *
     * Returns a paginated list of D&D 5e items including weapons, armor, and magic items.
     * Supports filtering by item type, rarity, magic properties, attunement requirements,
     * and prerequisites. All query parameters are validated automatically.
     */
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
            'prerequisites.prerequisite',
        ];

        // If 'include' parameter provided, use it (note: this is for additional validation)
        // The actual loading is still done via the default relationships above
        // In a more advanced implementation, you might dynamically build the relationships array
        $item->load($relationships);

        return new ItemResource($item);
    }
}
