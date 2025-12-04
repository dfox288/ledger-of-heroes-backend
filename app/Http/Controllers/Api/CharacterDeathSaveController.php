<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Character\DeathSaveRequest;
use App\Models\Character;
use Illuminate\Http\JsonResponse;

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
     * Record a death saving throw result.
     */
    public function store(DeathSaveRequest $request, Character $character): JsonResponse
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

        return response()->json([
            'data' => [
                'death_save_successes' => $character->death_save_successes,
                'death_save_failures' => $character->death_save_failures,
                'current_hit_points' => $character->current_hit_points,
                'result' => $result,
                'outcome' => $outcome,
                'is_stable' => $character->death_save_successes >= 3,
                'is_dead' => $character->death_save_failures >= 3,
            ],
        ]);
    }

    /**
     * Stabilize a character and reset death saves.
     */
    public function stabilize(Character $character): JsonResponse
    {
        $character->death_save_successes = 0;
        $character->death_save_failures = 0;
        $character->save();

        return response()->json([
            'data' => [
                'death_save_successes' => 0,
                'death_save_failures' => 0,
                'is_stable' => true,
            ],
        ]);
    }
}
