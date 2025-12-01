<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CharacterStatsDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Character\CharacterIndexRequest;
use App\Http\Requests\Character\CharacterShowRequest;
use App\Http\Requests\Character\CharacterStoreRequest;
use App\Http\Requests\Character\CharacterUpdateRequest;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\CharacterStatsResource;
use App\Models\Character;
use App\Services\CharacterStatCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class CharacterController extends Controller
{
    public function __construct(
        private CharacterStatCalculator $statCalculator
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

        $characters = Character::with(['race', 'characterClass', 'background'])
            ->paginate($perPage);

        return CharacterResource::collection($characters);
    }

    /**
     * Create a new character
     *
     * Creates a new character with the provided data. Supports wizard-style creation where only name is required,
     * and other fields can be filled in later via PATCH updates.
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
        $character = Character::create($request->validated());

        $character->load(['race', 'characterClass', 'background']);

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
        $character->load(['race', 'characterClass', 'background']);

        return new CharacterResource($character);
    }

    /**
     * Update a character
     *
     * Updates character fields. Supports partial updates (PATCH semantics).
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
        $character->update($request->validated());

        $character->load(['race', 'characterClass', 'background']);

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
        $character->load(['characterClass.parentClass', 'characterClass.spellcastingAbility']);

        $stats = Cache::remember(
            "character:{$character->id}:stats",
            now()->addMinutes(15),
            fn () => CharacterStatsDTO::fromCharacter($character, $this->statCalculator)
        );

        return new CharacterStatsResource($stats);
    }
}
