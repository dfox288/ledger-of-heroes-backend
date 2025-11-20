<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SizeIndexRequest;
use App\Http\Resources\SizeResource;
use App\Models\Size;

class SizeController extends Controller
{
    /**
     * List all creature sizes
     *
     * Returns a paginated list of D&D 5e creature sizes (Tiny, Small, Medium, Large, Huge, Gargantuan).
     * Used to categorize creatures, races, and determine space occupied in combat.
     */
    public function index(SizeIndexRequest $request)
    {
        $query = Size::query();

        // Search by name
        if ($request->has('search')) {
            $search = $request->validated('search');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Pagination
        $perPage = $request->validated('per_page', 50);

        return SizeResource::collection(
            $query->paginate($perPage)
        );
    }

    /**
     * Get a single size category
     *
     * Returns detailed information about a specific creature size including its space
     * requirements and rules implications.
     */
    public function show(Size $size)
    {
        return $size;
    }
}
