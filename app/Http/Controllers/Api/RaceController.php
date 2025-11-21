<?php

namespace App\Http\Controllers\Api;

use App\DTOs\RaceSearchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\RaceIndexRequest;
use App\Http\Requests\RaceShowRequest;
use App\Http\Resources\RaceResource;
use App\Models\Race;
use App\Services\RaceSearchService;
use Dedoc\Scramble\Attributes\QueryParameter;

class RaceController extends Controller
{
    /**
     * List all races and subraces
     *
     * Returns a paginated list of D&D 5e races and subraces. Supports filtering by
     * proficiencies, skills, languages, size, and speed. Includes ability score modifiers,
     * racial traits, and language options. All query parameters are validated automatically.
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression for advanced filtering. Supports operators: =, !=, >, >=, <, <=, AND, OR. Available fields: size (string), speed (int), has_darkvision (bool), darkvision_range (int).', example: 'speed >= 30 AND has_darkvision = true')]
    public function index(RaceIndexRequest $request, RaceSearchService $service)
    {
        $dto = RaceSearchDTO::fromRequest($request);

        if ($dto->searchQuery !== null) {
            $races = $service->buildScoutQuery($dto)->paginate($dto->perPage);
        } else {
            $races = $service->buildDatabaseQuery($dto)->paginate($dto->perPage);
        }

        return RaceResource::collection($races);
    }

    /**
     * Get a single race
     *
     * Returns detailed information about a specific race or subrace including parent race,
     * subraces, ability modifiers, proficiencies, traits, languages, and spells.
     * Supports selective relationship loading via the 'include' parameter.
     */
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
                'tags',
            ]);
        }

        return new RaceResource($race);
    }
}
