<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SpellResource;
use App\Models\Spell;
use Illuminate\Http\Request;

class SpellController extends Controller
{
    public function index(Request $request)
    {
        $query = Spell::with(['spellSchool', 'sources.source', 'effects.damageType', 'classes']);

        // Apply search filter (FULLTEXT search)
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Apply filters
        if ($request->has('level')) {
            $query->level($request->level);
        }

        if ($request->has('school')) {
            $query->school($request->school);
        }

        if ($request->has('concentration')) {
            $query->concentration($request->boolean('concentration'));
        }

        if ($request->has('ritual')) {
            $query->ritual($request->boolean('ritual'));
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $request->get('per_page', 15);
        $spells = $query->paginate($perPage);

        return SpellResource::collection($spells);
    }

    public function show(Spell $spell)
    {
        $spell->load(['spellSchool', 'sources.source', 'effects.damageType', 'classes']);

        return new SpellResource($spell);
    }
}
