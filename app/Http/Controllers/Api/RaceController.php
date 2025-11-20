<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RaceResource;
use App\Models\Race;
use Illuminate\Http\Request;

class RaceController extends Controller
{
    public function index(Request $request)
    {
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
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Apply size filter
        if ($request->has('size')) {
            $query->size($request->size);
        }

        // Filter by granted proficiency
        if ($request->has('grants_proficiency')) {
            $query->grantsProficiency($request->grants_proficiency);
        }

        // Filter by granted skill
        if ($request->has('grants_skill')) {
            $query->grantsSkill($request->grants_skill);
        }

        // Filter by proficiency type/category
        if ($request->has('grants_proficiency_type')) {
            $query->grantsProficiencyType($request->grants_proficiency_type);
        }

        // Filter by spoken language
        if ($request->has('speaks_language')) {
            $query->speaksLanguage($request->speaks_language);
        }

        // Filter by language choice count
        if ($request->has('language_choice_count')) {
            $query->languageChoiceCount((int) $request->language_choice_count);
        }

        // Filter entities granting any languages
        if ($request->has('grants_languages')) {
            if ($request->boolean('grants_languages')) {
                $query->grantsLanguages();
            }
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $request->get('per_page', 15);
        $races = $query->paginate($perPage);

        return RaceResource::collection($races);
    }

    public function show(Race $race)
    {
        $race->load([
            'size',
            'sources.source',
            'parent',
            'subraces',
            'proficiencies.skill.abilityScore',
            'proficiencies.abilityScore',
            'traits.randomTables.entries', // Load random tables through traits
            'modifiers.abilityScore',
            'modifiers.skill',
            'modifiers.damageType',
            'languages.language',
            'conditions.condition',
            'spells.spell',
            'spells.abilityScore',
        ]);

        return new RaceResource($race);
    }
}
