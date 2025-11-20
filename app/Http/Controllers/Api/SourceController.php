<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SourceIndexRequest;
use App\Http\Resources\SourceResource;
use App\Models\Source;

class SourceController extends Controller
{
    public function index(SourceIndexRequest $request)
    {
        $query = Source::query();

        // Add search support
        if ($request->has('search')) {
            $search = $request->validated('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        // Add pagination support
        $perPage = $request->validated('per_page', 50); // Higher default for lookups
        $entities = $query->paginate($perPage);

        return SourceResource::collection($entities);
    }

    public function show(Source $source)
    {
        return new SourceResource($source);
    }
}
