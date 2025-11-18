<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConditionResource;
use App\Models\Condition;
use Illuminate\Http\Request;

class ConditionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Condition::query();

        // Search by name or slug
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        $query->orderBy($sortBy, $sortDirection);

        $perPage = $request->input('per_page', 15);
        $conditions = $query->paginate($perPage);

        return ConditionResource::collection($conditions);
    }

    /**
     * Display the specified resource.
     */
    public function show(Condition $condition)
    {
        return new ConditionResource($condition);
    }
}
