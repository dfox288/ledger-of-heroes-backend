<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DamageTypeIndexRequest;
use App\Http\Resources\DamageTypeResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\SpellResource;
use App\Models\DamageType;

class DamageTypeController extends Controller
{
    /**
     * List all damage types
     *
     * Returns a paginated list of D&D 5e damage types (Fire, Cold, Poison, Slashing, etc.).
     * Used for spell effects, weapon damage, and resistances/immunities.
     */
    public function index(DamageTypeIndexRequest $request)
    {
        $query = DamageType::query();

        // Add search support
        if ($request->has('q')) {
            $search = $request->validated('q');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Add pagination support
        $perPage = $request->validated('per_page', 50); // Higher default for lookups
        $entities = $query->paginate($perPage);

        return DamageTypeResource::collection($entities);
    }

    /**
     * Get a single damage type
     *
     * Returns detailed information about a specific D&D damage type including its name
     * and associated spells, weapons, or effects.
     */
    public function show(DamageType $damageType)
    {
        return new DamageTypeResource($damageType);
    }

    /**
     * List all spells that deal this damage type
     *
     * Returns a paginated list of spells that deal this type of damage.
     *
     * @param DamageType $damageType The damage type (by ID, code, or name)
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function spells(DamageType $damageType)
    {
        $perPage = request()->input('per_page', 50);

        $spells = $damageType->spells()
            ->with(['spellSchool', 'sources', 'tags'])
            ->orderBy('name')
            ->paginate($perPage);

        return SpellResource::collection($spells);
    }

    /**
     * List all items that deal this damage type
     *
     * Returns a paginated list of items (weapons, ammunition) that deal this type of damage.
     *
     * @param DamageType $damageType The damage type (by ID, code, or name)
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function items(DamageType $damageType)
    {
        $perPage = request()->input('per_page', 50);

        $items = $damageType->items()
            ->with(['itemType', 'sources', 'tags'])
            ->orderBy('name')
            ->paginate($perPage);

        return ItemResource::collection($items);
    }
}
