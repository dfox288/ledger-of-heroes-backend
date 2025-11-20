<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProficiencyTypeIndexRequest;
use App\Http\Resources\ProficiencyTypeResource;
use App\Models\ProficiencyType;

class ProficiencyTypeController extends Controller
{
    /**
     * List all proficiency types
     *
     * Returns a paginated list of D&D 5e proficiency types including weapons, armor, tools,
     * languages, and skills. Supports filtering by category and subcategory (e.g., "weapon/martial").
     */
    public function index(ProficiencyTypeIndexRequest $request)
    {
        $query = ProficiencyType::query();

        // Filter by category
        if ($request->has('category')) {
            $query->byCategory($request->validated('category'));
        }

        // Filter by subcategory
        if ($request->has('subcategory')) {
            $query->bySubcategory($request->validated('subcategory'));
        }

        // Search by name
        if ($request->has('search')) {
            $search = $request->validated('search');
            $query->where('name', 'like', "%{$search}%");
        }

        // Pagination
        $perPage = $request->validated('per_page', 50);
        $proficiencyTypes = $query->paginate($perPage);

        return ProficiencyTypeResource::collection($proficiencyTypes);
    }

    /**
     * Get a single proficiency type
     *
     * Returns detailed information about a specific proficiency type including category,
     * subcategory, and optional associated item.
     */
    public function show(ProficiencyType $proficiencyType)
    {
        $proficiencyType->load('item');

        return new ProficiencyTypeResource($proficiencyType);
    }
}
