<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassIndexRequest;
use App\Http\Requests\ClassShowRequest;
use App\Http\Requests\ClassSpellListRequest;
use App\Http\Resources\ClassResource;
use App\Http\Resources\SpellResource;
use App\Models\CharacterClass;

class ClassController extends Controller
{
    public function index(ClassIndexRequest $request)
    {
        $validated = $request->validated();

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
        if (isset($validated['search'])) {
            $query->where('name', 'LIKE', '%'.$validated['search'].'%');
        }

        // Apply base_only filter (show only base classes, no subclasses)
        if (isset($validated['base_only']) && $validated['base_only']) {
            $query->whereNull('parent_class_id');
        }

        // Filter by granted proficiency
        if (isset($validated['grants_proficiency'])) {
            $query->grantsProficiency($validated['grants_proficiency']);
        }

        // Filter by granted skill
        if (isset($validated['grants_skill'])) {
            $query->grantsSkill($validated['grants_skill']);
        }

        // Filter by saving throw proficiency
        if (isset($validated['grants_saving_throw'])) {
            $abilityName = $validated['grants_saving_throw'];
            $query->whereHas('proficiencies', function ($q) use ($abilityName) {
                $q->where('proficiency_type', 'saving_throw')
                    ->whereHas('abilityScore', function ($abilityQuery) use ($abilityName) {
                        $abilityQuery->where('code', strtoupper($abilityName))
                            ->orWhere('name', 'LIKE', "%{$abilityName}%");
                    });
            });
        }

        // Apply sorting
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $validated['per_page'] ?? 15;
        $classes = $query->paginate($perPage);

        return ClassResource::collection($classes);
    }

    public function show(CharacterClass $class, ClassShowRequest $request)
    {
        $validated = $request->validated();

        // Default relationships
        $relationships = [
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
        ];

        // Use custom includes if provided
        if (isset($validated['include'])) {
            $relationships = $validated['include'];
        }

        $class->load($relationships);

        return new ClassResource($class);
    }

    /**
     * Get spells for a specific class
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function spells(CharacterClass $class, ClassSpellListRequest $request)
    {
        $validated = $request->validated();

        $query = $class->spells()
            ->with(['spellSchool', 'sources.source', 'effects.damageType', 'classes']);

        // Apply same filters as SpellController
        if (isset($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('spells.name', 'LIKE', "%{$validated['search']}%")
                    ->orWhere('spells.description', 'LIKE', "%{$validated['search']}%");
            });
        }

        if (isset($validated['level'])) {
            $query->where('spells.level', $validated['level']);
        }

        if (isset($validated['school'])) {
            $query->where('spells.spell_school_id', $validated['school']);
        }

        if (isset($validated['concentration'])) {
            $query->where('spells.needs_concentration', $validated['concentration']);
        }

        if (isset($validated['ritual'])) {
            $query->where('spells.is_ritual', $validated['ritual']);
        }

        // Sorting
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';

        // Ensure we prefix with table name for pivot queries
        if (! str_contains($sortBy, '.')) {
            $sortBy = 'spells.'.$sortBy;
        }

        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $validated['per_page'] ?? 15;
        $spells = $query->paginate($perPage);

        return SpellResource::collection($spells);
    }
}
