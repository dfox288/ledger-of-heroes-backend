<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConditionIndexRequest;
use App\Http\Resources\ConditionResource;
use App\Http\Resources\MonsterResource;
use App\Http\Resources\SpellResource;
use App\Models\Condition;

class ConditionController extends Controller
{
    /**
     * List all D&D conditions
     *
     * Returns a paginated list of D&D 5e conditions (Blinded, Charmed, Frightened, etc.).
     * These are status effects that can be applied to creatures during combat.
     */
    public function index(ConditionIndexRequest $request)
    {
        $query = Condition::query();

        // Add search support
        if ($request->has('q')) {
            $search = $request->validated('q');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Add pagination support
        $perPage = $request->validated('per_page', 50); // Higher default for lookups
        $entities = $query->paginate($perPage);

        return ConditionResource::collection($entities);
    }

    /**
     * Get a single condition
     *
     * Returns detailed information about a specific D&D condition including its rules
     * and effects on gameplay.
     */
    public function show(Condition $condition)
    {
        return new ConditionResource($condition);
    }

    /**
     * List all spells that inflict this condition
     *
     * Returns a paginated list of spells that can inflict this condition on targets.
     *
     * @param Condition $condition The condition (by ID or slug)
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function spells(Condition $condition)
    {
        $perPage = request()->input('per_page', 50);

        $spells = $condition->spells()
            ->with(['spellSchool', 'sources', 'tags'])
            ->orderBy('name')
            ->paginate($perPage);

        return SpellResource::collection($spells);
    }

    /**
     * List all monsters that inflict this condition
     *
     * Returns a paginated list of monsters that can inflict this condition through
     * their attacks, traits, or special abilities.
     *
     * @param Condition $condition The condition (by ID or slug)
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function monsters(Condition $condition)
    {
        $perPage = request()->input('per_page', 50);

        $monsters = $condition->monsters()
            ->with(['size', 'sources'])
            ->orderBy('name')
            ->paginate($perPage);

        return MonsterResource::collection($monsters);
    }
}
