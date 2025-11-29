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
use App\Services\ClassProgressionTableGenerator;
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
     * **archetype** (`string`)
     * - Value examples: "Martial Archetype", "Divine Domain", "Arcane Tradition", null (for subclasses)
     * - Operators: `=`, `!=`, `IN`, `NOT IN`, `IS NULL`, `IS NOT NULL`
     * - Examples:
     *   - `filter=archetype = "Martial Archetype"` (Fighter base class)
     *   - `filter=archetype IS NOT NULL` (all base classes with archetype names)
     *   - `filter=archetype IS NULL` (subclasses - they inherit from parent)
     * - Use case: Display "Choose your Martial Archetype at level 3" in UI
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
     * **has_optional_features** (`boolean`)
     * - Value range: `true` (class has invocations, maneuvers, etc.), `false` (no optional features)
     * - Operators: `=`, `!=`
     * - Examples:
     *   - `filter=has_optional_features = true` (Warlock, Fighter, Sorcerer, Monk)
     *   - `filter=has_optional_features = false` (classes without customization options)
     * - Use case: Finding classes with additional character customization choices
     *
     * ### Integer Fields (Optional Features)
     *
     * **optional_feature_count** (`integer`)
     * - Value range: 0 to 54 (number of optional features available)
     * - Operators: `=`, `!=`, `<`, `<=`, `>`, `>=`, `IN`, `NOT IN`
     * - Examples:
     *   - `filter=optional_feature_count > 0` (classes with optional features)
     *   - `filter=optional_feature_count >= 10` (classes with many customization options)
     * - Use case: Finding classes with extensive customization options
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
     * **optional_feature_types** (`array of strings`)
     * - Value examples: ["eldritch_invocation"], ["maneuver"], ["metamagic"], ["elemental_discipline"]
     * - Operators: `IN`, `NOT IN`
     * - Examples:
     *   - `filter=optional_feature_types IN ["eldritch_invocation"]` (Warlock)
     *   - `filter=optional_feature_types IN ["maneuver"]` (Fighter Battle Master)
     *   - `filter=optional_feature_types IN ["metamagic"]` (Sorcerer)
     * - Use case: Finding classes with specific customization mechanics
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
     *
     * # Classes with Eldritch Invocations (Warlock customization)
     * GET /api/v1/classes?filter=optional_feature_types IN ["eldritch_invocation"]
     *
     * # Classes with many optional feature choices
     * GET /api/v1/classes?filter=optional_feature_count >= 10
     *
     * # Martial classes with combat maneuvers
     * GET /api/v1/classes?filter=optional_feature_types IN ["maneuver"] AND is_spellcaster = false
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
     * **Optional Features / Customization**
     * - Find classes with invocations: `filter=optional_feature_types IN ["eldritch_invocation"]`
     * - Martial classes with maneuvers: `filter=optional_feature_types IN ["maneuver"]`
     * - Classes with high customization: `filter=optional_feature_count >= 10`
     * - Sorcerer metamagic: `filter=optional_feature_types IN ["metamagic"]`
     *
     * ## Filter Operators
     *
     * For complete operator documentation and syntax, see:
     * https://www.meilisearch.com/docs/reference/api/search#filter
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression. Supports all operators by data type: Integer (=,!=,>,>=,<,<=,TO), String (=,!=), Boolean (=,!=,IS NULL,EXISTS), Array (IN,NOT IN,IS EMPTY). Fields: id, slug, hit_die, spell_count, max_spell_level, optional_feature_count, primary_ability, spellcasting_ability, parent_class_name, is_base_class, is_subclass, has_spells, is_spellcaster, has_optional_features, source_codes, tag_slugs, saving_throw_proficiencies, armor_proficiencies, weapon_proficiencies, tool_proficiencies, skill_proficiencies, optional_feature_types. See docs/MEILISEARCH-FILTER-OPERATORS.md for details.', example: 'is_base_class = true AND armor_proficiencies IN ["Heavy Armor"]')]
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
     * ## Response Structure
     *
     * The response separates data into three categories for API clarity:
     * - **Base fields** - Core entity data (with inheritance resolved for subclasses)
     * - **inherited_data** - Pre-resolved parent class data (subclasses only)
     * - **computed** - Aggregated/calculated data for display optimization
     *
     * ## Field Inheritance (Automatic)
     *
     * D&D 5e subclasses inherit certain properties from their parent class. The API
     * automatically resolves this inheritance in base fields:
     *
     * - **hit_die**: Subclasses show inherited value (Death Domain → 8 from Cleric)
     * - **spellcasting_ability**: Subclasses show inherited value (Death Domain → Wisdom from Cleric)
     *
     * You never see raw database values (hit_die: 0 or spellcasting_ability: null) for subclasses.
     * The effective values are always returned.
     *
     * ## Computed Object (Display-Ready Data)
     *
     * The `computed` object contains pre-computed fields to reduce frontend logic.
     * Only included on show endpoint responses.
     *
     * **computed.hit_points** - Pre-calculated D&D 5e hit point formulas:
     * ```json
     * {
     *   "hit_die": "d10",
     *   "hit_die_numeric": 10,
     *   "first_level": {"value": 10, "description": "10 + your Constitution modifier"},
     *   "higher_levels": {"roll": "1d10", "average": 6, "description": "1d10 (or 6) + your Constitution modifier per fighter level after 1st"}
     * }
     * ```
     *
     * **computed.spell_slot_summary** - Spellcasting overview for UI column visibility:
     * ```json
     * {
     *   "has_spell_slots": true,
     *   "max_spell_level": 9,
     *   "available_levels": [1, 2, 3, 4, 5, 6, 7, 8, 9],
     *   "has_cantrips": true,
     *   "caster_type": "full"
     * }
     * ```
     * - `caster_type`: "full" (9th level), "half" (5th level), "third" (4th level), or null (non-caster)
     *
     * **computed.section_counts** - Relationship counts for lazy-loading accordion labels:
     * ```json
     * {
     *   "features": 34,
     *   "proficiencies": 12,
     *   "traits": 8,
     *   "subclasses": 7,
     *   "spells": 89,
     *   "counters": 3,
     *   "optional_features": 0
     * }
     * ```
     *
     * **computed.progression_table** - Complete 20-level progression table:
     * ```json
     * {
     *   "columns": [
     *     {"key": "level", "label": "Level", "type": "integer"},
     *     {"key": "proficiency_bonus", "label": "Proficiency Bonus", "type": "bonus"},
     *     {"key": "features", "label": "Features", "type": "string"},
     *     {"key": "sneak_attack", "label": "Sneak Attack", "type": "dice"}
     *   ],
     *   "rows": [
     *     {"level": 1, "proficiency_bonus": "+2", "features": "Expertise, Sneak Attack", "sneak_attack": "1d6"},
     *     {"level": 2, "proficiency_bonus": "+2", "features": "Cunning Action", "sneak_attack": "1d6"}
     *   ]
     * }
     * ```
     *
     * **Column Sources:**
     * - **Feature data tables**: Parsed from feature descriptions (Monk's Martial Arts: 1d4→1d10)
     * - **Roll elements**: From XML `<roll>` data (Rogue's Sneak Attack: 1d6→10d6)
     * - **Synthetic data**: Hardcoded for prose-only progressions (Barbarian's Rage Damage: +2→+4)
     * - **Counters**: Class counter values (Ki Points, Rage uses)
     * - **Spell slots**: Cantrips known, 1st-9th level slots for casters
     *
     * Values are interpolated (sparse data filled in for all 20 levels).
     * Also available via dedicated endpoint: `GET /classes/{slug}/progression`
     *
     * ## Inherited Data (Subclasses Only)
     *
     * **inherited_data** - Pre-resolved parent class data for subclasses:
     * ```json
     * {
     *   "hit_die": 10,
     *   "hit_points": {...},
     *   "counters": [...],
     *   "traits": [...],
     *   "level_progression": [...],
     *   "equipment": [...],
     *   "proficiencies": [...],
     *   "spell_slot_summary": {...}
     * }
     * ```
     * - Eliminates frontend inheritance resolution logic
     * - Only present for subclasses (is_base_class = false)
     * - Contains essential data from parent class that subclasses need
     *
     * ## Feature Inheritance for Subclasses
     *
     * - By default, subclasses return ALL features (inherited base class features + subclass-specific features)
     * - Use `?include_base_features=false` to return only subclass-specific features
     * - Base classes are unaffected by this parameter
     *
     * ## Feature Choice Options (Nested Features)
     *
     * Features that offer choices (like Fighting Style) have their options nested:
     *
     * ```json
     * {
     *   "feature_name": "Fighting Style",
     *   "is_choice_option": false,
     *   "parent_feature_id": null,
     *   "choice_options": [
     *     {"feature_name": "Fighting Style: Archery", "is_choice_option": true, "parent_feature_id": 397},
     *     {"feature_name": "Fighting Style: Defense", "is_choice_option": true, "parent_feature_id": 397}
     *   ]
     * }
     * ```
     *
     * - **is_choice_option**: `true` if this feature is a choice under a parent (player picks one)
     * - **parent_feature_id**: ID of the parent feature (null for top-level features)
     * - **choice_options**: Nested array of child features (only present when loaded)
     *
     * This allows frontends to:
     * - Display choice options in a collapsible/grouped UI
     * - Filter out choice options from main feature lists (check `is_choice_option`)
     * - Show accurate feature counts (exclude choice options)
     *
     * ## Examples
     *
     * ```bash
     * # Get Fighter with computed object
     * GET /api/v1/classes/fighter
     *
     * # Get Arcane Trickster (subclass) with inherited_data from Rogue parent
     * GET /api/v1/classes/rogue-arcane-trickster
     *
     * # Get only subclass-specific features (not inherited)
     * GET /api/v1/classes/rogue-arcane-trickster?include_base_features=false
     * ```
     *
     * ## Note on Index vs Show
     *
     * The `computed` object is **only included on show endpoint** for performance.
     * Index endpoint returns base fields and relationships without computed data.
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

            // Load counts for section_counts field
            $cachedClass->loadCount([
                'features' => fn ($query) => $query->topLevel(),
                'proficiencies',
                'traits',
                'subclasses',
                'spells',
                'counters',
                'optionalFeatures',
            ]);

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

        // Load counts for section_counts field
        $class->loadCount([
            'features' => fn ($query) => $query->topLevel(),
            'proficiencies',
            'traits',
            'subclasses',
            'spells',
            'counters',
            'optionalFeatures',
        ]);

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

    /**
     * Get the progression table for a class
     *
     * Returns a pre-computed progression table showing level-by-level advancement
     * including proficiency bonus, features gained, class-specific counters (like
     * Sneak Attack dice, Ki Points, Rage uses), and spell slots if applicable.
     *
     * This endpoint is useful for lazy-loading the progression table separately
     * from the main class detail response.
     *
     * ## Response Structure
     *
     * **columns** - Dynamic column definitions based on class features:
     * - Always includes: level, proficiency_bonus, features
     * - Feature data tables: martial_arts (Monk), etc. - parsed from feature descriptions
     * - Roll element tables: sneak_attack (Rogue) - from XML `<roll>` data
     * - Synthetic progressions: rage_damage (Barbarian) - hardcoded from PHB prose
     * - Counter columns: ki (Monk), rage (Barbarian), etc. - from class counters
     * - Spell slot columns: cantrips_known, spell_slots_1st through spell_slots_9th (for casters)
     *
     * **rows** - 20 rows, one per level, with all column values pre-computed:
     * - Values interpolated (sparse data filled in for all levels)
     * - Dice values: "1d4", "1d6", "2d6", etc.
     * - Bonus values: "+2", "+3", etc.
     * - Proficiency bonus formatted as "+X"
     * - Features joined with commas
     *
     * ## Example Response
     *
     * ```json
     * {
     *   "data": {
     *     "columns": [
     *       {"key": "level", "label": "Level", "type": "integer"},
     *       {"key": "proficiency_bonus", "label": "Proficiency Bonus", "type": "bonus"},
     *       {"key": "features", "label": "Features", "type": "string"},
     *       {"key": "ki_points", "label": "Ki Points", "type": "integer"}
     *     ],
     *     "rows": [
     *       {"level": 1, "proficiency_bonus": "+2", "features": "Unarmored Defense, Martial Arts", "ki_points": "—"},
     *       {"level": 2, "proficiency_bonus": "+2", "features": "Ki, Unarmored Movement", "ki_points": "2"}
     *     ]
     *   }
     * }
     * ```
     *
     * ## For Subclasses
     *
     * When called on a subclass, returns the parent class's progression table
     * since subclasses inherit the base class progression mechanics.
     */
    public function progression(CharacterClass $class, ClassProgressionTableGenerator $generator)
    {
        // Load required relationships for progression table
        $progressionClass = $class->is_base_class ? $class : $class->parentClass;

        if (! $progressionClass) {
            return response()->json(['data' => ['columns' => [], 'rows' => []]], 200);
        }

        $progressionClass->load(['levelProgression', 'counters', 'features']);

        $table = $generator->generate($class);

        return response()->json(['data' => $table]);
    }
}
