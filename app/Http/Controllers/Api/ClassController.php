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
use App\Services\Cache\EntityCacheService;
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
     * - By hit die: `GET /api/v1/classes?hit_die=12`
     * - Spellcasters only: `GET /api/v1/classes?is_spellcaster=true`
     * - Non-spellcasters: `GET /api/v1/classes?is_spellcaster=false`
     * - Classes with 9th level spells: `GET /api/v1/classes?max_spell_level=9`
     * - Combined filters: `GET /api/v1/classes?hit_die=10&is_spellcaster=true` (Paladin, Ranger)
     *
     * **Spell Filtering Examples (Meilisearch):**
     * - Single spell: `GET /api/v1/classes?filter=spell_slugs IN [fireball]`
     * - Healing classes: `GET /api/v1/classes?filter=spell_slugs IN [cure-wounds, healing-word]`
     * - Full casters: `GET /api/v1/classes?filter=tag_slugs IN [full-caster]`
     *
     * **Note on Spell Filtering:**
     * Spell filtering now uses Meilisearch `?filter=` syntax exclusively.
     * Legacy parameters like `?spells=`, `?spell_level=`, `?max_spell_level=` have been removed.
     * Use `?filter=spell_slugs IN [spell1, spell2]` instead.
     *
     * **Use Cases:**
     * - **Multiclass Planning:** Which classes get Fireball? (`?filter=spell_slugs IN [fireball]`)
     * - **Healer Identification:** Classes with healing magic (`?filter=spell_slugs IN [cure-wounds, healing-word]`)
     * - **Full Spellcasters:** Classes with 9th level spells (`?filter=tag_slugs IN [full-caster]`)
     * - **Optimization:** Find INT-based spellcasters (`?filter=spellcasting_ability = INT`)
     * - **Build Planning:** Wisdom casters (`?filter=spellcasting_ability = WIS`)
     *
     * **Tag-Based Filtering Examples (Meilisearch):**
     * - Full spellcasters: `GET /api/v1/classes?filter=tag_slugs IN [full-caster]`
     * - Martial classes: `GET /api/v1/classes?filter=tag_slugs IN [martial]`
     * - Half casters: `GET /api/v1/classes?filter=tag_slugs IN [half-caster]`
     * - Combined filters: `GET /api/v1/classes?filter=tag_slugs IN [spellcaster] AND hit_die >= 8`
     *
     * **Base Class vs Subclass Filtering (Meilisearch):**
     * - Base classes only: `GET /api/v1/classes?filter=is_subclass = false`
     * - Subclasses only: `GET /api/v1/classes?filter=is_subclass = true`
     * - Base spellcasters: `GET /api/v1/classes?filter=is_subclass = false AND tag_slugs IN [spellcaster]`
     * - High HP subclasses: `GET /api/v1/classes?filter=is_subclass = true AND hit_die >= 10`
     *
     * **Parameter Reference:**
     * - `is_spellcaster` (bool): Filter by spellcasting ability (true=has spellcasting, false=no spellcasting)
     * - `hit_die` (int): Filter by hit die size (6, 8, 10, or 12)
     * - `max_spell_level` (int): Filter classes that have spells of this level (0-9)
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
    #[QueryParameter('filter', description: 'Meilisearch filter expression for advanced filtering. Supports operators: =, !=, >, >=, <, <=, AND, OR, IN. Available fields: hit_die (int), is_spellcaster (bool), spellcasting_ability_code (string), is_subclass (bool), tag_slugs (array).', example: 'is_subclass = false AND tag_slugs IN [spellcaster]')]
    public function index(ClassIndexRequest $request, ClassSearchService $service)
    {
        $dto = ClassSearchDTO::fromRequest($request);

        if ($dto->searchQuery !== null) {
            // Scout search - paginate fi
            // rst, then eager-load relationships
            $classes = $service->buildScoutQuery($dto->searchQuery)->paginate($dto->perPage);
            $classes->load($service->getDefaultRelationships());
        } else {
            // Database query - relationships already eager-loaded via with()
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
     *
     * **Feature Inheritance for Subclasses:**
     * - By default, subclasses return ALL features (inherited base class features + subclass-specific features)
     * - Use `?include_base_features=false` to return only subclass-specific features
     * - Base classes are unaffected by this parameter
     *
     * **Examples:**
     * - Get Arcane Trickster with all 40 features: `GET /classes/rogue-arcane-trickster`
     * - Get only Arcane Trickster's 6 unique features: `GET /classes/rogue-arcane-trickster?include_base_features=false`
     * - Get base Rogue (always 34 features): `GET /classes/rogue`
     */
    public function show(ClassShowRequest $request, CharacterClass $class, EntityCacheService $cache, ClassSearchService $service)
    {
        $validated = $request->validated();

        // Default relationships from service
        $defaultRelationships = $service->getShowRelationships();

        // Try cache first
        $cachedClass = $cache->getClass($class->id);

        if ($cachedClass) {
            // If include parameter provided, use it; otherwise load defaults
            $includes = $validated['include'] ?? $defaultRelationships;

            // If this is a subclass and we're including base features, eager-load parent features
            $includeBaseFeatures = $request->boolean('include_base_features', true);
            if ($includeBaseFeatures && $cachedClass->parent_class_id !== null && in_array('features', $includes)) {
                $includes[] = 'parentClass.features';
            }

            $cachedClass->load($includes);

            return new ClassResource($cachedClass);
        }

        // Fallback to route model binding result (should rarely happen)
        $includes = $validated['include'] ?? $defaultRelationships;

        // If this is a subclass and we're including base features, eager-load parent features
        $includeBaseFeatures = $request->boolean('include_base_features', true);
        if ($includeBaseFeatures && $class->parent_class_id !== null && in_array('features', $includes)) {
            $includes[] = 'parentClass.features';
        }

        $class->load($includes);

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
