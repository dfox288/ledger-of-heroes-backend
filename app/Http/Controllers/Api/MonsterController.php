<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MonsterIndexRequest;
use App\Http\Requests\MonsterShowRequest;
use App\Http\Resources\MonsterResource;
use App\Models\Monster;

class MonsterController extends Controller
{
    /**
     * List all monsters
     *
     * Returns a paginated list of D&D 5e monsters. Supports filtering by challenge rating,
     * type, size, and alignment. All query parameters are validated and documented automatically.
     */
    public function index(MonsterIndexRequest $request)
    {
        $validated = $request->validated();

        $query = Monster::query()
            ->with([
                'size',
                'sources.source',
                'modifiers.abilityScore',
                'modifiers.skill',
                'modifiers.damageType',
                'conditions',
            ]);

        // Filter by challenge rating
        if (isset($validated['challenge_rating'])) {
            $query->where('challenge_rating', $validated['challenge_rating']);
        }

        // Filter by minimum challenge rating
        // Note: challenge_rating is VARCHAR to support fractions (1/4, 1/2)
        // We cast to DECIMAL for numeric comparison, which works for whole numbers
        if (isset($validated['min_cr'])) {
            $query->whereRaw('CAST(challenge_rating AS DECIMAL(5,2)) >= ?', [$validated['min_cr']]);
        }

        // Filter by maximum challenge rating
        if (isset($validated['max_cr'])) {
            $query->whereRaw('CAST(challenge_rating AS DECIMAL(5,2)) <= ?', [$validated['max_cr']]);
        }

        // Filter by type
        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        // Filter by size
        if (isset($validated['size'])) {
            $query->whereHas('size', function ($q) use ($validated) {
                $q->where('code', $validated['size']);
            });
        }

        // Filter by alignment
        if (isset($validated['alignment'])) {
            $query->where('alignment', 'like', '%'.$validated['alignment'].'%');
        }

        // Search by name
        if (isset($validated['q'])) {
            $query->where('name', 'like', '%'.$validated['q'].'%');
        }

        // Sort
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $query->orderBy($sortBy, $sortDirection);

        $perPage = $validated['per_page'] ?? 15;

        return MonsterResource::collection($query->paginate($perPage));
    }

    /**
     * Get a single monster
     *
     * Returns detailed information about a specific monster including traits, actions,
     * legendary actions, spellcasting, modifiers, conditions, and source citations.
     * Supports selective relationship loading via the 'include' parameter.
     */
    public function show(MonsterShowRequest $request, Monster $monster)
    {
        $validated = $request->validated();

        // Default relationships
        $with = [
            'size',
            'traits',
            'actions',
            'legendaryActions',
            'spellcasting',
            'sources.source',
            'modifiers.abilityScore',
            'modifiers.skill',
            'modifiers.damageType',
            'conditions',
        ];

        // Use validated include parameter if provided
        if (isset($validated['include'])) {
            $with = $validated['include'];
        }

        $monster->load($with);

        return new MonsterResource($monster);
    }
}
