<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AbilityScoreIndexRequest;
use App\Http\Resources\AbilityScoreResource;
use App\Models\AbilityScore;

class AbilityScoreController extends Controller
{
    public function index(AbilityScoreIndexRequest $request)
    {
        $query = AbilityScore::query();

        // Search by name OR code
        if ($request->has('search')) {
            $search = $request->validated('search');
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

    public function show(AbilityScore $abilityScore)
    {
        return $abilityScore;
    }
}
