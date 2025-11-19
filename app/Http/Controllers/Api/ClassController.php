<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassResource;
use App\Models\CharacterClass;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function index(Request $request)
    {
        $query = CharacterClass::with([
            'spellcastingAbility',
            'proficiencies.proficiencyType',
            'traits',
            'sources.source',
            'features',
            'levelProgression',
            'counters',
            'subclasses.features',
            'subclasses.counters',
        ]);

        // Apply search filter
        if ($request->has('search')) {
            $query->where('name', 'LIKE', '%'.$request->search.'%');
        }

        // Apply base_only filter (show only base classes, no subclasses)
        if ($request->boolean('base_only')) {
            $query->whereNull('parent_class_id');
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $request->get('per_page', 15);
        $classes = $query->paginate($perPage);

        return ClassResource::collection($classes);
    }

    public function show(CharacterClass $class)
    {
        $class->load([
            'spellcastingAbility',
            'parentClass',
            'subclasses',
            'proficiencies.proficiencyType',
            'proficiencies.skill.abilityScore',
            'proficiencies.abilityScore',
            'traits.randomTables.entries',
            'sources.source',
            'features',
            'levelProgression',
            'counters',
            'subclasses.features',
            'subclasses.counters',
        ]);

        return new ClassResource($class);
    }
}
