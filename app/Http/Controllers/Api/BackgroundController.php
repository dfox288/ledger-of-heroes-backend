<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackgroundIndexRequest;
use App\Http\Requests\BackgroundShowRequest;
use App\Http\Resources\BackgroundResource;
use App\Models\Background;
use Illuminate\Support\Facades\Log;

class BackgroundController extends Controller
{
    /**
     * List all backgrounds
     *
     * Returns a paginated list of D&D 5e character backgrounds. Supports filtering by
     * proficiencies, skills, and languages. Includes random tables for personality traits,
     * ideals, bonds, and flaws. All query parameters are validated automatically.
     */
    public function index(BackgroundIndexRequest $request)
    {
        $validated = $request->validated();

        // Handle Scout search if 'q' parameter is provided
        if ($request->filled('q')) {
            return $this->searchBackgrounds($request, $validated);
        }

        // Standard database query for listing/filtering
        return $this->listBackgrounds($validated);
    }

    /**
     * Search backgrounds using Scout/Meilisearch
     */
    protected function searchBackgrounds(BackgroundIndexRequest $request, array $validated)
    {
        try {
            $perPage = $validated['per_page'] ?? 15;
            $search = Background::search($validated['q']);

            // Paginate search results
            $backgrounds = $search->paginate($perPage);

            return BackgroundResource::collection($backgrounds);
        } catch (\Exception $e) {
            // Log the failure and fall back to MySQL
            Log::warning('Meilisearch search failed, falling back to MySQL', [
                'query' => $validated['q'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackSearch($validated);
        }
    }

    /**
     * Fallback to MySQL FULLTEXT search when Meilisearch is unavailable
     */
    protected function fallbackSearch(array $validated)
    {
        $query = Background::with(['sources.source']);

        // Search by name
        if (isset($validated['q'])) {
            $query->where('name', 'LIKE', '%'.$validated['q'].'%');
        }

        // Apply filters
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
            if ((bool) $validated['grants_languages']) {
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

    /**
     * Standard database listing with filters (no search)
     */
    protected function listBackgrounds(array $validated)
    {
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

    /**
     * Get a single background
     *
     * Returns detailed information about a specific background including proficiencies,
     * traits with random tables (personality, ideals, bonds, flaws), languages, and sources.
     * Supports selective relationship loading via the 'include' parameter.
     */
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
