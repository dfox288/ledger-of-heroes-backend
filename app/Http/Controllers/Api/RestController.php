<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\RestService;
use Illuminate\Http\JsonResponse;

class RestController extends Controller
{
    public function __construct(
        private RestService $restService
    ) {}

    /**
     * Perform a short rest
     *
     * Allows the character to take a short rest (1+ hours). Effects:
     * - Pact magic spell slots (Warlock) are reset
     * - Features with "resets on short rest" are reset
     * - Character can spend hit dice to heal (use the hit-dice/spend endpoint separately)
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/short-rest
     * ```
     *
     * **Response:**
     * ```json
     * {
     *   "data": {
     *     "pact_magic_reset": true,
     *     "features_reset": ["Second Wind", "Action Surge"]
     *   }
     * }
     * ```
     */
    public function shortRest(Character $character): JsonResponse
    {
        $result = $this->restService->shortRest($character);

        return response()->json(['data' => $result]);
    }

    /**
     * Perform a long rest
     *
     * Allows the character to take a long rest (8+ hours). Effects:
     * - HP restored to maximum
     * - Half of total hit dice recovered (minimum 1)
     * - All spell slots reset (standard and pact magic)
     * - Death saves cleared
     * - All features reset (short rest, long rest, and dawn)
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/long-rest
     * ```
     *
     * **Response:**
     * ```json
     * {
     *   "data": {
     *     "hp_restored": 15,
     *     "hit_dice_recovered": 3,
     *     "spell_slots_reset": true,
     *     "death_saves_cleared": true,
     *     "features_reset": ["Rage", "Second Wind", "Indomitable"]
     *   }
     * }
     * ```
     */
    public function longRest(Character $character): JsonResponse
    {
        $result = $this->restService->longRest($character);

        return response()->json(['data' => $result]);
    }
}
