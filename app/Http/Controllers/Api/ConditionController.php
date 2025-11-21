<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConditionIndexRequest;
use App\Http\Resources\ConditionResource;
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
}
