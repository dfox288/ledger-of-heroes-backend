<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SourceIndexRequest;
use App\Http\Resources\SourceResource;
use App\Models\Source;

class SourceController extends Controller
{
    /**
     * List all D&D sourcebooks
     *
     * Returns a paginated list of D&D 5e sourcebooks (PHB, Xanathar's, Tasha's, etc.).
     * Supports searching by name or code (e.g., "PHB", "XGE").
     */
    public function index(SourceIndexRequest $request)
    {
        $query = Source::query();

        // Add search support
        if ($request->has('q')) {
            $search = $request->validated('q');
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

    /**
     * Get a single sourcebook
     *
     * Returns detailed information about a specific D&D sourcebook including its full name,
     * code abbreviation, and publication date.
     */
    public function show(Source $source)
    {
        return new SourceResource($source);
    }
}
