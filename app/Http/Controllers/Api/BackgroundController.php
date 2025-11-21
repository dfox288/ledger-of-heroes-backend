<?php

namespace App\Http\Controllers\Api;

use App\DTOs\BackgroundSearchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackgroundIndexRequest;
use App\Http\Requests\BackgroundShowRequest;
use App\Http\Resources\BackgroundResource;
use App\Models\Background;
use App\Services\BackgroundSearchService;
use Dedoc\Scramble\Attributes\QueryParameter;

class BackgroundController extends Controller
{
    /**
     * List all backgrounds
     *
     * Returns a paginated list of D&D 5e character backgrounds. Supports filtering by
     * proficiencies, skills, and languages. Includes random tables for personality traits,
     * ideals, bonds, and flaws. All query parameters are validated automatically.
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression for advanced filtering. Note: Backgrounds have limited filterable fields. Use search (q parameter) for most queries.', example: 'name = Acolyte')]
    public function index(BackgroundIndexRequest $request, BackgroundSearchService $service)
    {
        $dto = BackgroundSearchDTO::fromRequest($request);

        // Use Scout for full-text search, otherwise use database query
        if ($dto->searchQuery !== null) {
            $backgrounds = $service->buildScoutQuery($dto->searchQuery)->paginate($dto->perPage);
        } else {
            $backgrounds = $service->buildDatabaseQuery($dto)->paginate($dto->perPage);
        }

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
