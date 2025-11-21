<?php

namespace App\Http\Controllers\Api;

use App\DTOs\FeatSearchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\FeatIndexRequest;
use App\Http\Requests\FeatShowRequest;
use App\Http\Resources\FeatResource;
use App\Models\Feat;
use App\Services\FeatSearchService;
use Dedoc\Scramble\Attributes\QueryParameter;

class FeatController extends Controller
{
    /**
     * List all feats
     *
     * Returns a paginated list of D&D 5e feats. Supports advanced filtering by prerequisites
     * (race, ability scores, proficiencies), granted benefits (skills, proficiencies),
     * and full-text search. All query parameters are validated and documented automatically.
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression for advanced filtering. Note: Prerequisites are stored relationally. Use legacy parameters (prerequisite_race, prerequisite_ability) for prerequisite filtering.', example: 'name = "War Caster"')]
    public function index(FeatIndexRequest $request, FeatSearchService $service)
    {
        $dto = FeatSearchDTO::fromRequest($request);

        if ($dto->searchQuery !== null) {
            $feats = $service->buildScoutQuery($dto->searchQuery)->paginate($dto->perPage);
        } else {
            $feats = $service->buildDatabaseQuery($dto)->paginate($dto->perPage);
        }

        return FeatResource::collection($feats);
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
            'tags',
        ];

        // Use validated include parameter if provided
        if (isset($validated['include'])) {
            $with = $validated['include'];
        }

        $feat->load($with);

        return new FeatResource($feat);
    }
}
