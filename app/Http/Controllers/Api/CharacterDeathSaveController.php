<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Character\DeathSaveRequest;
use App\Http\Resources\DeathSaveResultResource;
use App\Http\Resources\DeathSaveStatusResource;
use App\Models\Character;

/**
 * Handles death saving throws for characters at 0 HP.
 *
 * D&D 5e Death Save Rules:
 * - At 0 HP, character makes death saving throws (DC 10)
 * - 3 successes = stabilized (unconscious but not dying)
 * - 3 failures = character dies
 * - Rolling a 1 = 2 failures (critical failure)
 * - Rolling a 20 = regain 1 HP, wake up, reset saves (critical success)
 * - Taking damage at 0 HP = automatic failure (crit = 2 failures)
 */
class CharacterDeathSaveController extends Controller
{
    /**
     * Record a death saving throw result
     *
     * Records either a death save roll or damage taken at 0 HP. The API handles all
     * D&D 5e death save mechanics including critical rolls and damage failures.
     *
     * @x-flow gameplay-combat
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/death-saves
     *
     * # Roll result (d20 value)
     * {"roll": 15}       # Success (10+)
     * {"roll": 8}        # Failure (9-)
     * {"roll": 20}       # Critical success - regain 1 HP, reset saves
     * {"roll": 1}        # Critical failure - 2 failures
     *
     * # Taking damage at 0 HP
     * {"damage": 5}                    # 1 automatic failure
     * {"damage": 10, "is_critical": true}  # 2 automatic failures (crit hit)
     * ```
     *
     * **Request Body (Option A - Death Save Roll):**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `roll` | integer | Yes | The d20 roll result (1-20) |
     *
     * **Request Body (Option B - Damage at 0 HP):**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `damage` | integer | Yes | Damage amount taken (triggers automatic failure) |
     * | `is_critical` | boolean | No | If true, counts as 2 failures (default: false) |
     *
     * **Roll Results:**
     * | Roll | Result | Effect |
     * |------|--------|--------|
     * | 20 | `critical_success` | Regain 1 HP, wake up, reset all saves |
     * | 10-19 | `success` | Add 1 success (3 = stable) |
     * | 2-9 | `failure` | Add 1 failure (3 = dead) |
     * | 1 | `critical_failure` | Add 2 failures |
     *
     * **Response Fields:**
     * - `death_save_successes` (0-3): Current success count
     * - `death_save_failures` (0-3): Current failure count
     * - `current_hit_points`: Character's HP (may be 1 on nat 20)
     * - `result`: What happened (success, failure, critical_success, critical_failure, damage, critical_damage)
     * - `outcome`: Final outcome if determined (stable, dead, conscious) or null
     * - `is_stable`: True if 3+ successes
     * - `is_dead`: True if 3+ failures
     */
    public function store(DeathSaveRequest $request, Character $character): DeathSaveResultResource
    {
        $result = null;
        $outcome = null;
        $successes = $character->death_save_successes;
        $failures = $character->death_save_failures;

        if ($request->has('roll')) {
            $roll = $request->integer('roll');

            if ($roll === 20) {
                // Critical success: regain 1 HP, reset saves
                $result = 'critical_success';
                $outcome = 'conscious';
                $successes = 0;
                $failures = 0;
                $character->current_hit_points = 1;
            } elseif ($roll === 1) {
                // Critical failure: 2 failures
                $result = 'critical_failure';
                $failures = min(3, $failures + 2);
            } elseif ($roll >= 10) {
                // Success
                $result = 'success';
                $successes = min(3, $successes + 1);
            } else {
                // Failure
                $result = 'failure';
                $failures = min(3, $failures + 1);
            }
        } elseif ($request->has('damage')) {
            // Taking damage at 0 HP = automatic failure
            $isCritical = $request->boolean('is_critical');
            $failuresToAdd = $isCritical ? 2 : 1;
            $failures = min(3, $failures + $failuresToAdd);
            $result = $isCritical ? 'critical_damage' : 'damage';
        }

        // Check for outcomes
        if ($outcome === null) {
            if ($successes >= 3) {
                $outcome = 'stable';
            } elseif ($failures >= 3) {
                $outcome = 'dead';
            }
        }

        // Update character
        $character->death_save_successes = $successes;
        $character->death_save_failures = $failures;
        $character->save();

        return new DeathSaveResultResource([
            'death_save_successes' => $character->death_save_successes,
            'death_save_failures' => $character->death_save_failures,
            'current_hit_points' => $character->current_hit_points,
            'result' => $result,
            'outcome' => $outcome,
            'is_stable' => $character->death_save_successes >= 3,
            'is_dead' => $character->death_save_failures >= 3,
        ]);
    }

    /**
     * Stabilize a character and reset death saves
     *
     * Manually stabilizes a character (e.g., via Spare the Dying spell or Medicine check).
     * Resets both success and failure counters to 0.
     *
     * @x-flow gameplay-combat
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/death-saves/stabilize
     * ```
     *
     * **Use Cases:**
     * - Spare the Dying cantrip cast on character
     * - Successful Medicine check (DC 10) to stabilize
     * - Healing spell cast on character (though this also heals HP)
     *
     * **Note:** A stabilized character remains at 0 HP and unconscious but no longer
     * makes death saving throws. They regain 1 HP after 1d4 hours.
     */
    public function stabilize(Character $character): DeathSaveStatusResource
    {
        $character->death_save_successes = 0;
        $character->death_save_failures = 0;
        $character->save();

        return new DeathSaveStatusResource([
            'death_save_successes' => 0,
            'death_save_failures' => 0,
            'is_stable' => true,
        ]);
    }

    /**
     * Reset death saves without stabilizing
     *
     * Manually resets both success and failure counters to 0. Use this when
     * the character regains HP through healing or other means.
     *
     * @x-flow gameplay-combat
     *
     * **Examples:**
     * ```
     * DELETE /api/v1/characters/1/death-saves
     * ```
     *
     * **Use Cases:**
     * - Character receives healing while making death saves
     * - DM manually resets death save tracking
     * - Character is revived after dying
     */
    public function reset(Character $character): DeathSaveStatusResource
    {
        $character->death_save_successes = 0;
        $character->death_save_failures = 0;
        $character->save();

        return new DeathSaveStatusResource([
            'death_save_successes' => 0,
            'death_save_failures' => 0,
        ]);
    }
}
