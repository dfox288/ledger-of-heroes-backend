<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Character\CharacterReviveRequest;
use App\Http\Resources\CharacterResource;
use App\Models\Character;
use App\Models\CharacterCondition;

/**
 * Handles character revival from death.
 *
 * D&D 5e Revival Rules:
 * - Revivify (3rd level): Returns with 1 HP
 * - Raise Dead (5th level): Returns with 1 HP
 * - Resurrection (7th level): Returns with 1 HP
 * - True Resurrection (9th level): Full HP
 *
 * This endpoint provides a clean atomic operation for revival,
 * handling all necessary state resets in one request.
 */
class CharacterReviveController extends Controller
{
    /**
     * Revive a dead character
     *
     * Brings a dead character back to life, resetting death saves and optionally
     * clearing exhaustion. This is an atomic operation that handles all state
     * changes required for revival.
     *
     * @x-flow gameplay-combat
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/revive
     *
     * # Default revival (1 HP, clear exhaustion)
     * {}
     *
     * # Revival with specific HP
     * {"hit_points": 25}
     *
     * # True Resurrection (full HP)
     * {"hit_points": 999}  # Will be capped to max HP
     *
     * # Revivify (preserve any non-lethal exhaustion)
     * {"clear_exhaustion": false}
     *
     * # With source tracking (for future audit trail)
     * {"source": "Revivify spell", "hit_points": 1}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `hit_points` | integer | No | HP to set (default: 1, capped at max HP) |
     * | `clear_exhaustion` | boolean | No | Remove exhaustion condition (default: true) |
     * | `source` | string | No | What caused the revival (not stored yet, for future use) |
     *
     * **Actions Performed:**
     * 1. Sets `is_dead` to false
     * 2. Resets `death_save_successes` to 0
     * 3. Resets `death_save_failures` to 0
     * 4. Sets `current_hit_points` to specified value (default 1, capped at max)
     * 5. If `clear_exhaustion` is true (default), removes exhaustion condition
     *
     * **Validation:**
     * - Character must be dead (`is_dead = true`)
     * - Hit points must be at least 1
     */
    public function __invoke(CharacterReviveRequest $request, Character $character): CharacterResource
    {
        // Determine HP to set (default 1, cap at max)
        $hitPoints = $request->input('hit_points', 1);
        $hitPoints = min($hitPoints, $character->max_hit_points);

        // Revive the character
        $character->is_dead = false;
        $character->death_save_successes = 0;
        $character->death_save_failures = 0;
        $character->current_hit_points = $hitPoints;
        $character->save();

        // Clear exhaustion if requested (default: true)
        if ($request->input('clear_exhaustion', true)) {
            CharacterCondition::where('character_id', $character->id)
                ->where('condition_slug', 'LIKE', '%:exhaustion')
                ->delete();
        }

        // Source is accepted but not stored yet (for future audit trail)
        // $source = $request->input('source');

        return new CharacterResource($character);
    }
}
