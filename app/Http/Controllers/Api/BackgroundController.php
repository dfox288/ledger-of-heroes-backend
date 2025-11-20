<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackgroundIndexRequest;
use App\Http\Requests\BackgroundShowRequest;
use App\Http\Resources\BackgroundResource;
use App\Models\Background;

class BackgroundController extends Controller
{
    /**
     * List all backgrounds with optional filters
     *
     * @queryParam grants_proficiency string Filter by granted proficiency type (e.g., "longsword", "light armor")
     * @queryParam grants_skill string Filter by granted skill (e.g., "stealth", "perception")
     * @queryParam speaks_language string Filter by spoken language (e.g., "elvish", "dwarvish")
     * @queryParam language_choice_count integer Filter by number of language choices (0-10)
     * @queryParam grants_languages boolean Filter backgrounds that grant any languages
     * @queryParam search string Search by name (max 255 characters)
     * @queryParam sort_by string Sort by field (name, created_at, updated_at)
     * @queryParam sort_direction string Sort direction (asc, desc)
     * @queryParam per_page integer Items per page (1-100, default 15)
     * @queryParam page integer Page number (min 1)
     */
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
