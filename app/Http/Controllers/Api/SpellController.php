<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpellIndexRequest;
use App\Http\Requests\SpellShowRequest;
use App\Http\Resources\SpellResource;
use App\Models\Spell;
use Illuminate\Support\Facades\Log;

class SpellController extends Controller
{
    /**
     * List all spells
     *
     * Returns a paginated list of D&D 5e spells. Supports filtering by level, school,
     * concentration, ritual, and full-text search. All query parameters are validated
     * and documented automatically from the SpellIndexRequest.
     */
    public function index(SpellIndexRequest $request)
    {
        $validated = $request->validated();

        // Handle Scout search if 'q' parameter is provided
        if ($request->filled('q')) {
            return $this->searchSpells($request, $validated);
        }

        // Standard database query for listing/filtering
        return $this->listSpells($validated);
    }

    /**
     * Search spells using Scout/Meilisearch
     */
    protected function searchSpells(SpellIndexRequest $request, array $validated)
    {
        try {
            $perPage = $validated['per_page'] ?? 15;
            $search = Spell::search($validated['q']);

            // Apply filters using Scout's where() method
            if (isset($validated['level'])) {
                $search->where('level', $validated['level']);
            }

            if (isset($validated['school'])) {
                // Get school name for Meilisearch (it indexes the name, not ID)
                $schoolId = $validated['school'];
                $schoolName = \App\Models\SpellSchool::find($schoolId)?->name;
                if ($schoolName) {
                    $search->where('school_name', $schoolName);
                }
            }

            if (isset($validated['concentration'])) {
                $search->where('concentration', $request->boolean('concentration'));
            }

            if (isset($validated['ritual'])) {
                $search->where('ritual', $request->boolean('ritual'));
            }

            // Paginate search results
            $spells = $search->paginate($perPage);

            return SpellResource::collection($spells);
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
        $query = Spell::with(['spellSchool', 'sources.source', 'effects.damageType', 'classes']);

        // MySQL FULLTEXT search
        if (isset($validated['q'])) {
            $search = $validated['q'];
            $query->whereRaw(
                'MATCH(name, description) AGAINST(? IN NATURAL LANGUAGE MODE)',
                [$search]
            );
        }

        // Apply filters
        if (isset($validated['level'])) {
            $query->level($validated['level']);
        }

        if (isset($validated['school'])) {
            $query->school($validated['school']);
        }

        if (isset($validated['concentration'])) {
            $query->concentration($validated['concentration']);
        }

        if (isset($validated['ritual'])) {
            $query->ritual($validated['ritual']);
        }

        // Apply sorting
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $validated['per_page'] ?? 15;
        $spells = $query->paginate($perPage);

        return SpellResource::collection($spells);
    }

    /**
     * Standard database listing with filters (no search)
     */
    protected function listSpells(array $validated)
    {
        $query = Spell::with(['spellSchool', 'sources.source', 'effects.damageType', 'classes']);

        // Apply search filter (legacy 'search' parameter using LIKE)
        if (isset($validated['search'])) {
            $query->search($validated['search']);
        }

        // Apply filters
        if (isset($validated['level'])) {
            $query->level($validated['level']);
        }

        if (isset($validated['school'])) {
            $query->school($validated['school']);
        }

        if (isset($validated['concentration'])) {
            $query->concentration($validated['concentration']);
        }

        if (isset($validated['ritual'])) {
            $query->ritual($validated['ritual']);
        }

        // Apply sorting
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $validated['per_page'] ?? 15;
        $spells = $query->paginate($perPage);

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
