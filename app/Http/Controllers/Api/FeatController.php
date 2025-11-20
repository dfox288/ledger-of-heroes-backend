<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FeatIndexRequest;
use App\Http\Requests\FeatShowRequest;
use App\Http\Resources\FeatResource;
use App\Models\Feat;
use Illuminate\Support\Facades\Log;

class FeatController extends Controller
{
    /**
     * List all feats
     *
     * Returns a paginated list of D&D 5e feats. Supports advanced filtering by prerequisites
     * (race, ability scores, proficiencies), granted benefits (skills, proficiencies),
     * and full-text search. All query parameters are validated and documented automatically.
     */
    public function index(FeatIndexRequest $request)
    {
        $validated = $request->validated();
        $perPage = $validated['per_page'] ?? 15;

        if ($request->filled('q')) {
            try {
                $feats = $this->performScoutSearch($request, $validated, $perPage);
            } catch (\Exception $e) {
                Log::warning('Meilisearch search failed, falling back to MySQL', [
                    'query' => $validated['q'] ?? null,
                    'error' => $e->getMessage(),
                ]);
                $feats = $this->performMysqlSearch($validated, $perPage);
            }
        } else {
            $feats = $this->buildStandardQuery($validated)->paginate($perPage);
        }

        return FeatResource::collection($feats);
    }

    /**
     * Search feats using Scout/Meilisearch
     */
    protected function performScoutSearch(FeatIndexRequest $request, array $validated, int $perPage)
    {
        return Feat::search($validated['q'])->paginate($perPage);
    }

    /**
     * Fallback to MySQL FULLTEXT search when Meilisearch is unavailable
     */
    protected function performMysqlSearch(array $validated, int $perPage)
    {
        $query = Feat::with(['sources.source', 'prerequisites.prerequisite']);

        // MySQL FULLTEXT search
        if (isset($validated['q'])) {
            $search = $validated['q'];
            $query->whereRaw(
                'MATCH(name, description) AGAINST(? IN NATURAL LANGUAGE MODE)',
                [$search]
            );
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

        return $query->paginate($perPage);
    }

    /**
     * Standard database listing with filters (no search)
     */
    protected function buildStandardQuery(array $validated)
    {
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

        return $query;
    }

    /**
     * Get a single feat
     *
     * Returns detailed information about a specific feat including modifiers, proficiencies,
     * conditions, prerequisites, and source citations. Supports selective relationship loading.
     */
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
