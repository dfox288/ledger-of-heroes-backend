<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpellSchoolIndexRequest;
use App\Http\Resources\SpellSchoolResource;
use App\Models\SpellSchool;

class SpellSchoolController extends Controller
{
    /**
     * List all schools of magic
     *
     * Returns a paginated list of the 8 schools of magic in D&D 5e (Abjuration, Conjuration,
     * Divination, Enchantment, Evocation, Illusion, Necromancy, Transmutation).
     */
    public function index(SpellSchoolIndexRequest $request)
    {
        $query = SpellSchool::query();

        // Search by name
        if ($request->has('q')) {
            $search = $request->validated('q');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Pagination
        $perPage = $request->validated('per_page', 50);

        return SpellSchoolResource::collection(
            $query->paginate($perPage)
        );
    }

    /**
     * Get a single school of magic
     *
     * Returns detailed information about a specific school of magic including its name
     * and associated spells.
     */
    public function show(SpellSchool $spellSchool)
    {
        return new SpellSchoolResource($spellSchool);
    }
}
