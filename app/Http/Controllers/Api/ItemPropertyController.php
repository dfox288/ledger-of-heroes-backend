<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemPropertyIndexRequest;
use App\Http\Resources\ItemPropertyResource;
use App\Models\ItemProperty;

class ItemPropertyController extends Controller
{
    public function index(ItemPropertyIndexRequest $request)
    {
        $query = ItemProperty::query();

        // Search by name
        if ($request->has('search')) {
            $search = $request->validated('search');
            $query->where('name', 'like', "%{$search}%");
        }

        // Pagination
        $perPage = $request->validated('per_page', 50);
        $itemProperties = $query->paginate($perPage);

        return ItemPropertyResource::collection($itemProperties);
    }

    public function show(ItemProperty $itemProperty)
    {
        return new ItemPropertyResource($itemProperty);
    }
}
