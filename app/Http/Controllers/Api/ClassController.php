<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ClassSearchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClassIndexRequest;
use App\Http\Requests\ClassShowRequest;
use App\Http\Requests\ClassSpellListRequest;
use App\Http\Resources\ClassResource;
use App\Http\Resources\SpellResource;
use App\Models\CharacterClass;
use App\Services\ClassSearchService;
use Dedoc\Scramble\Attributes\QueryParameter;

class ClassController extends Controller
{
    /**
     * List all classes and subclasses
     *
     * Returns a paginated list of D&D 5e character classes and subclasses. Includes hit dice,
     * spellcasting abilities, proficiencies, class features, level progression tables, and
     * subclass options. Supports filtering by proficiencies, skills, and saving throws.
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression for advanced filtering. Supports operators: =, !=, >, >=, <, <=, AND, OR. Available fields: hit_die (int), is_spellcaster (bool), spellcasting_ability_code (string), is_subclass (bool).', example: 'is_spellcaster = true AND hit_die >= 8')]
    public function index(ClassIndexRequest $request, ClassSearchService $service)
    {
        $dto = ClassSearchDTO::fromRequest($request);

        if ($dto->searchQuery !== null) {
            $classes = $service->buildScoutQuery($dto->searchQuery)->paginate($dto->perPage);
        } else {
            $classes = $service->buildDatabaseQuery($dto)->paginate($dto->perPage);
        }

        return ClassResource::collection($classes);
    }

    /**
     * Get a single class
     *
     * Returns detailed information about a specific class or subclass including parent class,
     * subclasses, proficiencies, traits, features, level progression, spell slot tables,
     * and counters. Supports selective relationship loading via the 'include' parameter.
     */
    public function show(CharacterClass $class, ClassShowRequest $request)
    {
        $validated = $request->validated();

        // Default relationships
        $relationships = [
            'spellcastingAbility',
            'parentClass',
            'subclasses',
            'proficiencies.proficiencyType',
            'proficiencies.skill.abilityScore',
            'proficiencies.abilityScore',
            'traits.randomTables.entries',
            'sources.source',
            'features',
            'levelProgression',
            'counters',
            'subclasses.features',
            'subclasses.counters',
            'tags',
        ];

        // Use custom includes if provided
        if (isset($validated['include'])) {
            $relationships = $validated['include'];
        }

        $class->load($relationships);

        return new ClassResource($class);
    }

    /**
     * Get spells available to a class
     *
     * Returns a paginated list of spells available to a specific class. Supports the same
     * filtering options as the main spell list (level, school, concentration, ritual).
     * Useful for building spell lists for spellcasting classes.
     */
    public function spells(CharacterClass $class, ClassSpellListRequest $request)
    {
        $validated = $request->validated();

        $query = $class->spells()
            ->with(['spellSchool', 'sources.source', 'effects.damageType', 'classes']);

        // Apply same filters as SpellController
        if (isset($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('spells.name', 'LIKE', "%{$validated['search']}%")
                    ->orWhere('spells.description', 'LIKE', "%{$validated['search']}%");
            });
        }

        if (isset($validated['level'])) {
            $query->where('spells.level', $validated['level']);
        }

        if (isset($validated['school'])) {
            $query->where('spells.spell_school_id', $validated['school']);
        }

        if (isset($validated['concentration'])) {
            $query->where('spells.needs_concentration', $validated['concentration']);
        }

        if (isset($validated['ritual'])) {
            $query->where('spells.is_ritual', $validated['ritual']);
        }

        // Sorting
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';

        // Ensure we prefix with table name for pivot queries
        if (! str_contains($sortBy, '.')) {
            $sortBy = 'spells.'.$sortBy;
        }

        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $validated['per_page'] ?? 15;
        $spells = $query->paginate($perPage);

        return SpellResource::collection($spells);
    }
}
