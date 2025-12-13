<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Character\CharacterCurrencyRequest;
use App\Http\Resources\CharacterCurrencyResource;
use App\Models\Character;
use App\Services\CurrencyService;

/**
 * Handles currency modification for characters with D&D 5e auto-conversion.
 *
 * D&D 5e Currency Rules:
 * - 1 PP = 10 GP = 100 SP = 1000 CP
 * - 1 GP = 10 SP = 100 CP
 * - 1 EP = 5 SP = 50 CP
 * - 1 SP = 10 CP
 *
 * When subtracting currency, higher denominations are automatically
 * converted ("making change") if the character doesn't have enough
 * of the requested coin type.
 */
class CharacterCurrencyController extends Controller
{
    public function __construct(
        private CurrencyService $currencyService
    ) {}

    /**
     * Modify character currency
     *
     * Modifies character currency with automatic coin conversion. Accepts add,
     * subtract, or set operations for each coin type.
     *
     * @x-flow gameplay-exploration
     *
     * **Examples:**
     * ```
     * PATCH /api/v1/characters/1/currency
     *
     * # Subtract gold (auto-converts if needed)
     * {"gp": "-5"}
     *
     * # Add silver
     * {"sp": "+10"}
     *
     * # Set copper to exact value
     * {"cp": "25"}
     *
     * # Combined operations
     * {"gp": "-5", "sp": "+10", "cp": "25"}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `pp` | string | No | Platinum change: "-X", "+X", or "X" to set |
     * | `gp` | string | No | Gold change: "-X", "+X", or "X" to set |
     * | `ep` | string | No | Electrum change: "-X", "+X", or "X" to set |
     * | `sp` | string | No | Silver change: "-X", "+X", or "X" to set |
     * | `cp` | string | No | Copper change: "-X", "+X", or "X" to set |
     *
     * **Auto-Conversion:**
     * When subtracting coins and the character doesn't have enough, higher
     * denominations are automatically broken down. Example: subtracting 50 CP
     * when character has 0 CP but 1 GP will convert 1 GP to 100 CP, then
     * subtract 50 CP, leaving 0 GP and 50 CP.
     *
     * **Response Fields:**
     * - `pp`: Platinum coins after modification
     * - `gp`: Gold coins after modification
     * - `ep`: Electrum coins after modification
     * - `sp`: Silver coins after modification
     * - `cp`: Copper coins after modification
     */
    public function __invoke(CharacterCurrencyRequest $request, Character $character): CharacterCurrencyResource
    {
        $changes = $request->parseCurrencyChanges();

        $result = $this->currencyService->modifyCurrency($character, $changes);

        return new CharacterCurrencyResource($result);
    }
}
