<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpellSchoolIndexRequest;
use App\Http\Resources\SpellSchoolResource;
use App\Models\SpellSchool;

class SpellSchoolController extends Controller
{
    public function index(SpellSchoolIndexRequest $request)
    {
        $query = SpellSchool::query();

        // Search by name
        if ($request->has('search')) {
            $search = $request->validated('search');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Pagination
        $perPage = $request->validated('per_page', 50);

        return SpellSchoolResource::collection(
            $query->paginate($perPage)
        );
    }

    public function show(SpellSchool $spellSchool)
    {
        return $spellSchool;
    }
}
