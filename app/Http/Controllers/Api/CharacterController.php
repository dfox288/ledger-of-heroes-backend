<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CharacterStatsDTO;
use App\DTOs\CharacterSummaryDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Character\CharacterIndexRequest;
use App\Http\Requests\Character\CharacterStoreRequest;
use App\Http\Requests\Character\CharacterUpdateRequest;
use App\Http\Resources\AbilityBonusCollectionResource;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\CharacterStatsResource;
use App\Http\Resources\CharacterSummaryResource;
use App\Models\Character;
use App\Services\AbilityBonusService;
use App\Services\CharacterBackgroundAssignmentService;
use App\Services\CharacterChoiceService;
use App\Services\CharacterClassAssignmentService;
use App\Services\CharacterLanguageService;
use App\Services\CharacterProficiencyService;
use App\Services\CharacterRaceAssignmentService;
use App\Services\CharacterStatCalculator;
use App\Services\FeatChoiceService;
use App\Services\FeatureUseService;
use App\Services\HitDiceService;
use App\Services\SpellManagerService;
use App\Services\SpellSlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CharacterController extends Controller
{
    public function __construct(
        private CharacterStatCalculator $statCalculator,
        private CharacterProficiencyService $proficiencyService,
        private CharacterLanguageService $languageService,
        private SpellSlotService $spellSlotService,
        private SpellManagerService $spellManagerService,
        private HitDiceService $hitDiceService,
        private FeatChoiceService $featChoiceService,
        private AbilityBonusService $abilityBonusService,
        private FeatureUseService $featureUseService,
        private CharacterChoiceService $choiceService,
        private CharacterClassAssignmentService $classAssignmentService,
        private CharacterRaceAssignmentService $raceAssignmentService,
        private CharacterBackgroundAssignmentService $backgroundAssignmentService,
    ) {}

    /**
     * List all characters
     *
     * Returns a paginated list of characters. Use for displaying character lists in a character selection screen.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters               # All characters
     * GET /api/v1/characters?per_page=10   # Custom page size
     * GET /api/v1/characters?q=gandalf     # Search by name
     * ```
     */
    public function index(CharacterIndexRequest $request)
    {
        $perPage = $request->validated('per_page', 15);

        $query = Character::with([
            'race.senses.sense',
            'background',
            'characterClasses.characterClass.levelProgression',
            'characterClasses.subclass',
            'media',
        ]);

        // Filter by name when q parameter is provided
        if ($request->has('q')) {
            $search = $request->validated('q');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $characters = $query->paginate($perPage);

        return CharacterResource::collection($characters);
    }

    /**
     * Create a new character
     *
     * Creates a new character with the provided data. Supports wizard-style creation where only name is required,
     * and other fields can be filled in later via PATCH updates.
     *
     * @x-flow character-creation
     *
     * @x-flow-step 1
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters {"name": "Gandalf"}                                          # Draft character
     * POST /api/v1/characters {"name": "Legolas", "race_slug": "phb:elf", "class_slug": "phb:ranger"}  # With race/class
     * POST /api/v1/characters {"name": "Conan", "strength": 18, "constitution": 16}        # With ability scores
     * ```
     *
     * **Validation:**
     * - `name` (required): Character name
     * - `race_slug`, `class_slug`, `background_slug`: Dangling references allowed per #288
     * - Ability scores (STR, DEX, etc.): Must be 3-20 if provided
     */
    public function store(CharacterStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Extract class_slug before creating - we'll add it via junction table
        $classSlug = $validated['class_slug'] ?? null;
        unset($validated['class_slug']);

        // Use transaction to ensure character and all grants are created atomically
        $character = DB::transaction(function () use ($validated, $classSlug) {
            $character = Character::create($validated);

            // Add class via junction table if provided
            if ($classSlug) {
                $isPrimary = $this->classAssignmentService->assignClass($character, $classSlug);
                if ($isPrimary) {
                    $this->classAssignmentService->grantPrimaryClassItems($character);
                }
            }

            // Grant fixed items from race if provided (and not dangling)
            if ($character->race_slug && $character->race) {
                $this->raceAssignmentService->grantRaceItems($character);
            }

            // Grant fixed items from background if provided (and not dangling)
            if ($character->background_slug && $character->background) {
                $this->backgroundAssignmentService->grantBackgroundItems($character);
            }

            return $character;
        });

        $character->load([
            'race.senses.sense',
            'background',
            'characterClasses.characterClass.levelProgression',
            'characterClasses.subclass',
            'media',
        ]);

        return (new CharacterResource($character))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Get a character
     *
     * Returns detailed character information including ability scores, modifiers, and validation status.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1
     * ```
     *
     * **Response includes:**
     * - Basic info: name, level, XP
     * - Ability scores (STR, DEX, CON, INT, WIS, CHA)
     * - Calculated modifiers
     * - Proficiency bonus
     * - Relationships: race, class, background
     * - Validation status: is_complete, missing fields
     */
    public function show(Character $character): CharacterResource
    {
        $character->load([
            'race.senses.sense',
            'background',
            'characterClasses.characterClass.levelProgression',
            'characterClasses.characterClass.equipment.item',
            'characterClasses.subclass',
            'featureSelections.optionalFeature',
            'featureSelections.characterClass',
            'media',
        ]);

        return new CharacterResource($character);
    }

    /**
     * Update a character
     *
     * Updates character fields. Supports partial updates (PATCH semantics).
     *
     * @x-flow character-creation
     *
     * @x-flow-step 2
     *
     * **Examples:**
     * ```
     * PATCH /api/v1/characters/1 {"name": "NewName"}
     * PATCH /api/v1/characters/1 {"race_slug": "phb:elf"}
     * PATCH /api/v1/characters/1 {"strength": 18, "dexterity": 14}
     * ```
     *
     * **Validation:**
     * - Ability scores must be 3-20
     * - Slugs (race_slug, class_slug) and IDs (background_slug) must exist
     */
    public function update(CharacterUpdateRequest $request, Character $character): CharacterResource
    {
        $validated = $request->validated();

        // Extract class_slug and level before transaction
        $classSlug = $validated['class_slug'] ?? null;
        $level = $validated['level'] ?? null;
        unset($validated['class_slug'], $validated['level']);

        // Track assignment changes for fixed grants
        $previousRaceSlug = $character->race_slug;
        $raceAssigned = $this->raceAssignmentService->isRaceChanging($character, $validated);
        $backgroundAssigned = $this->backgroundAssignmentService->isBackgroundChanging(
            $character,
            $validated['background_slug'] ?? null
        );

        // Use transaction with pessimistic locking for all operations
        DB::transaction(function () use (
            $character,
            &$validated,
            $classSlug,
            $level,
            $backgroundAssigned,
            $raceAssigned,
            $previousRaceSlug
        ) {
            // Lock character row first for HP/death save consistency
            $character->lockForUpdate()->first();

            // Auto-reset death saves when HP goes from 0 to positive
            $this->autoResetDeathSaves($character, $validated);

            // Handle class assignment and level updates
            $primaryClassAssigned = false;
            if ($classSlug) {
                $primaryClassAssigned = $this->classAssignmentService->assignClass($character, $classSlug);
            }
            if ($level !== null) {
                $this->classAssignmentService->updatePrimaryClassLevel($character, $level);
            }

            // Clear old background data BEFORE updating background_slug
            // This ensures choice-based languages/proficiencies are removed when switching backgrounds
            if ($backgroundAssigned) {
                $this->backgroundAssignmentService->clearOldBackgroundData($character);
            }

            // Apply direct character updates
            $character->update($validated);

            // Adjust HP if race changed (must happen after update so race_slug is set)
            if ($raceAssigned) {
                $this->raceAssignmentService->adjustHpForRaceChange(
                    $character,
                    $previousRaceSlug,
                    $validated['race_slug'] ?? null
                );
            }

            // Grant fixed items from primary class
            if ($primaryClassAssigned) {
                $this->classAssignmentService->grantPrimaryClassItems($character);
            }

            // Grant fixed items from race (reload to verify race exists)
            if ($raceAssigned) {
                $character->load('race');
                $this->raceAssignmentService->grantRaceItems($character);
            }

            // Grant fixed items from background (reload to verify background exists)
            if ($backgroundAssigned) {
                $character->load('background');
                $this->backgroundAssignmentService->grantBackgroundItems($character);
            }
        });

        $character->load([
            'race.senses.sense',
            'background',
            'characterClasses.characterClass.levelProgression',
            'characterClasses.subclass',
            'media',
        ]);

        return new CharacterResource($character);
    }

    /**
     * Auto-reset death saves when HP goes from 0 to positive.
     */
    private function autoResetDeathSaves(Character $character, array &$validated): void
    {
        $wasAtZeroHp = $character->current_hit_points === 0;
        $newHp = $validated['current_hit_points'] ?? null;

        if ($wasAtZeroHp && $newHp !== null && $newHp > 0) {
            $validated['death_save_successes'] = 0;
            $validated['death_save_failures'] = 0;
        }
    }

    /**
     * Delete a character
     *
     * Permanently deletes a character and all associated data (spells, proficiencies, features, equipment).
     *
     * **Examples:**
     * ```
     * DELETE /api/v1/characters/1
     * ```
     */
    public function destroy(Character $character): Response
    {
        // Clear stats cache before deleting
        Cache::forget("character:{$character->id}:stats");

        $character->delete();

        return response()->noContent();
    }

    /**
     * Get computed stats for a character
     *
     * Returns all computed statistics for the character, including ability modifiers,
     * proficiency bonus, saving throws, spell save DC, and spell slots.
     *
     * Results are cached for 15 minutes for performance. Cache is invalidated
     * when the character is updated.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/stats
     * ```
     *
     * **Response includes:**
     * - Ability scores with modifiers
     * - Proficiency bonus
     * - Saving throw modifiers
     * - Armor class and hit points
     * - Spellcasting: ability, spell save DC, attack bonus
     * - Spell slots by level
     * - Preparation limit and count
     */
    public function stats(Character $character): CharacterStatsResource
    {
        $character->load([
            'characterClasses.characterClass.parentClass',
            'characterClasses.characterClass.spellcastingAbility',
            'race.modifiers.damageType',
            'race.conditions.condition',
            'features.feature',
        ]);

        $stats = Cache::remember(
            "character:{$character->id}:stats",
            now()->addMinutes(15),
            fn () => CharacterStatsDTO::fromCharacter($character, $this->statCalculator, $this->spellManagerService)
        );

        return new CharacterStatsResource($stats);
    }

    /**
     * Get character summary overview
     *
     * Returns a comprehensive overview of character state including pending choices,
     * resource states, combat state, and creation completeness.
     *
     * Use this endpoint to display a character dashboard or creation progress tracker.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/summary
     * ```
     *
     * **Response includes:**
     * - Basic character info (id, name, level)
     * - Pending choices (proficiencies, languages, spells, optional features, ASI, size, feats)
     * - Resources (hit points, hit dice, spell slots, feature uses)
     * - Combat state (conditions, death saves, consciousness)
     * - Creation status (complete/incomplete, missing requirements)
     */
    public function summary(Character $character): CharacterSummaryResource
    {
        $summary = CharacterSummaryDTO::fromCharacter(
            $character,
            $this->proficiencyService,
            $this->languageService,
            $this->spellSlotService,
            $this->hitDiceService,
            $this->featChoiceService,
            $this->featureUseService,
            $this->choiceService
        );

        return new CharacterSummaryResource($summary);
    }

    /**
     * Get all ability score bonuses for a character
     *
     * Returns bonuses from race (fixed and choice) and feats,
     * with metadata about source and whether the bonus can be changed.
     *
     * Use this endpoint to display ability score bonuses in a character sheet
     * or to show the source of each bonus for transparency.
     *
     * @operationId getCharacterAbilityBonuses
     *
     * @tags Characters
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/ability-bonuses
     * ```
     *
     * **Response includes:**
     * - Total bonuses by ability (STR, DEX, CON, INT, WIS, CHA)
     * - Individual bonus entries with:
     *   - Ability name and value
     *   - Source (race name or feat name)
     *   - Source type (race or feat)
     *   - Whether bonus can be changed
     *   - Entity ID for tracking
     */
    public function abilityBonuses(Character $character): AbilityBonusCollectionResource
    {
        // Eager-load race and parent race to avoid N+1 queries in service
        $character->loadMissing(['race.parent', 'race.modifiers.abilityScore', 'race.parent.modifiers.abilityScore']);

        $result = $this->abilityBonusService->getBonuses($character);

        return new AbilityBonusCollectionResource($result);
    }
}
