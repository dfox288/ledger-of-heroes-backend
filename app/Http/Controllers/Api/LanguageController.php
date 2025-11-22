<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LanguageIndexRequest;
use App\Http\Requests\LanguageShowRequest;
use App\Http\Resources\BackgroundResource;
use App\Http\Resources\LanguageResource;
use App\Http\Resources\RaceResource;
use App\Models\Language;
use App\Services\Cache\LookupCacheService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LanguageController extends Controller
{
    /**
     * List all D&D languages
     *
     * Returns a paginated list of languages in D&D 5e including Common, Elvish, Dwarvish,
     * and exotic languages. Includes script information, language type, and rarity.
     */
    public function index(LanguageIndexRequest $request, LookupCacheService $cache)
    {
        $query = Language::query();

        // Add search support
        if ($request->has('q')) {
            $search = $request->validated('q');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Add pagination support
        $perPage = $request->validated('per_page', 50); // Higher default for lookups

        // Use cache for unfiltered queries
        if (! $request->has('q')) {
            $allLanguages = $cache->getLanguages();
            $currentPage = $request->input('page', 1);
            $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $allLanguages->forPage($currentPage, $perPage),
                $allLanguages->count(),
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return LanguageResource::collection($paginated);
        }

        $entities = $query->paginate($perPage);

        return LanguageResource::collection($entities);
    }

    /**
     * Get a single language
     *
     * Returns detailed information about a specific D&D language including its script,
     * type (standard/exotic), and rarity.
     */
    public function show(Language $language)
    {
        return new LanguageResource($language);
    }

    /**
     * List all races that speak this language natively or as a choice
     *
     * Returns races that either automatically know this language or can choose it as a racial
     * language option. Use this to find which races provide access to specific languages for
     * character building or to understand language distribution across the world.
     *
     * **Examples:**
     * ```bash
     * # By slug (most common)
     * GET /api/v1/languages/elvish/races
     * GET /api/v1/languages/draconic/races
     * GET /api/v1/languages/common/races
     *
     * # By numeric ID
     * GET /api/v1/languages/5/races?per_page=25
     * ```
     *
     * **Common Language → Race Queries:**
     * - **Common:** Human, Half-Elf, Half-Orc, most other races (universal language)
     * - **Elvish:** Elf, Half-Elf, Eladrin
     * - **Dwarvish:** Dwarf, Duergar
     * - **Draconic:** Dragonborn, Kobold, Half-Dragon
     * - **Undercommon:** Drow, Deep Gnome, Svirfneblin (Underdark languages)
     * - **Infernal:** Tiefling (heritage language)
     * - **Primordial:** Genasi (elemental languages)
     * - **Sylvan:** Wood Elf, Forest Gnome (fey languages)
     *
     * **Use Cases:**
     * - **Race selection:** "I want to speak Elvish - which races work?" → Query `/languages/elvish/races`
     * - **Campaign planning:** "Which races speak Infernal for my Avernus campaign?" → Check Tiefling variants
     * - **Lore building:** "Which races communicate with dragons?" → Query Draconic speakers
     * - **Party composition:** "We need someone who speaks Undercommon for Underdark exploration"
     * - **Multilingual characters:** Combine race language with background language for 3+ languages
     *
     * **Language Distribution (Approximate):**
     * - **Standard Languages** (all races): Common (30 races), Elvish (8 races), Dwarvish (4 races), Draconic (5 races)
     * - **Exotic Languages** (rare): Abyssal (1 race), Infernal (2 races), Celestial (1 race), Deep Speech (0 races)
     * - **Regional Languages:** Primordial (4 dialects), Sylvan (3 races), Undercommon (5 Underdark races)
     *
     * **Character Building Tips:**
     * - Most campaigns need Common + 1-2 specialized languages (dungeon exploration, diplomacy)
     * - Choose languages based on campaign setting (Underdark → Undercommon, Planar → Infernal/Celestial)
     * - Thieves' Cant is only available via Criminal/Urchin backgrounds (not racial)
     * - Druids automatically learn Druidic (not queryable via this endpoint - class feature)
     *
     * **Response Format:**
     * Returns paginated race data with size, speed, sources, and traits. Default 50 results per page.
     * Use `per_page` parameter to adjust pagination (max 100).
     */
    public function races(LanguageShowRequest $request, Language $language): AnonymousResourceCollection
    {
        $perPage = $request->validated('per_page', 50);

        $races = $language->races()
            ->with(['size', 'sources', 'tags'])
            ->paginate($perPage);

        return RaceResource::collection($races);
    }

    /**
     * List all backgrounds that teach or grant this language
     *
     * Returns backgrounds that provide access to this language, either as a fixed language grant
     * or as a language choice. Use this to plan character backgrounds that grant specific languages
     * or to understand which backgrounds provide linguistic diversity.
     *
     * **Examples:**
     * ```bash
     * # By slug (most common)
     * GET /api/v1/languages/thieves-cant/backgrounds
     * GET /api/v1/languages/elvish/backgrounds
     * GET /api/v1/languages/common/backgrounds
     *
     * # By numeric ID
     * GET /api/v1/languages/8/backgrounds?per_page=20
     * ```
     *
     * **Common Language → Background Queries:**
     * - **Thieves' Cant:** Criminal, Urchin (only these backgrounds teach this secret language!)
     * - **Common:** Most backgrounds grant Common or assume it (universal language)
     * - **Dwarvish:** Guild Artisan (if Dwarf-focused), Folk Hero (mountainous regions)
     * - **Elvish:** Sage (elven libraries), Outlander (elven forests)
     * - **Exotic Languages:** Acolyte (Celestial, Infernal), Sage (any language), Hermit (choice)
     *
     * **Use Cases:**
     * - **Background selection:** "I want Thieves' Cant - which backgrounds work?" → Only Criminal/Urchin
     * - **Language optimization:** "I need 3+ languages - which backgrounds help?" → Sage (2 choices), Acolyte (2 languages)
     * - **Campaign alignment:** "Urban campaign needs urban languages" → Criminal, Charlatan, Urchin
     * - **Rare language access:** "How do I get Deep Speech without being Far Realm-touched?" → Sage background
     * - **Multilingual builds:** Combine race languages + background languages + class features
     *
     * **Background Language Patterns:**
     * - **Standard Backgrounds:** Grant 1-2 common languages (Common, regional language)
     * - **Criminal/Urchin:** Only backgrounds that grant Thieves' Cant
     * - **Sage:** Most flexible - can choose 2 languages from any list
     * - **Acolyte:** Grants 2 languages (often Celestial or Infernal based on deity)
     * - **Outlander:** Grants 1 language (often regional or tribal)
     *
     * **Language Acquisition Priority:**
     * 1. **Race languages** (automatic, usually 2-3 languages including Common)
     * 2. **Background languages** (1-2 additional languages, often campaign-specific)
     * 3. **Class features** (Druid → Druidic, Ranger → one favored enemy language)
     * 4. **Feats** (Linguist feat → 3 additional languages + ciphers)
     *
     * **Character Building Tips:**
     * - Most characters start with 2-4 languages (race + background)
     * - Choose background languages based on campaign (dungeon → Undercommon, planar → Infernal/Celestial)
     * - Thieves' Cant is secret and not counted against language limits
     * - Consider taking Sage background if you need rare languages (Deep Speech, Primordial)
     * - Language proficiency can enable social encounters, lore discovery, and avoid combat
     *
     * **Response Format:**
     * Returns paginated background data with traits, proficiencies, and sources. Default 50 results per page.
     * Use `per_page` parameter to adjust pagination (max 100).
     */
    public function backgrounds(LanguageShowRequest $request, Language $language): AnonymousResourceCollection
    {
        $perPage = $request->validated('per_page', 50);

        $backgrounds = $language->backgrounds()
            ->with(['sources', 'tags'])
            ->paginate($perPage);

        return BackgroundResource::collection($backgrounds);
    }
}
