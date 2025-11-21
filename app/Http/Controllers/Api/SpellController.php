<?php

namespace App\Http\Controllers\Api;

use App\DTOs\SpellSearchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\SpellIndexRequest;
use App\Http\Requests\SpellShowRequest;
use App\Http\Resources\SpellResource;
use App\Models\Spell;
use App\Services\SpellSearchService;
use Dedoc\Scramble\Attributes\QueryParameter;
use MeiliSearch\Client;

class SpellController extends Controller
{
    /**
     * List all spells
     *
     * Returns a paginated list of D&D 5e spells. Supports filtering by level, school,
     * concentration, ritual, and full-text search. All query parameters are validated
     * and documented automatically from the SpellIndexRequest.
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression for advanced filtering. Supports operators: =, !=, >, >=, <, <=, AND, OR. Available fields: level (int), school_code (string), concentration (bool), ritual (bool).', example: 'level >= 1 AND level <= 3 AND school_code = EV')]
    public function index(SpellIndexRequest $request, SpellSearchService $service, Client $meilisearch)
    {
        $dto = SpellSearchDTO::fromRequest($request);

        // Use new Meilisearch filter syntax if provided
        if ($dto->meilisearchFilter !== null) {
            try {
                $spells = $service->searchWithMeilisearch($dto, $meilisearch);
            } catch (\MeiliSearch\Exceptions\ApiException $e) {
                // Invalid filter syntax
                return response()->json([
                    'message' => 'Invalid filter syntax',
                    'error' => $e->getMessage(),
                ], 422);
            }

            return SpellResource::collection($spells);
        }

        // Use Scout search with backwards-compatible filters
        if ($dto->searchQuery !== null) {
            $spells = $service->buildScoutQuery($dto)->paginate($dto->perPage);

            return SpellResource::collection($spells);
        }

        // Fallback to database query (no search, no filters)
        $spells = $service->buildDatabaseQuery($dto)->paginate($dto->perPage);

        return SpellResource::collection($spells);
    }

    /**
     * Get a single spell
     *
     * Returns detailed information about a specific spell including relationships
     * like spell school, sources, damage effects, and associated classes.
     * Supports selective relationship loading via the 'include' parameter.
     */
    public function show(SpellShowRequest $request, Spell $spell)
    {
        $validated = $request->validated();

        // Load relationships based on validated 'include' parameter
        $includes = $validated['include'] ?? ['spellSchool', 'sources.source', 'effects.damageType', 'classes'];
        $spell->load($includes);

        return new SpellResource($spell);
    }
}
