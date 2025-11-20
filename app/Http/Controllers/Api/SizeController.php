<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SizeIndexRequest;
use App\Http\Resources\SizeResource;
use App\Models\Size;

class SizeController extends Controller
{
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

    public function show(Size $size)
    {
        return $size;
    }
}
