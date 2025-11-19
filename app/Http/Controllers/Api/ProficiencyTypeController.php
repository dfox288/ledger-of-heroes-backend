<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProficiencyTypeResource;
use App\Models\ProficiencyType;
use Illuminate\Http\Request;

class ProficiencyTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ProficiencyType::query();

        // Filter by category
        if ($request->has('category')) {
            $query->byCategory($request->input('category'));
        }

        // Filter by subcategory
        if ($request->has('subcategory')) {
            $query->bySubcategory($request->input('subcategory'));
        }

        // Search by name
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        $query->orderBy($sortBy, $sortDirection);

        $perPage = $request->input('per_page', 15);
        $proficiencyTypes = $query->paginate($perPage);

        return ProficiencyTypeResource::collection($proficiencyTypes);
    }

    /**
     * Display the specified resource.
     */
    public function show(ProficiencyType $proficiencyType)
    {
        $proficiencyType->load('item');

        return new ProficiencyTypeResource($proficiencyType);
    }
}
