<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CharacterStatsDTO;
use App\DTOs\CharacterSummaryDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Character\CharacterIndexRequest;
use App\Http\Requests\Character\CharacterShowRequest;
use App\Http\Requests\Character\CharacterStoreRequest;
use App\Http\Requests\Character\CharacterUpdateRequest;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\CharacterStatsResource;
use App\Http\Resources\CharacterSummaryResource;
use App\Models\Character;
use App\Models\CharacterClassPivot;
use App\Services\CharacterLanguageService;
use App\Services\CharacterProficiencyService;
use App\Services\CharacterStatCalculator;
use App\Services\HitDiceService;
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
        private HitDiceService $hitDiceService
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
     * ```
     */
    public function index(CharacterIndexRequest $request)
    {
        $perPage = $request->validated('per_page', 15);

        $characters = Character::with([
            'race',
            'background',
            'characterClasses.characterClass.levelProgression',
            'characterClasses.subclass',
            'media',
        ])->paginate($perPage);

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
     * POST /api/v1/characters {"name": "Gandalf"}                                    # Draft character
     * POST /api/v1/characters {"name": "Legolas", "race_id": 1, "class_id": 2}      # With race/class
     * POST /api/v1/characters {"name": "Conan", "strength": 18, "constitution": 16} # With ability scores
     * ```
     *
     * **Validation:**
     * - `name` (required): Character name
     * - `race_id`, `class_id`, `background_id`: Must exist if provided
     * - Ability scores (STR, DEX, etc.): Must be 3-20 if provided
     */
    public function store(CharacterStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Extract class_id before creating - we'll add it via junction table
        $classId = $validated['class_id'] ?? null;
        unset($validated['class_id']);

        $character = Character::create($validated);

        // Add class via junction table if provided
        if ($classId) {
            CharacterClassPivot::create([
                'character_id' => $character->id,
                'class_id' => $classId,
                'level' => 1,
                'is_primary' => true,
                'order' => 1,
                'hit_dice_spent' => 0,
            ]);
        }

        $character->load([
            'race',
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
    public function show(CharacterShowRequest $request, Character $character): CharacterResource
    {
        $character->load([
            'race',
            'background',
            'characterClasses.characterClass.levelProgression',
            'characterClasses.subclass',
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
     * PATCH /api/v1/characters/1 {"race_id": 5}
     * PATCH /api/v1/characters/1 {"strength": 18, "dexterity": 14}
     * ```
     *
     * **Validation:**
     * - Ability scores must be 3-20
     * - IDs (race_id, class_id, background_id) must exist
     */
    public function update(CharacterUpdateRequest $request, Character $character): CharacterResource
    {
        $validated = $request->validated();

        // Extract class_id and level before transaction
        $classId = $validated['class_id'] ?? null;
        $level = $validated['level'] ?? null;
        unset($validated['class_id'], $validated['level']);

        // Use transaction with pessimistic locking for all operations
        DB::transaction(function () use ($character, &$validated, $classId, $level) {
            // Lock character row first for HP/death save consistency
            $character->lockForUpdate()->first();

            // Auto-reset death saves when HP goes from 0 to positive (inside transaction)
            $wasAtZeroHp = $character->current_hit_points === 0;
            $newHp = $validated['current_hit_points'] ?? null;
            if ($wasAtZeroHp && $newHp !== null && $newHp > 0) {
                $validated['death_save_successes'] = 0;
                $validated['death_save_failures'] = 0;
            }

            // Handle class_id - add via junction table if provided
            if ($classId) {
                // Lock the character's class rows to prevent concurrent modifications
                $existingClasses = $character->characterClasses()->lockForUpdate()->get();

                // Only add if character doesn't already have this class
                if (! $existingClasses->where('class_id', $classId)->first()) {
                    $isPrimary = $existingClasses->isEmpty();
                    $order = ($existingClasses->max('order') ?? 0) + 1;

                    CharacterClassPivot::create([
                        'character_id' => $character->id,
                        'class_id' => $classId,
                        'level' => 1,
                        'is_primary' => $isPrimary,
                        'order' => $order,
                        'hit_dice_spent' => 0,
                    ]);
                }
            }

            // Handle level - update primary class level if provided
            if ($level !== null) {
                $primaryClass = $character->characterClasses()->where('is_primary', true)->first();
                if ($primaryClass) {
                    $primaryClass->update(['level' => $level]);
                }
            }

            $character->update($validated);
        });

        $character->load([
            'race',
            'background',
            'characterClasses.characterClass.levelProgression',
            'characterClasses.subclass',
            'media',
        ]);

        return new CharacterResource($character);
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
        $character->load(['characterClasses.characterClass.parentClass', 'characterClasses.characterClass.spellcastingAbility']);

        $stats = Cache::remember(
            "character:{$character->id}:stats",
            now()->addMinutes(15),
            fn () => CharacterStatsDTO::fromCharacter($character, $this->statCalculator)
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
     * - Pending choices (proficiencies, languages, spells, optional features, ASI)
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
            $this->hitDiceService
        );

        return new CharacterSummaryResource($summary);
    }
}
