<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassResource;
use App\Http\Resources\SpellResource;
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

        // Filter by granted proficiency
        if ($request->has('grants_proficiency')) {
            $query->grantsProficiency($request->grants_proficiency);
        }

        // Filter by granted skill
        if ($request->has('grants_skill')) {
            $query->grantsSkill($request->grants_skill);
        }

        // Filter by saving throw proficiency
        if ($request->has('grants_saving_throw')) {
            $abilityName = $request->grants_saving_throw;
            $query->whereHas('proficiencies', function ($q) use ($abilityName) {
                $q->where('proficiency_type', 'saving_throw')
                    ->whereHas('abilityScore', function ($abilityQuery) use ($abilityName) {
                        $abilityQuery->where('code', strtoupper($abilityName))
                            ->orWhere('name', 'LIKE', "%{$abilityName}%");
                    });
            });
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

    /**
     * Get spells for a specific class
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function spells(CharacterClass $class, Request $request)
    {
        $query = $class->spells()
            ->with(['spellSchool', 'sources.source', 'effects.damageType', 'classes']);

        // Apply same filters as SpellController
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('spells.name', 'LIKE', "%{$request->search}%")
                    ->orWhere('spells.description', 'LIKE', "%{$request->search}%");
            });
        }

        if ($request->has('level')) {
            $query->where('spells.level', $request->level);
        }

        if ($request->has('school')) {
            $query->where('spells.spell_school_id', $request->school);
        }

        if ($request->has('concentration')) {
            $query->where('spells.needs_concentration', $request->boolean('concentration'));
        }

        if ($request->has('ritual')) {
            $query->where('spells.is_ritual', $request->boolean('ritual'));
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'spells.name');
        $sortDirection = $request->get('sort_direction', 'asc');

        // Ensure we prefix with table name for pivot queries
        if (! str_contains($sortBy, '.')) {
            $sortBy = 'spells.'.$sortBy;
        }

        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $request->get('per_page', 15);
        $spells = $query->paginate($perPage);

        return SpellResource::collection($spells);
    }
}
