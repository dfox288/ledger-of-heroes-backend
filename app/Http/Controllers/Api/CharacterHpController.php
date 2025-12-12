<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Character\CharacterHpModifyRequest;
use App\Http\Resources\CharacterHpResource;
use App\Models\Character;
use App\Services\HitPointService;

/**
 * Handles HP modification for characters with D&D 5e rule enforcement.
 *
 * D&D 5e HP Rules:
 * - Damage subtracts from temp HP first, overflow to current HP
 * - Healing adds to current HP, caps at max HP
 * - Current HP cannot go below 0
 * - Temp HP doesn't stack (higher value wins)
 * - Death saves reset when HP goes from 0 to positive
 */
class CharacterHpController extends Controller
{
    public function __construct(
        private HitPointService $hitPointService
    ) {}

    /**
     * Modify character HP
     *
     * Modifies character HP with full D&D 5e rule enforcement. Accepts damage,
     * healing, or absolute HP values, plus optional temp HP.
     *
     * @x-flow gameplay-combat
     *
     * **Examples:**
     * ```
     * PATCH /api/v1/characters/1/hp
     *
     * # Damage (subtracts, temp HP absorbs first)
     * {"hp": "-12"}
     *
     * # Healing (adds, caps at max HP)
     * {"hp": "+15"}
     *
     * # Set absolute value (caps at max HP)
     * {"hp": "45"}
     *
     * # Set temp HP (higher-wins logic)
     * {"temp_hp": 10}
     *
     * # Combined
     * {"hp": "+10", "temp_hp": 15}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `hp` | string | No | HP change: "-X" for damage, "+X" for healing, "X" to set |
     * | `temp_hp` | integer | No | Temp HP value (0+ only, higher-wins unless 0 which clears) |
     *
     * **D&D Rules Applied:**
     * - Damage: Temp HP absorbs first, overflow hits current HP
     * - Healing: Caps at max HP
     * - Set: Caps at max HP
     * - Temp HP: Keeps higher of current or new (except 0 clears)
     * - Death saves reset when healing from 0 HP
     *
     * **Response Fields:**
     * - `current_hit_points`: Current HP after modification
     * - `max_hit_points`: Maximum HP (unchanged)
     * - `temp_hit_points`: Temporary HP after modification
     * - `death_save_successes`: Death save successes (may reset to 0)
     * - `death_save_failures`: Death save failures (may reset to 0)
     */
    public function __invoke(CharacterHpModifyRequest $request, Character $character): CharacterHpResource
    {
        $hpChange = $request->parseHpChange();
        $tempHp = $request->has('temp_hp') ? $request->integer('temp_hp') : null;

        $result = $this->hitPointService->modifyHp($character, $hpChange, $tempHp);

        return new CharacterHpResource($result);
    }
}
