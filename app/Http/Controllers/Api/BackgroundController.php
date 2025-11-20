<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BackgroundResource;
use App\Models\Background;
use Illuminate\Http\Request;

class BackgroundController extends Controller
{
    public function index(Request $request)
    {
        $query = Background::with(['sources.source']);

        // Apply search filter
        if ($request->has('search')) {
            $query->search($request->search);
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
        $backgrounds = $query->paginate($perPage);

        return BackgroundResource::collection($backgrounds);
    }

    public function show(Background $background)
    {
        $background->load([
            'sources.source',
            'traits.randomTables.entries',
            'proficiencies.skill.abilityScore',
            'proficiencies.proficiencyType',
            'languages.language',
            'equipment.item',
        ]);

        return new BackgroundResource($background);
    }
}
