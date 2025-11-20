<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $query = Item::with([
            'itemType',
            'damageType',
            'properties',
            'sources.source',
            'prerequisites.prerequisite',
        ]);

        // Apply search filter
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('description', 'like', "%{$request->search}%");
            });
        }

        // Filter by item type
        if ($request->has('item_type_id')) {
            $query->where('item_type_id', $request->item_type_id);
        }

        // Filter by rarity
        if ($request->has('rarity')) {
            $query->where('rarity', $request->rarity);
        }

        // Filter by magic
        if ($request->has('is_magic')) {
            $query->where('is_magic', $request->boolean('is_magic'));
        }

        // Filter by attunement
        if ($request->has('requires_attunement')) {
            $query->where('requires_attunement', $request->boolean('requires_attunement'));
        }

        // Filter by minimum strength requirement
        if ($request->has('min_strength')) {
            $query->whereMinStrength((int) $request->min_strength);
        }

        // Filter by having any prerequisites
        if ($request->has('has_prerequisites')) {
            if ($request->boolean('has_prerequisites')) {
                $query->hasPrerequisites();
            }
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $request->get('per_page', 15);
        $items = $query->paginate($perPage);

        return ItemResource::collection($items);
    }

    public function show(Item $item)
    {
        $item->load([
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
        ]);

        return new ItemResource($item);
    }
}
