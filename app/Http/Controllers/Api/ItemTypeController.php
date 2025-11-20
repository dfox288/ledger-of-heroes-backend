<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemTypeIndexRequest;
use App\Http\Resources\ItemTypeResource;
use App\Models\ItemType;

class ItemTypeController extends Controller
{
    public function index(ItemTypeIndexRequest $request)
    {
        $query = ItemType::query();

        // Search by name
        if ($request->has('search')) {
            $search = $request->validated('search');
            $query->where('name', 'like', "%{$search}%");
        }

        // Pagination
        $perPage = $request->validated('per_page', 50);
        $itemTypes = $query->paginate($perPage);

        return ItemTypeResource::collection($itemTypes);
    }

    public function show(ItemType $itemType)
    {
        return new ItemTypeResource($itemType);
    }
}
