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
use MeiliSearch\Client;

class ClassController extends Controller
{
    /**
     * List all classes and subclasses
     *
     * Returns a paginated list of D&D 5e character classes and subclasses. Includes hit dice,
     * spellcasting abilities, proficiencies, class features, level progression tables, and
     * subclass options. Uses Meilisearch for filtering and search.
     *
     * **Filtering Examples:**
     * - Base classes only: `GET /api/v1/classes?filter=is_subclass = false`
     * - Subclasses only: `GET /api/v1/classes?filter=is_subclass = true`
     * - High HP classes: `GET /api/v1/classes?filter=hit_die >= 10`
     * - Barbarian and Fighter: `GET /api/v1/classes?filter=hit_die = 12`
     * - Spellcasters: `GET /api/v1/classes?filter=spellcasting_ability != null`
     * - INT-based casters: `GET /api/v1/classes?filter=spellcasting_ability = INT`
     * - WIS-based casters: `GET /api/v1/classes?filter=spellcasting_ability = WIS`
     * - Full casters: `GET /api/v1/classes?filter=tag_slugs IN [full-caster]`
     * - Martial classes: `GET /api/v1/classes?filter=tag_slugs IN [martial]`
     * - Combined: `GET /api/v1/classes?filter=is_subclass = false AND tag_slugs IN [spellcaster]`
     *
     * **Use Cases:**
     * - **Build Planning:** Find INT-based spellcasters for Wizard multiclass synergy
     * - **Optimization:** Identify high HP classes for tanking (hit_die >= 10)
     * - **Spellcasting:** Filter by spellcasting ability for multiclass requirements
     * - **Class Archetype:** Find all martial, spellcaster, or hybrid classes via tags
     *
     * **Available Filterable Fields:**
     * - `id` (int): Class ID
     * - `slug` (string): Class slug (e.g., "wizard", "fighter-champion")
     * - `hit_die` (int): Hit die size (6, 8, 10, or 12)
     * - `primary_ability` (string): Primary ability (e.g., "STR", "INT")
     * - `spellcasting_ability` (string): Spellcasting ability code (e.g., "INT", "WIS", "CHA", null for non-casters)
     * - `source_codes` (array): Source book codes (e.g., ["PHB"], ["XGTE"])
     * - `is_subclass` (bool): Whether this is a subclass (true) or base class (false)
     * - `parent_class_name` (string): Parent class name for subclasses
     * - `tag_slugs` (array): Tag slugs (e.g., ["spellcaster"], ["martial"], ["full-caster"])
     *
     * See `docs/API-EXAMPLES.md` for comprehensive usage examples.
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression for advanced filtering. Supports operators: =, !=, >, >=, <, <=, AND, OR, IN. Available fields: id, slug, hit_die, primary_ability, spellcasting_ability, is_spellcaster, source_codes, is_subclass, parent_class_name, tag_slugs, has_spells, spell_count, max_spell_level, saving_throw_proficiencies, armor_proficiencies, weapon_proficiencies, tool_proficiencies, skill_proficiencies.', example: 'is_spellcaster = true AND hit_die >= 8')]
    public function index(ClassIndexRequest $request, ClassSearchService $service, Client $meilisearch)
    {
        $dto = ClassSearchDTO::fromRequest($request);

        // Use Meilisearch for ANY search query or filter expression
        // This enables filter-only queries without requiring ?q= parameter
        if ($dto->searchQuery !== null || $dto->meilisearchFilter !== null) {
            $classes = $service->searchWithMeilisearch($dto, $meilisearch);
        } else {
            // Database query for pure pagination (no search/filter)
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

            // Ensure parentClass relationship is loaded if features are requested
            // (Resource will use getAllFeatures() to handle inheritance)
            if (in_array('features', $includes) && ! in_array('parentClass', $includes)) {
                $includes[] = 'parentClass';
            }

            $cachedClass->load($includes);

            return new ClassResource($cachedClass);
        }

        // Fallback to route model binding result (should rarely happen)
        $includes = $validated['include'] ?? $defaultRelationships;

        // Ensure parentClass relationship is loaded if features are requested
        // (Resource will use getAllFeatures() to handle inheritance)
        if (in_array('features', $includes) && ! in_array('parentClass', $includes)) {
            $includes[] = 'parentClass';
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
