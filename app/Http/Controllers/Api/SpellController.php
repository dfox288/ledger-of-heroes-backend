<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpellIndexRequest;
use App\Http\Requests\SpellShowRequest;
use App\Http\Resources\SpellResource;
use App\Models\Spell;

class SpellController extends Controller
{
    public function index(SpellIndexRequest $request)
    {
        $validated = $request->validated();
        $query = Spell::with(['spellSchool', 'sources.source', 'effects.damageType', 'classes']);

        // Apply search filter (FULLTEXT search)
        if (isset($validated['search'])) {
            $query->search($validated['search']);
        }

        // Apply filters
        if (isset($validated['level'])) {
            $query->level($validated['level']);
        }

        if (isset($validated['school'])) {
            $query->school($validated['school']);
        }

        if (isset($validated['concentration'])) {
            $query->concentration($validated['concentration']);
        }

        if (isset($validated['ritual'])) {
            $query->ritual($validated['ritual']);
        }

        // Apply sorting
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $validated['per_page'] ?? 15;
        $spells = $query->paginate($perPage);

        return SpellResource::collection($spells);
    }

    public function show(SpellShowRequest $request, Spell $spell)
    {
        $validated = $request->validated();

        // Load relationships based on validated 'include' parameter
        $includes = $validated['include'] ?? ['spellSchool', 'sources.source', 'effects.damageType', 'classes'];
        $spell->load($includes);

        return new SpellResource($spell);
    }
}
