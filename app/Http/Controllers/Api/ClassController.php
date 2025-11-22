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
     * subclass options. Supports filtering by proficiencies, skills, saving throws, and spells.
     *
     * **Basic Examples:**
     * - All classes: `GET /api/v1/classes`
     * - Base classes only: `GET /api/v1/classes?base_only=1`
     * - By hit die: `GET /api/v1/classes?filter=hit_die = 12`
     * - Spellcasters only: `GET /api/v1/classes?filter=is_spellcaster = true`
     *
     * **Spell Filtering Examples:**
     * - Single spell: `GET /api/v1/classes?spells=fireball`
     * - Multiple spells (AND): `GET /api/v1/classes?spells=fireball,counterspell`
     * - Multiple spells (OR): `GET /api/v1/classes?spells=cure-wounds,healing-word&spells_operator=OR`
     * - Spell level: `GET /api/v1/classes?spell_level=9`
     * - Combined: `GET /api/v1/classes?spells=fireball&spell_level=3&base_only=1`
     *
     * **Spell Filtering Logic:**
     * - AND (default): Class must have ALL specified spells
     * - OR: Class must have AT LEAST ONE specified spell
     * - Spell slugs are case-insensitive (fireball = FIREBALL)
     * - Use spell slugs, not IDs (e.g., "cure-wounds" not "Cure Wounds")
     *
     * **Use Cases:**
     * - **Multiclass Planning:** Which classes get Fireball? (`?spells=fireball`)
     * - **Healer Identification:** Classes with healing magic (`?spells=cure-wounds,healing-word&spells_operator=OR`)
     * - **Full Spellcasters:** Classes with 9th level spells (`?spell_level=9`)
     * - **Optimization:** Find INT-based spellcasters (`?filter=spellcasting_ability_code = INT`)
     * - **Build Planning:** Cleric or Paladin with specific spells (`?spells=revivify&filter=spellcasting_ability_code = WIS OR spellcasting_ability_code = CHA`)
     *
     * **Parameter Reference:**
     * - `spells` (string): Comma-separated spell slugs (max 500 chars)
     * - `spells_operator` (string): "AND" or "OR" (default: AND)
     * - `spell_level` (int): Spell level 0-9 (0=cantrip, 9=9th level)
     * - `base_only` (bool): Filter to base classes only (exclude subclasses)
     * - `grants_proficiency` (string): Filter by proficiency type
     * - `grants_skill` (string): Filter by skill proficiency
     * - `grants_saving_throw` (string): Filter by saving throw proficiency
     * - `filter` (string): Meilisearch filter expression (see Scramble docs)
     *
     * **Data Source:**
     * - 1,917 class-spell relationships across 63 classes/subclasses
     * - Spell filtering powered by `class_spells` pivot table
     * - Results include subclasses in nested `subclasses` array
     *
     * See `docs/API-EXAMPLES.md` for comprehensive usage examples.
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
