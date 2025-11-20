<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackgroundIndexRequest;
use App\Http\Requests\BackgroundShowRequest;
use App\Http\Resources\BackgroundResource;
use App\Models\Background;

class BackgroundController extends Controller
{
    public function index(BackgroundIndexRequest $request)
    {
        $validated = $request->validated();
        $query = Background::with(['sources.source']);

        // Apply search filter
        if (isset($validated['search'])) {
            $query->search($validated['search']);
        }

        // Filter by granted proficiency
        if (isset($validated['grants_proficiency'])) {
            $query->grantsProficiency($validated['grants_proficiency']);
        }

        // Filter by granted skill
        if (isset($validated['grants_skill'])) {
            $query->grantsSkill($validated['grants_skill']);
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
            if ($validated['grants_languages']) {
                $query->grantsLanguages();
            }
        }

        // Apply sorting
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $validated['per_page'] ?? 15;
        $backgrounds = $query->paginate($perPage);

        return BackgroundResource::collection($backgrounds);
    }

    public function show(Background $background, BackgroundShowRequest $request)
    {
        $validated = $request->validated();

        // Load relationships based on validated 'include' parameter
        $includes = $validated['include'] ?? [
            'sources.source',
            'traits.randomTables.entries',
            'proficiencies.skill.abilityScore',
            'proficiencies.proficiencyType',
            'languages.language',
        ];

        $background->load($includes);

        return new BackgroundResource($background);
    }
}
