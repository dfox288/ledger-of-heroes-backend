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
     * @deprecated Use POST /characters/{id}/classes/{class}/level-up instead.
     *             This endpoint will be removed in API v2.
     *
     * **Why is this deprecated?**
     * This endpoint automatically levels up the character's primary (first) class,
     * which creates ambiguity in multiclass builds. The new class-specific endpoint
     * provides explicit control over which class gains a level.
     *
     * **Migration Path:**
     * - OLD: `POST /api/v1/characters/{id}/level-up`
     * - NEW: `POST /api/v1/characters/{id}/classes/{classId}/level-up`
     *
     * **Removal Timeline:**
     * - Deprecated: December 2025
     * - Planned Removal: June 2026 (API v2)
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
     */
    public function __invoke(Character $character): JsonResponse
    {
        $result = $this->levelUpService->levelUp($character);

        return response()->json($result->toArray())
            ->header('Deprecation', 'true')
            ->header('Sunset', 'Sat, 01 Jun 2026 00:00:00 GMT')
            ->header('Link', '</api/v1/characters/'.$character->id.'/classes/{class}/level-up>; rel="successor-version"');
    }
}
