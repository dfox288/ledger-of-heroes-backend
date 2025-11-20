<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConditionIndexRequest;
use App\Http\Resources\ConditionResource;
use App\Models\Condition;

class ConditionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ConditionIndexRequest $request)
    {
        $query = Condition::query();

        // Add search support
        if ($request->has('search')) {
            $search = $request->validated('search');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Add pagination support
        $perPage = $request->validated('per_page', 50); // Higher default for lookups
        $entities = $query->paginate($perPage);

        return ConditionResource::collection($entities);
    }

    /**
     * Display the specified resource.
     */
    public function show(Condition $condition)
    {
        return new ConditionResource($condition);
    }
}
