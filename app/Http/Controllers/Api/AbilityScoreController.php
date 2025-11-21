<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AbilityScoreIndexRequest;
use App\Http\Resources\AbilityScoreResource;
use App\Models\AbilityScore;

class AbilityScoreController extends Controller
{
    /**
     * List all ability scores
     *
     * Returns a paginated list of the 6 core ability scores in D&D 5e (Strength, Dexterity,
     * Constitution, Intelligence, Wisdom, Charisma). Supports searching by name or code (e.g., "STR", "DEX").
     */
    public function index(AbilityScoreIndexRequest $request)
    {
        $query = AbilityScore::query();

        // Search by name OR code
        if ($request->has('q')) {
            $search = $request->validated('q');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->validated('per_page', 50);

        return AbilityScoreResource::collection(
            $query->paginate($perPage)
        );
    }

    /**
     * Get a single ability score
     *
     * Returns detailed information about a specific ability score including its full name,
     * code abbreviation, and associated skills.
     */
    public function show(AbilityScore $abilityScore)
    {
        return new AbilityScoreResource($abilityScore);
    }
}
