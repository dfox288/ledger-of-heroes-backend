<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProficiencyTypeIndexRequest;
use App\Http\Requests\ProficiencyTypeShowRequest;
use App\Http\Resources\BackgroundResource;
use App\Http\Resources\ClassResource;
use App\Http\Resources\ProficiencyTypeResource;
use App\Http\Resources\RaceResource;
use App\Models\ProficiencyType;
use App\Services\Cache\LookupCacheService;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;

class ProficiencyTypeController extends Controller
{
    /**
     * List all proficiency types
     *
     * Returns D&D 5e proficiency types - the things characters can be trained in.
     * Includes weapons, armor, tools, languages, skills, and saving throws.
     *
     * **Examples:**
     * ```
     * GET /api/v1/lookups/proficiency-types                           # All proficiencies
     * GET /api/v1/lookups/proficiency-types?category=weapon           # All weapons
     * GET /api/v1/lookups/proficiency-types?category=weapon&subcategory=martial  # Martial weapons only
     * GET /api/v1/lookups/proficiency-types?category=armor            # All armor types
     * GET /api/v1/lookups/proficiency-types?category=tool             # All tools
     * GET /api/v1/lookups/proficiency-types?q=longsword               # Search by name
     * ```
     *
     * **Categories:**
     * - **weapon:** All weapon proficiencies (subcategories: simple, martial)
     * - **armor:** Light, Medium, Heavy, Shields
     * - **tool:** Artisan's tools, gaming sets, musical instruments, thieves' tools
     * - **language:** Common, Elvish, Dwarvish, exotic languages
     * - **skill:** The 18 D&D skills (see /lookups/skills for details)
     * - **saving-throw:** STR, DEX, CON, INT, WIS, CHA saves
     *
     * **Query Parameters:**
     * - `q` (string): Search proficiencies by name (partial match)
     * - `category` (string): Filter by category (weapon, armor, tool, language, skill, saving-throw)
     * - `subcategory` (string): Filter by subcategory (e.g., simple, martial for weapons)
     * - `per_page` (int): Results per page, 1-100 (default: 50)
     *
     * **Use Cases:**
     * - **Class Selection:** "Which classes get heavy armor?" → Filter by category=armor
     * - **Multiclass Planning:** "What martial weapons can a Rogue use after Fighter dip?"
     * - **Character Building:** Find which proficiencies your race/class/background grants
     * - **Equipment Shopping:** Browse weapon proficiencies to know what you can use effectively
     *
     * @response array{data: array<int, array{id: int, slug: string, name: string, category: string, subcategory: string|null}>}
     */
    #[QueryParameter('q', description: 'Search proficiencies by name', example: 'longsword')]
    #[QueryParameter('category', description: 'Filter by category (weapon, armor, tool, language, skill, saving-throw)', example: 'weapon')]
    #[QueryParameter('subcategory', description: 'Filter by subcategory within category (e.g., simple, martial)', example: 'martial')]
    public function index(ProficiencyTypeIndexRequest $request, LookupCacheService $cache)
    {
        $query = ProficiencyType::query();

        // Filter by category
        if ($request->has('category')) {
            $query->byCategory($request->validated('category'));
        }

        // Filter by subcategory
        if ($request->has('subcategory')) {
            $query->bySubcategory($request->validated('subcategory'));
        }

        // Search by name
        if ($request->has('q')) {
            $search = $request->validated('q');
            $query->where('name', 'like', "%{$search}%");
        }

        // Pagination
        $perPage = $request->validated('per_page', 50);

        // Use cache for unfiltered queries
        if (! $request->has('q') && ! $request->has('category') && ! $request->has('subcategory')) {
            $allProficiencyTypes = $cache->getProficiencyTypes();
            $currentPage = $request->input('page', 1);
            $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $allProficiencyTypes->forPage($currentPage, $perPage),
                $allProficiencyTypes->count(),
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return ProficiencyTypeResource::collection($paginated);
        }

        $proficiencyTypes = $query->paginate($perPage);

        return ProficiencyTypeResource::collection($proficiencyTypes);
    }

    /**
     * Get a single proficiency type
     *
     * Returns detailed information about a specific proficiency type.
     * Proficiencies can be retrieved by ID, slug, or name.
     *
     * **Examples:**
     * ```
     * GET /api/v1/lookups/proficiency-types/42           # By ID
     * GET /api/v1/lookups/proficiency-types/longsword    # By slug
     * GET /api/v1/lookups/proficiency-types/Longsword    # By name
     * ```
     *
     * **Response includes:**
     * - `id`, `name`, `slug`: Proficiency identification
     * - `category`: Type of proficiency (weapon, armor, tool, language, skill)
     * - `subcategory`: Sub-type (e.g., simple/martial for weapons)
     * - `item`: Associated item details (for weapon/armor proficiencies)
     *
     * **Related endpoints:**
     * - `/api/v1/lookups/proficiency-types/{id}/classes` - Classes with this proficiency
     * - `/api/v1/lookups/proficiency-types/{id}/races` - Races with this proficiency
     * - `/api/v1/lookups/proficiency-types/{id}/backgrounds` - Backgrounds granting this proficiency
     */
    public function show(ProficiencyType $proficiencyType)
    {
        $proficiencyType->load('item');

        return new ProficiencyTypeResource($proficiencyType);
    }

    /**
     * List all classes proficient with this proficiency type (weapon/armor/tool/skill/language)
     *
     * **Examples:**
     * ```bash
     * # By numeric ID
     * GET /api/v1/proficiency-types/flail/classes
     *
     * # By name (most common for proficiencies)
     * GET /api/v1/proficiency-types/Longsword/classes
     * GET /api/v1/proficiency-types/Stealth/classes
     * GET /api/v1/proficiency-types/Heavy%20Armor/classes
     *
     * # With pagination
     * GET /api/v1/proficiency-types/longsword/classes?per_page=25
     * ```
     *
     * **Common Proficiency Queries:**
     * - **Martial Weapons:** Longsword, Greatsword, Longbow → Fighter, Paladin, Ranger, Barbarian
     * - **Heavy Armor:** Plate, Chain Mail → Fighter, Paladin, Cleric (War/Forge domains)
     * - **Skills:** Stealth → Rogue, Monk, Ranger | Perception → Ranger, Druid, Barbarian
     * - **Tools:** Thieves' Tools → Rogue | Smith's Tools → Fighter (specific backgrounds)
     * - **Languages:** Draconic → Sorcerer (Draconic Bloodline), Wizard
     *
     * **Use Cases:**
     * - **Multiclass Planning:** "Which classes are proficient with longswords?" (Fighter, Paladin, etc.)
     * - **Build Optimization:** "I want heavy armor - which classes work?" (Fighter, Paladin)
     * - **Skill Coverage:** "Which classes are proficient in Perception?" (Rangers, Druids, Barbarians)
     * - **Tool Requirements:** "Which classes can use Thieves' Tools?" (Rogue + specific subclasses)
     * - **Character Concept:** "I want a Draconic speaker - which classes learn Draconic?" (Sorcerer, Wizard)
     *
     * **Proficiency Distribution (Typical):**
     * - **Martial Weapons:** ~8-12 classes (primarily martial classes + some subclasses)
     * - **Heavy Armor:** ~6-8 classes (Fighter, Paladin, Cleric domains)
     * - **Skills (Stealth):** ~4-6 classes (Rogue, Monk, Ranger, Bard)
     * - **Skills (Perception):** ~8-10 classes (most common skill proficiency)
     * - **Tools:** ~1-3 classes (highly specialized, often background-dependent)
     * - **Languages:** Varies widely (Common: all classes, Exotic: 1-2 classes)
     *
     * **Character Building Advice:**
     * - **Weapon Proficiency Gaps:** If your class lacks martial weapon proficiency, consider:
     *   - Feat: Weapon Master (gain 4 weapon proficiencies + +1 STR/DEX)
     *   - Multiclass: 1 level Fighter (all weapons + armor + Fighting Style)
     *   - Race: Elf/Dwarf variants grant specific weapon proficiencies
     * - **Armor Proficiency Progression:** Light → Medium → Heavy (each tier requires feat/multiclass)
     * - **Skill Proficiency Limits:** Classes get 2-4 skills at creation, backgrounds add 2 more
     * - **Tool Proficiency Value:** Often background-dependent, but Thieves' Tools universally useful
     *
     * **Query Tips:**
     * - Use name routing for readability: `/proficiency-types/Longsword/classes` vs `/proficiency-types/42/classes`
     * - Results are alphabetically sorted for consistent browsing
     * - Includes base classes AND subclasses (e.g., Eldritch Knight Fighter counts separately)
     * - Check `parent_class_id` in response to distinguish base vs subclass
     *
     * @response array{data: array<int, array{id: int, slug: string, full_slug: string, name: string, hit_die: int, description: string|null, archetype: string|null, parent_class_id: int|null, is_base_class: bool}>}
     */
    public function classes(ProficiencyTypeShowRequest $request, ProficiencyType $proficiencyType): JsonResponse
    {
        $perPage = $request->validated('per_page', 50);

        $classes = $proficiencyType->classes()
            ->with(['sources', 'tags'])
            ->paginate($perPage);

        return ClassResource::collection($classes)->toResponse($request);
    }

    /**
     * List all races that have this proficiency type (weapon/armor/tool/skill/language/trait)
     *
     * **Examples:**
     * ```bash
     * # By numeric ID
     * GET /api/v1/proficiency-types/quarterstaff/races
     *
     * # By name (recommended for readability)
     * GET /api/v1/proficiency-types/Elvish/races
     * GET /api/v1/proficiency-types/Longsword/races
     * GET /api/v1/proficiency-types/Darkvision/races
     *
     * # With pagination
     * GET /api/v1/proficiency-types/dwarven-resilience/races?per_page=10
     * ```
     *
     * **Common Racial Proficiency Queries:**
     * - **Languages:** Elvish → Elf, Half-Elf | Dwarvish → Dwarf, Duergar | Draconic → Dragonborn
     * - **Weapons:** Longsword, Shortsword → Elf variants | Battleaxe, Warhammer → Dwarf variants
     * - **Tools:** Smith's Tools → Mountain Dwarf | Tinker's Tools → Rock Gnome
     * - **Traits:** Darkvision → Dwarf, Elf, Tiefling, Half-Orc (~60% of races)
     * - **Armor:** Light/Medium Armor → Specific racial variants (rare, usually Mountain Dwarf)
     *
     * **Use Cases:**
     * - **Language Planning:** "Which races speak Elvish?" (Elf, Half-Elf, High Elf)
     * - **Weapon Synergies:** "I want longsword proficiency from race - which races work?" (Elves)
     * - **Tool Proficiencies:** "Which races get Smith's Tools?" (Mountain Dwarf)
     * - **Trait Coverage:** "Which races have Darkvision?" (Dwarf, Elf, Tiefling, Gnome, etc.)
     * - **Build Optimization:** "I want a Fighter with elven weapon training - which elf subrace?"
     *
     * **Proficiency Distribution (Typical):**
     * - **Languages:** Common (all races), Elvish (~4 races), Dwarvish (~3 races), Draconic (~2 races)
     * - **Weapons:** Elf variants (~3-4 races for longsword/bow), Dwarf variants (~2-3 for axes/hammers)
     * - **Tools:** ~1-2 races per tool type (highly specialized)
     * - **Darkvision:** ~12-15 races out of ~30 total (very common trait)
     * - **Skills:** Rare as racial proficiencies (usually background-dependent)
     *
     * **Character Building Advice:**
     * - **Stacking Proficiencies:** Elf Fighter = Longsword prof from race + all martial weapons from class
     * - **Unique Combinations:** Mountain Dwarf Wizard = Medium armor prof (normally Wizard has none!)
     * - **Language Optimization:** Pick race based on campaign setting (Elvish for Faerun, Draconic for dragonlands)
     * - **Darkvision Value:** Critical in dungeon-heavy campaigns, less useful in urban/outdoor settings
     *
     * **Query Tips:**
     * - Use name routing: `/proficiency-types/Elvish/races` vs `/proficiency-types/7/races`
     * - Results include base races AND subraces (e.g., High Elf, Wood Elf count separately)
     * - Check `parent_race_id` in response to distinguish base race vs subrace
     * - Results alphabetically sorted for consistent browsing
     *
     * @response array{data: array<int, array{id: int, slug: string, full_slug: string, name: string, speed: int|null, is_subrace: bool}>}
     */
    public function races(ProficiencyTypeShowRequest $request, ProficiencyType $proficiencyType): JsonResponse
    {
        $perPage = $request->validated('per_page', 50);

        $races = $proficiencyType->races()
            ->with(['sources', 'tags', 'size'])
            ->paginate($perPage);

        return RaceResource::collection($races)->toResponse($request);
    }

    /**
     * List all backgrounds that grant this proficiency type (skill/tool/language/equipment)
     *
     * **Examples:**
     * ```bash
     * # By numeric ID
     * GET /api/v1/proficiency-types/dagger/backgrounds
     *
     * # By name (recommended for clarity)
     * GET /api/v1/proficiency-types/Stealth/backgrounds
     * GET /api/v1/proficiency-types/Thieves%27%20Tools/backgrounds
     * GET /api/v1/proficiency-types/Deception/backgrounds
     *
     * # With pagination
     * GET /api/v1/proficiency-types/insight/backgrounds?per_page=15
     * ```
     *
     * **Common Background Proficiency Queries:**
     * - **Skills (Social):** Deception → Charlatan, Criminal | Persuasion → Noble, Guild Artisan
     * - **Skills (Stealth):** Stealth → Criminal, Urchin, Spy | Sleight of Hand → Criminal, Entertainer
     * - **Skills (Knowledge):** Insight → Acolyte, Sage, Hermit | Investigation → Sage, City Watch
     * - **Tools:** Thieves' Tools → Criminal, Urchin | Gaming Set → Gambler, Sailor
     * - **Languages:** Most backgrounds grant 1-2 language choices (player picks from standard list)
     *
     * **Use Cases:**
     * - **Skill Coverage:** "I need Stealth but my class doesn't have it - which backgrounds work?" (Criminal, Urchin)
     * - **Tool Requirements:** "Which backgrounds grant Thieves' Tools?" (Criminal, Urchin)
     * - **Role-Playing Synergy:** "I'm playing a con artist - which backgrounds fit?" (Charlatan, Criminal)
     * - **Multiclass Optimization:** "I need Investigation for my Detective build" (Sage, City Watch)
     * - **Campaign Fit:** "We need a diplomat - which backgrounds grant Persuasion?" (Noble, Guild Artisan)
     *
     * **Proficiency Distribution (Typical):**
     * - **Skills (Stealth):** ~3-4 backgrounds (Criminal, Urchin, Spy, Urban Bounty Hunter)
     * - **Skills (Persuasion):** ~4-5 backgrounds (Noble, Guild Artisan, Courtier, Entertainer)
     * - **Skills (Insight):** ~5-6 backgrounds (Acolyte, Sage, Hermit, Folk Hero)
     * - **Tools (Thieves' Tools):** ~2 backgrounds (Criminal, Urchin)
     * - **Tools (Artisan's Tools):** ~8-10 backgrounds (Guild Artisan variants for each tool type)
     * - **Languages:** Nearly all backgrounds grant 1-2 language choices
     *
     * **Character Building Advice:**
     * - **Skill Proficiency Overlap:** If background + class both grant same skill, you can swap ONE background skill
     * - **Tool Proficiency Value:** Thieves' Tools (lockpicking) > other tools for dungeon delving
     * - **Language Strategy:** Pick languages based on campaign (Undercommon for Underdark, Celestial for holy campaigns)
     * - **Background Variants:** Many backgrounds have official variants (e.g., Criminal → Spy, same proficiencies)
     *
     * **Typical Background Combinations:**
     * - **Rogue + Criminal:** Double Stealth prof → swap to Perception or Insight
     * - **Fighter + Soldier:** Double Athletics prof → swap to Intimidation or Survival
     * - **Wizard + Sage:** Triple Intelligence skill coverage (Investigation, Arcana, History)
     * - **Cleric + Acolyte:** Perfect thematic fit (Insight, Religion, holy proficiencies)
     *
     * **Query Tips:**
     * - Use name routing for readability: `/proficiency-types/Stealth/backgrounds` vs `/proficiency-types/12/backgrounds`
     * - Results alphabetically sorted for consistent navigation
     * - All backgrounds are base-level (no "sub-backgrounds" like races/classes have)
     * - Check background description for thematic fit beyond mechanical proficiencies
     *
     * @response array{data: array<int, array{id: int, slug: string, full_slug: string, name: string, feature_name: string|null, feature_description: string|null}>}
     */
    public function backgrounds(ProficiencyTypeShowRequest $request, ProficiencyType $proficiencyType): JsonResponse
    {
        $perPage = $request->validated('per_page', 50);

        $backgrounds = $proficiencyType->backgrounds()
            ->with(['sources', 'tags'])
            ->paginate($perPage);

        return BackgroundResource::collection($backgrounds)->toResponse($request);
    }
}
