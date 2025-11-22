<?php

namespace App\Http\Controllers\Api;

use App\DTOs\MonsterSearchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\MonsterIndexRequest;
use App\Http\Requests\MonsterShowRequest;
use App\Http\Resources\MonsterResource;
use App\Http\Resources\SpellResource;
use App\Models\Monster;
use App\Services\MonsterSearchService;
use Dedoc\Scramble\Attributes\QueryParameter;
use MeiliSearch\Client;

class MonsterController extends Controller
{
    /**
     * List all monsters
     *
     * Returns a paginated list of D&D 5e monsters. Supports filtering by challenge rating,
     * type, size, alignment, and full-text search. All query parameters are validated
     * and documented automatically from the MonsterIndexRequest.
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression for advanced filtering. Supports operators: =, !=, >, >=, <, <=, AND, OR. Available fields: challenge_rating (string), type (string), size_code (string), alignment (string), armor_class (int), hit_points_average (int), experience_points (int).', example: 'challenge_rating >= 5 AND challenge_rating <= 10 AND type = dragon')]
    public function index(MonsterIndexRequest $request, MonsterSearchService $service, Client $meilisearch)
    {
        $dto = MonsterSearchDTO::fromRequest($request);

        // Use new Meilisearch filter syntax if provided
        if ($dto->meilisearchFilter !== null) {
            $monsters = $service->searchWithMeilisearch($dto, $meilisearch);
        } elseif ($dto->searchQuery !== null) {
            // Use Scout search with backwards-compatible filters
            $monsters = $service->buildScoutQuery($dto)->paginate($dto->perPage);
        } else {
            // Fallback to database query (no search, no filters)
            $monsters = $service->buildDatabaseQuery($dto)->paginate($dto->perPage);
        }

        return MonsterResource::collection($monsters);
    }

    /**
     * Get a single monster
     *
     * Returns detailed information about a specific monster including traits, actions,
     * legendary actions, spellcasting, modifiers, conditions, and source citations.
     * Supports selective relationship loading via the 'include' parameter.
     */
    public function show(MonsterShowRequest $request, Monster $monster)
    {
        $validated = $request->validated();

        // Default relationships
        $with = [
            'size',
            'traits',
            'actions',
            'legendaryActions',
            'spellcasting',
            'sources.source',
            'modifiers.abilityScore',
            'modifiers.skill',
            'modifiers.damageType',
            'conditions',
        ];

        // Use validated include parameter if provided
        if (isset($validated['include'])) {
            $with = $validated['include'];
        }

        $monster->load($with);

        return new MonsterResource($monster);
    }

    /**
     * Get all spells for a specific monster
     *
     * Returns a collection of spells that the monster can cast.
     * Empty collection for non-spellcasters. Spells are ordered by level then name.
     */
    public function spells(Monster $monster)
    {
        $monster->load(['entitySpells' => function ($query) {
            $query->orderBy('level')->orderBy('name');
        }, 'entitySpells.spellSchool']);

        return SpellResource::collection($monster->entitySpells);
    }
}
