<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DamageTypeIndexRequest;
use App\Http\Resources\DamageTypeResource;
use App\Models\DamageType;

class DamageTypeController extends Controller
{
    public function index(DamageTypeIndexRequest $request)
    {
        $query = DamageType::query();

        // Add search support
        if ($request->has('search')) {
            $search = $request->validated('search');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Add pagination support
        $perPage = $request->validated('per_page', 50); // Higher default for lookups
        $entities = $query->paginate($perPage);

        return DamageTypeResource::collection($entities);
    }

    public function show(DamageType $damageType)
    {
        return new DamageTypeResource($damageType);
    }
}
