<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SkillIndexRequest;
use App\Http\Resources\SkillResource;
use App\Models\Skill;

class SkillController extends Controller
{
    public function index(SkillIndexRequest $request)
    {
        $query = Skill::query();

        // Search by name
        if ($request->has('search')) {
            $search = $request->validated('search');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Filter by ability score code
        if ($request->has('ability')) {
            $query->whereHas('abilityScore', fn ($q) => $q->where('code', strtoupper($request->validated('ability')))
            );
        }

        // Pagination
        $perPage = $request->validated('per_page', 50);

        return SkillResource::collection(
            $query->paginate($perPage)
        );
    }

    public function show(Skill $skill)
    {
        return $skill;
    }
}
