<?php

namespace App\Http\Controllers\Api;

use App\DTOs\BackgroundSearchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackgroundIndexRequest;
use App\Http\Requests\BackgroundShowRequest;
use App\Http\Resources\BackgroundResource;
use App\Models\Background;
use App\Services\BackgroundSearchService;
use App\Services\Cache\EntityCacheService;
use Dedoc\Scramble\Attributes\QueryParameter;

class BackgroundController extends Controller
{
    /**
     * List all backgrounds
     *
     * Returns a paginated list of D&D 5e character backgrounds with comprehensive filtering
     * capabilities. Supports proficiency filtering, skill/tool grants, language choices,
     * and full-text search. Each background includes personality traits, ideals, bonds,
     * flaws (via random tables), starting equipment, and feature descriptions.
     *
     * **Basic Examples:**
     * - All backgrounds: `GET /api/v1/backgrounds`
     * - By skill proficiency: `GET /api/v1/backgrounds?grants_skill=stealth` (Urchin, Criminal)
     * - By tool proficiency: `GET /api/v1/backgrounds?grants_proficiency=thieves-tools` (Criminal, Urchin)
     * - By language: `GET /api/v1/backgrounds?speaks_language=dwarvish` (Guild Artisan)
     * - Pagination: `GET /api/v1/backgrounds?per_page=20&page=1`
     *
     * **Proficiency Filtering Examples:**
     * - Skill proficiencies: `GET /api/v1/backgrounds?grants_skill=insight` (Acolyte, Sage)
     * - Tool proficiencies: `GET /api/v1/backgrounds?grants_proficiency=gaming-set` (Folk Hero)
     * - Music proficiencies: `GET /api/v1/backgrounds?grants_proficiency=lute` (Entertainer)
     * - Artisan tools: `GET /api/v1/backgrounds?grants_proficiency=smiths-tools` (Guild Artisan)
     *
     * **Language Filtering Examples:**
     * - Specific language: `GET /api/v1/backgrounds?speaks_language=elvish` (Sage, Outlander)
     * - Language choices: `GET /api/v1/backgrounds?language_choice_count=2` (2+ language choices)
     * - Any languages: `GET /api/v1/backgrounds?grants_languages=true` (backgrounds granting languages)
     * - No languages: `GET /api/v1/backgrounds?grants_languages=false` (no language grants)
     *
     * **Search Examples:**
     * - Search by name: `GET /api/v1/backgrounds?q=noble` (Noble, Knight)
     * - Search by description: `GET /api/v1/backgrounds?q=temple` (Acolyte)
     * - Search by feature: `GET /api/v1/backgrounds?q=shelter` (Folk Hero feature)
     *
     * **Combined Filtering Examples:**
     * - Skill + tool: `GET /api/v1/backgrounds?grants_skill=deception&grants_proficiency=disguise-kit` (Charlatan)
     * - Language + skill: `GET /api/v1/backgrounds?speaks_language=dwarvish&grants_skill=history` (Guild Artisan)
     * - Search + filter: `GET /api/v1/backgrounds?q=criminal&grants_skill=stealth` (Criminal, Urchin)
     *
     * **Use Cases:**
     * - Character Creation: Find backgrounds matching desired skill proficiencies
     * - Proficiency Planning: Optimize proficiency spread across race/class/background
     * - Roleplaying: Browse personality traits, ideals, bonds, and flaws for inspiration
     * - Language Optimization: Find backgrounds granting extra language choices
     * - Equipment Planning: Compare starting equipment for early-game optimization
     * - Build Synergy: Match background skills with class features (Rogue + Criminal)
     *
     * **Query Parameters:**
     * - `q` (string): Full-text search term (searches name, description, feature text)
     * - `filter` (string): Meilisearch filter expression (limited fields for backgrounds)
     * - `grants_proficiency` (string): Filter by granted proficiency (tool, instrument, gaming set)
     * - `grants_skill` (string): Filter by granted skill proficiency (stealth, insight, etc.)
     * - `speaks_language` (string): Filter by granted language (elvish, dwarvish, etc.)
     * - `language_choice_count` (int): Minimum number of language choices granted
     * - `grants_languages` (bool): Has any language grants (true/false)
     * - `sort_by` (string): Column to sort by (name, created_at, updated_at)
     * - `sort_direction` (string): Sort direction (asc, desc)
     * - `per_page` (int): Results per page (default 15, max 100)
     * - `page` (int): Page number (default 1)
     *
     * **Data Source:**
     * - D&D 5e backgrounds from PHB, SCAG, XGE, TCoE, and other sourcebooks
     * - Includes personality traits, ideals, bonds, flaws via random tables
     * - Proficiency and language data via polymorphic relationships
     * - Starting equipment variants and feature descriptions
     *
     * **Unique Features:**
     * - Random personality tables for roleplaying (d8, d6, d10, d12 tables)
     * - Starting equipment variants (choose between options)
     * - Feature descriptions (special abilities unique to each background)
     * - Proficiency grants (skills, tools, instruments, gaming sets)
     * - Language choices (fixed languages + choice slots)
     *
     * See `docs/API-EXAMPLES.md` for comprehensive usage examples.
     *
     * @param  BackgroundIndexRequest  $request  Validated request with filtering parameters
     * @param  BackgroundSearchService  $service  Service layer for background queries
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression for advanced filtering. Note: Backgrounds have limited filterable fields. Use search (q parameter) for most queries.', example: 'name = Acolyte')]
    public function index(BackgroundIndexRequest $request, BackgroundSearchService $service)
    {
        $dto = BackgroundSearchDTO::fromRequest($request);

        // Use Scout for full-text search, otherwise use database query
        if ($dto->searchQuery !== null) {
            $backgrounds = $service->buildScoutQuery($dto->searchQuery)->paginate($dto->perPage);
        } else {
            $backgrounds = $service->buildDatabaseQuery($dto)->paginate($dto->perPage);
        }

        return BackgroundResource::collection($backgrounds);
    }

    /**
     * Get a single background
     *
     * Returns detailed information about a specific background including proficiencies,
     * traits with random tables (personality, ideals, bonds, flaws), languages, and sources.
     * Supports selective relationship loading via the 'include' parameter.
     */
    public function show(BackgroundShowRequest $request, Background $background, EntityCacheService $cache)
    {
        $validated = $request->validated();

        // Default relationships
        $defaultRelationships = [
            'sources.source',
            'traits.randomTables.entries',
            'proficiencies.skill.abilityScore',
            'proficiencies.proficiencyType',
            'languages.language',
            'tags',
        ];

        // Try cache first
        $cachedBackground = $cache->getBackground($background->id);

        if ($cachedBackground) {
            // If include parameter provided, use it; otherwise load defaults
            $includes = $validated['include'] ?? $defaultRelationships;
            $cachedBackground->load($includes);

            return new BackgroundResource($cachedBackground);
        }

        // Fallback to route model binding result (should rarely happen)
        $includes = $validated['include'] ?? $defaultRelationships;
        $background->load($includes);

        return new BackgroundResource($background);
    }
}
