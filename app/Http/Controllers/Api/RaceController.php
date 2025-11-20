<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RaceIndexRequest;
use App\Http\Requests\RaceShowRequest;
use App\Http\Resources\RaceResource;
use App\Models\Race;

class RaceController extends Controller
{
    public function index(RaceIndexRequest $request)
    {
        $validated = $request->validated();

        $query = Race::with([
            'size',
            'sources.source',
            'proficiencies.skill',
            'traits.randomTables.entries',
            'modifiers.abilityScore',
            'conditions.condition',
            'spells.spell',
            'spells.abilityScore',
        ]);

        // Apply search filter
        if (isset($validated['search'])) {
            $query->search($validated['search']);
        }

        // Apply size filter
        if (isset($validated['size'])) {
            $query->size($validated['size']);
        }

        // Filter by granted proficiency
        if (isset($validated['grants_proficiency'])) {
            $query->grantsProficiency($validated['grants_proficiency']);
        }

        // Filter by granted skill
        if (isset($validated['grants_skill'])) {
            $query->grantsSkill($validated['grants_skill']);
        }

        // Filter by proficiency type/category
        if (isset($validated['grants_proficiency_type'])) {
            $query->grantsProficiencyType($validated['grants_proficiency_type']);
        }

        // Filter by spoken language
        if (isset($validated['speaks_language'])) {
            $query->speaksLanguage($validated['speaks_language']);
        }

        // Filter by language choice count
        if (isset($validated['language_choice_count'])) {
            $query->languageChoiceCount((int) $validated['language_choice_count']);
        }

        // Filter entities granting any languages
        if (isset($validated['grants_languages'])) {
            if ($request->boolean('grants_languages')) {
                $query->grantsLanguages();
            }
        }

        // Apply sorting
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $validated['per_page'] ?? 15;
        $races = $query->paginate($perPage);

        return RaceResource::collection($races);
    }

    public function show(RaceShowRequest $request, Race $race)
    {
        $validated = $request->validated();

        // Load relationships if specified in the request
        if (isset($validated['include']) && ! empty($validated['include'])) {
            $race->load($validated['include']);
        } else {
            // Default relationships
            $race->load([
                'size',
                'sources.source',
                'parent',
                'subraces',
                'proficiencies.skill.abilityScore',
                'proficiencies.abilityScore',
                'traits.randomTables.entries',
                'modifiers.abilityScore',
                'modifiers.skill',
                'modifiers.damageType',
                'languages.language',
                'conditions.condition',
                'spells.spell',
                'spells.abilityScore',
            ]);
        }

        return new RaceResource($race);
    }
}
