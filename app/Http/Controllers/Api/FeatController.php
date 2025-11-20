<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FeatIndexRequest;
use App\Http\Requests\FeatShowRequest;
use App\Http\Resources\FeatResource;
use App\Models\Feat;

class FeatController extends Controller
{
    public function index(FeatIndexRequest $request)
    {
        $validated = $request->validated();
        $query = Feat::with(['sources.source', 'prerequisites.prerequisite']);

        // Apply search filter
        if (isset($validated['search'])) {
            $query->search($validated['search']);
        }

        // Filter by prerequisite race
        if (isset($validated['prerequisite_race'])) {
            $query->wherePrerequisiteRace($validated['prerequisite_race']);
        }

        // Filter by prerequisite ability score
        if (isset($validated['prerequisite_ability'])) {
            $minValue = $validated['min_value'] ?? null;
            $query->wherePrerequisiteAbility($validated['prerequisite_ability'], $minValue);
        }

        // Filter by prerequisite proficiency
        if (isset($validated['prerequisite_proficiency'])) {
            $query->wherePrerequisiteProficiency($validated['prerequisite_proficiency']);
        }

        // Filter by presence of prerequisites
        if (isset($validated['has_prerequisites'])) {
            $hasPrerequisites = filter_var($validated['has_prerequisites'], FILTER_VALIDATE_BOOLEAN);
            $query->withOrWithoutPrerequisites($hasPrerequisites);
        }

        // Filter by granted proficiency
        if (isset($validated['grants_proficiency'])) {
            $query->grantsProficiency($validated['grants_proficiency']);
        }

        // Filter by granted skill
        if (isset($validated['grants_skill'])) {
            $query->grantsSkill($validated['grants_skill']);
        }

        // Apply sorting
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $validated['per_page'] ?? 15;
        $feats = $query->paginate($perPage);

        return FeatResource::collection($feats);
    }

    public function show(FeatShowRequest $request, Feat $feat)
    {
        $validated = $request->validated();

        // Default relationships
        $with = [
            'sources.source',
            'modifiers.abilityScore',
            'modifiers.skill',
            'proficiencies.skill.abilityScore',
            'proficiencies.proficiencyType',
            'conditions',
            'prerequisites.prerequisite',
        ];

        // Use validated include parameter if provided
        if (isset($validated['include'])) {
            $with = $validated['include'];
        }

        $feat->load($with);

        return new FeatResource($feat);
    }
}
