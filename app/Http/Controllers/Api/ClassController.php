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
     * Display a paginated, searchable, and filterable list of character classes.
     *
     * ## Common Examples
     *
     * ```bash
     * # All base classes (no subclasses)
     * GET /api/v1/classes?filter=is_base_class = true
     *
     * # Full spellcasters (9th level spells)
     * GET /api/v1/classes?filter=max_spell_level = 9
     *
     * # Classes with heavy armor proficiency (tanky classes)
     * GET /api/v1/classes?filter=armor_proficiencies IN ["Heavy Armor"]
     *
     * # Wisdom-based spellcasters (Cleric, Druid, Ranger)
     * GET /api/v1/classes?filter=spellcasting_ability = "WIS"
     *
     * # Classes with Dexterity save proficiency (evasion)
     * GET /api/v1/classes?filter=saving_throw_proficiencies IN ["DEX"]
     *
     * # Tanky spellcasters (heavy armor + spellcasting)
     * GET /api/v1/classes?filter=is_spellcaster = true AND armor_proficiencies IN ["Heavy Armor"]
     *
     * # High hit die classes (d10 or d12) for durability
     * GET /api/v1/classes?filter=hit_die >= 10
     * ```
     *
     * ## Filterable Fields by Data Type
     *
     * ### Integer Fields
     *
     * **id** (`integer`)
     * - Value range: 1 to 131
     * - Operators: `=`, `!=`, `<`, `<=`, `>`, `>=`, `IN`, `NOT IN`
     * - Examples:
     *   - `filter=id = 1` (Fighter base class)
     *   - `filter=id IN [1, 2, 3]` (Fighter, Wizard, Cleric)
     * - Use case: Direct lookups when ID is known
     *
     * **hit_die** (`integer`)
     * - Value range: 6, 8, 10, 12 (d6, d8, d10, d12)
     * - Operators: `=`, `!=`, `<`, `<=`, `>`, `>=`, `IN`, `NOT IN`
     * - Examples:
     *   - `filter=hit_die = 12` (Barbarian - highest HP)
     *   - `filter=hit_die >= 10` (d10/d12 classes for durability)
     *   - `filter=hit_die = 6` (Wizard, Sorcerer - low HP)
     * - Use case: Character optimization for survivability
     *
     * **spell_count** (`integer`)
     * - Value range: 0 to 477 (number of spells available to this class)
     * - Operators: `=`, `!=`, `<`, `<=`, `>`, `>=`, `IN`, `NOT IN`
     * - Examples:
     *   - `filter=spell_count > 100` (classes with large spell lists)
     *   - `filter=spell_count = 0` (non-spellcasting classes)
     * - Use case: Finding classes with extensive spell options
     *
     * **max_spell_level** (`integer`)
     * - Value range: 0 to 9 (highest spell level available)
     * - Operators: `=`, `!=`, `<`, `<=`, `>`, `>=`, `IN`, `NOT IN`
     * - Examples:
     *   - `filter=max_spell_level = 9` (full casters: Wizard, Cleric, Bard, Druid, Sorcerer, Warlock)
     *   - `filter=max_spell_level = 5` (half casters: Paladin, Ranger)
     *   - `filter=max_spell_level IN [0, 1, 2, 3, 4]` (non-casters and third casters)
     * - Use case: Distinguishing full/half/third casters for multiclass planning
     *
     * ### String Fields
     *
     * **slug** (`string`)
     * - Value examples: "fighter", "champion", "eldritch-knight", "wizard"
     * - Operators: `=`, `!=`, `IN`, `NOT IN`
     * - Examples:
     *   - `filter=slug = "fighter"` (Fighter base class)
     *   - `filter=slug IN ["champion", "battle-master", "eldritch-knight"]` (Fighter subclasses)
     * - Use case: Human-readable queries and URL-friendly lookups
     *
     * **primary_ability** (`string`)
     * - Value range: "STR", "DEX", "CON", "INT", "WIS", "CHA"
     * - Operators: `=`, `!=`, `IN`, `NOT IN`
     * - Examples:
     *   - `filter=primary_ability = "STR"` (Barbarian, Fighter, Paladin)
     *   - `filter=primary_ability IN ["INT", "WIS", "CHA"]` (mental ability classes)
     * - Use case: Finding classes that benefit from specific ability scores
     *
     * **spellcasting_ability** (`string`)
     * - Value range: "INT", "WIS", "CHA", null (for non-spellcasters)
     * - Operators: `=`, `!=`, `IN`, `NOT IN`, `IS NULL`, `IS NOT NULL`
     * - Examples:
     *   - `filter=spellcasting_ability = "INT"` (Wizard, Artificer)
     *   - `filter=spellcasting_ability = "WIS"` (Cleric, Druid, Ranger)
     *   - `filter=spellcasting_ability = "CHA"` (Bard, Sorcerer, Warlock, Paladin)
     *   - `filter=spellcasting_ability IS NULL` (non-spellcasters)
     * - Use case: Multiclass optimization - matching spellcasting abilities to avoid MAD (Multiple Ability Dependency)
     *
     * **parent_class_name** (`string`)
     * - Value examples: "Fighter", "Wizard", "Cleric", null (for base classes)
     * - Operators: `=`, `!=`, `IN`, `NOT IN`, `IS NULL`, `IS NOT NULL`
     * - Examples:
     *   - `filter=parent_class_name = "Fighter"` (all Fighter subclasses)
     *   - `filter=parent_class_name IS NULL` (base classes only)
     * - Use case: Finding subclasses for a specific base class
     *
     * ### Boolean Fields
     *
     * **is_base_class** (`boolean`)
     * - Value range: `true` (12 base classes), `false` (119 subclasses)
     * - Operators: `=`, `!=`
     * - Examples:
     *   - `filter=is_base_class = true` (Barbarian, Bard, Cleric, Druid, Fighter, Monk, Paladin, Ranger, Rogue, Sorcerer, Warlock, Wizard)
     *   - `filter=is_base_class = false` (all subclasses)
     * - Use case: Character creation - choose base class first, then filter subclasses
     *
     * **is_subclass** (`boolean`)
     * - Value range: `true` (119 subclasses), `false` (12 base classes)
     * - Operators: `=`, `!=`
     * - Examples:
     *   - `filter=is_subclass = true` (all subclasses)
     *   - `filter=is_subclass = false` (base classes)
     * - Use case: Inverse of `is_base_class` - useful for API consistency
     *
     * **has_spells** (`boolean`)
     * - Value range: `true` (class has spell list), `false` (no spell list)
     * - Operators: `=`, `!=`
     * - Examples:
     *   - `filter=has_spells = true` (spellcasting classes and subclasses)
     *   - `filter=has_spells = false` (martial classes without spells)
     * - Use case: Quick filter for classes with magical abilities
     *
     * **is_spellcaster** (`boolean`)
     * - Value range: `true` (class/subclass gains spellcasting feature), `false` (no spellcasting)
     * - Operators: `=`, `!=`
     * - Examples:
     *   - `filter=is_spellcaster = true` (full/half/third casters)
     *   - `filter=is_spellcaster = false` (pure martial classes)
     * - Use case: Distinguishing classes with Spellcasting feature vs spell-like abilities
     *
     * ### Array Fields
     *
     * **source_codes** (`array of strings`)
     * - Value examples: ["PHB"], ["XGTE"], ["TCOE"], ["PHB", "XGTE"]
     * - Operators: `IN`, `NOT IN` (checks if ANY value in field matches ANY value in filter)
     * - Examples:
     *   - `filter=source_codes IN ["PHB"]` (Player's Handbook classes)
     *   - `filter=source_codes IN ["XGTE", "TCOE"]` (Xanathar's or Tasha's content)
     * - Use case: Filtering by allowed sourcebooks for campaigns
     *
     * **tag_slugs** (`array of strings`)
     * - Value examples: ["spellcasting"], ["martial"], ["stealth"], ["healing"]
     * - Operators: `IN`, `NOT IN`
     * - Examples:
     *   - `filter=tag_slugs IN ["spellcasting"]` (classes with spellcasting tag)
     *   - `filter=tag_slugs IN ["martial"]` (weapon-focused classes)
     * - Use case: Thematic filtering for character concepts
     *
     * **saving_throw_proficiencies** (`array of strings`)
     * - Value range: ["STR"], ["DEX"], ["CON"], ["INT"], ["WIS"], ["CHA"] (always 2 per class)
     * - Operators: `IN`, `NOT IN`
     * - Examples:
     *   - `filter=saving_throw_proficiencies IN ["DEX"]` (Rogue, Monk, Ranger - evasion synergy)
     *   - `filter=saving_throw_proficiencies IN ["WIS"]` (Cleric, Druid, Paladin, Ranger, Monk - mind control resistance)
     *   - `filter=saving_throw_proficiencies IN ["CON"]` (Fighter, Barbarian, Sorcerer - concentration checks)
     * - Use case: **Critical for multiclass planning** - you gain new saving throw proficiencies ONLY from your first class
     *
     * **armor_proficiencies** (`array of strings`)
     * - Value examples: ["Light Armor"], ["Medium Armor"], ["Heavy Armor"], ["Shields"], specific armor types
     * - Operators: `IN`, `NOT IN`
     * - Examples:
     *   - `filter=armor_proficiencies IN ["Heavy Armor"]` (Cleric, Fighter, Paladin - AC optimization)
     *   - `filter=armor_proficiencies IN ["Shields"]` (classes that can use shields)
     *   - `filter=armor_proficiencies IN ["Light Armor"]` (Rogue, Ranger, Bard - DEX-based AC)
     * - Use case: **Multiclass AC planning** - determines maximum AC without multiclassing
     *
     * **weapon_proficiencies** (`array of strings`)
     * - Value examples: ["Simple Weapons"], ["Martial Weapons"], specific weapon names
     * - Operators: `IN`, `NOT IN`
     * - Examples:
     *   - `filter=weapon_proficiencies IN ["Martial Weapons"]` (Fighter, Paladin, Ranger, Barbarian)
     *   - `filter=weapon_proficiencies IN ["Simple Weapons"]` (most classes)
     * - Use case: **Weapon optimization** - finding classes that can use specific weapons
     *
     * **tool_proficiencies** (`array of strings`)
     * - Value examples: ["Thieves' Tools"], ["Smith's Tools"], ["Alchemist's Supplies"]
     * - Operators: `IN`, `NOT IN`
     * - Examples:
     *   - `filter=tool_proficiencies IN ["Thieves' Tools"]` (Rogue, some Artificer subclasses)
     *   - `filter=tool_proficiencies IN ["Alchemist's Supplies"]` (Artificer)
     * - Use case: Finding classes with specific tool proficiencies for crafting/utility
     *
     * **skill_proficiencies** (`array of strings`)
     * - Value examples: ["Stealth"], ["Perception"], ["Insight"], ["Athletics"]
     * - Operators: `IN`, `NOT IN`
     * - Examples:
     *   - `filter=skill_proficiencies IN ["Stealth"]` (Rogue, Ranger - sneaky classes)
     *   - `filter=skill_proficiencies IN ["Perception"]` (Ranger, Druid - wilderness awareness)
     * - Note: This field contains **available** skill choices, not guaranteed proficiencies
     * - Use case: Finding classes with access to specific skills
     *
     * ## Complex Filter Examples
     *
     * ```bash
     * # Tanky spellcasters (heavy armor + full casting)
     * GET /api/v1/classes?filter=armor_proficiencies IN ["Heavy Armor"] AND max_spell_level = 9
     *
     * # Wisdom-based full casters with Wisdom save proficiency
     * GET /api/v1/classes?filter=spellcasting_ability = "WIS" AND max_spell_level = 9 AND saving_throw_proficiencies IN ["WIS"]
     *
     * # Durable half-casters (d10 hit die + 5th level spells)
     * GET /api/v1/classes?filter=hit_die = 10 AND max_spell_level = 5
     *
     * # PHB base classes with heavy armor proficiency
     * GET /api/v1/classes?filter=source_codes IN ["PHB"] AND is_base_class = true AND armor_proficiencies IN ["Heavy Armor"]
     *
     * # Charisma-based spellcasters (multiclass synergy)
     * GET /api/v1/classes?filter=spellcasting_ability = "CHA" AND max_spell_level >= 5
     *
     * # Non-spellcasters with Dexterity save proficiency (pure martials with evasion)
     * GET /api/v1/classes?filter=is_spellcaster = false AND saving_throw_proficiencies IN ["DEX"]
     *
     * # Classes with martial weapons and spellcasting (gish classes)
     * GET /api/v1/classes?filter=weapon_proficiencies IN ["Martial Weapons"] AND is_spellcaster = true
     *
     * # Low-HP full casters (d6 hit die, 9th level spells)
     * GET /api/v1/classes?filter=hit_die = 6 AND max_spell_level = 9
     *
     * # Base classes with Constitution save proficiency (concentration casters)
     * GET /api/v1/classes?filter=is_base_class = true AND saving_throw_proficiencies IN ["CON"]
     *
     * # Tasha's Cauldron subclasses that are spellcasters
     * GET /api/v1/classes?filter=source_codes IN ["TCOE"] AND is_subclass = true AND is_spellcaster = true
     * ```
     *
     * ## Use Cases
     *
     * **Character Creation**
     * - Find base classes by primary ability: `filter=is_base_class = true AND primary_ability = "CHA"`
     * - Compare hit dice for survivability: `filter=hit_die >= 10`
     *
     * **Multiclass Planning**
     * - Find compatible spellcasting abilities: `filter=spellcasting_ability = "CHA" AND max_spell_level >= 5`
     * - Identify armor proficiency gaps: `filter=armor_proficiencies IN ["Heavy Armor"] AND is_base_class = true`
     * - Save proficiency optimization: `filter=saving_throw_proficiencies IN ["WIS", "DEX"]` (best saves)
     *
     * **Campaign Restrictions**
     * - Filter by allowed sourcebooks: `filter=source_codes IN ["PHB", "XGTE"]`
     * - Exclude subclasses: `filter=is_base_class = true`
     *
     * **Build Optimization**
     * - Find tanky spellcasters: `filter=armor_proficiencies IN ["Heavy Armor"] AND is_spellcaster = true`
     * - Identify gish classes (melee + magic): `filter=weapon_proficiencies IN ["Martial Weapons"] AND is_spellcaster = true`
     * - High-HP casters: `filter=hit_die >= 8 AND max_spell_level = 9`
     *
     * **Spell List Analysis**
     * - Classes with large spell pools: `filter=spell_count > 100`
     * - Full casters only: `filter=max_spell_level = 9`
     *
     * **Thematic Search**
     * - Find classes with specific tags: `filter=tag_slugs IN ["healing", "support"]`
     * - Stealth-capable classes: `filter=skill_proficiencies IN ["Stealth"]`
     *
     * ## Filter Operators
     *
     * For complete operator documentation and syntax, see:
     * https://www.meilisearch.com/docs/reference/api/search#filter
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression. Supports all operators by data type: Integer (=,!=,>,>=,<,<=,TO), String (=,!=), Boolean (=,!=,IS NULL,EXISTS), Array (IN,NOT IN,IS EMPTY). Fields: id, slug, hit_die, spell_count, max_spell_level, primary_ability, spellcasting_ability, parent_class_name, is_base_class, is_subclass, has_spells, is_spellcaster, source_codes, tag_slugs, saving_throw_proficiencies, armor_proficiencies, weapon_proficiencies, tool_proficiencies, skill_proficiencies. See docs/MEILISEARCH-FILTER-OPERATORS.md for details.', example: 'is_base_class = true AND armor_proficiencies IN ["Heavy Armor"]')]
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
