<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\LevelUpService;
use Illuminate\Http\JsonResponse;

class CharacterLevelUpController extends Controller
{
    public function __construct(
        private LevelUpService $levelUpService
    ) {}

    /**
     * Level up a character
     *
     * Increases the character's level by 1 in their primary class. Automatically
     * calculates HP increase, grants class features, and updates spell slots.
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/level-up
     * ```
     *
     * **What Happens on Level Up:**
     * 1. Level in primary class increases by 1
     * 2. HP increases (based on class hit die + CON modifier)
     * 3. New class features are granted (if any at this level)
     * 4. Spell slots updated for spellcasters
     * 5. ASI/Feat choice flagged at levels 4, 8, 12, 16, 19
     *
     * **HP Calculation:**
     * - Level 1: Max hit die + CON modifier
     * - Level 2+: (hit die / 2 + 1) + CON modifier (average roll)
     *
     * **Response Fields:**
     * - `previous_level` - Level before this level up
     * - `new_level` - Level after this level up
     * - `hp_increase` - HP gained this level
     * - `new_max_hp` - Total max HP after level up
     * - `features_gained` - Array of new class features
     * - `spell_slots` - Updated spell slot progression
     * - `asi_pending` - True if character needs to select ASI/Feat
     *
     * **Multiclass Notes:**
     * This endpoint levels up the character's primary (first) class. To level up
     * a specific class in a multiclass build, use `POST /api/v1/characters/{id}/classes/{classId}/level-up`.
     *
     * @param  Character  $character  The character to level up
     *
     * @response 200 array{previous_level: int, new_level: int, hp_increase: int, new_max_hp: int, features_gained: array, spell_slots: array, asi_pending: bool}
     * @response 404 array{message: string} Character not found
     * @response 422 array{message: string} Character at max level (20) or character incomplete
     */
    public function __invoke(Character $character): JsonResponse
    {
        $result = $this->levelUpService->levelUp($character);

        return response()->json($result->toArray());
    }
}
