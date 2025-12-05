<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientHitDiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\HitDice\RecoverHitDiceRequest;
use App\Http\Requests\HitDice\SpendHitDiceRequest;
use App\Http\Resources\HitDiceResource;
use App\Models\Character;
use App\Services\HitDiceService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class HitDiceController extends Controller
{
    public function __construct(
        private HitDiceService $hitDiceService
    ) {}

    /**
     * Get hit dice for a character
     *
     * Returns hit dice grouped by die type with totals. Useful for displaying
     * available healing resources before a short rest.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/hit-dice
     * ```
     *
     * **Response:**
     * ```json
     * {
     *   "data": {
     *     "hit_dice": {
     *       "d10": { "available": 3, "max": 5, "spent": 2 }
     *     },
     *     "total": { "available": 3, "max": 5, "spent": 2 }
     *   }
     * }
     * ```
     */
    public function index(Character $character): HitDiceResource
    {
        return new HitDiceResource($this->hitDiceService->getHitDice($character));
    }

    /**
     * Spend hit dice
     *
     * Spend one or more hit dice of a specific type. Used during short rests
     * to heal. The character rolls the hit dice and adds their Constitution
     * modifier to determine HP recovered (client-side calculation).
     *
     * @x-flow gameplay-rest
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/hit-dice/spend
     * {"die_type": "d10", "quantity": 2}
     * ```
     *
     * **Validation:**
     * - `die_type` (required): Must be d6, d8, d10, or d12
     * - `quantity` (required): Must be at least 1
     *
     * **Errors:**
     * - 422: Invalid die type or quantity
     * - 422: Not enough hit dice available
     */
    public function spend(SpendHitDiceRequest $request, Character $character): HitDiceResource|JsonResponse
    {
        try {
            $result = $this->hitDiceService->spend(
                $character,
                $request->validated('die_type'),
                $request->validated('quantity')
            );

            return new HitDiceResource($result);
        } catch (InsufficientHitDiceException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [
                    'die_type' => [$e->getMessage()],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Recover hit dice
     *
     * Recover spent hit dice. Typically used during long rests. If quantity
     * is not specified, recovers half of total max hit dice (minimum 1) per
     * D&D 5e rules. Larger dice are recovered first to maximize healing potential.
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/hit-dice/recover
     * {"quantity": 3}
     *
     * POST /api/v1/characters/1/hit-dice/recover
     * {} // Recovers half of total (D&D 5e standard)
     * ```
     *
     * **Validation:**
     * - `quantity` (optional): Must be at least 1 if provided
     */
    public function recover(RecoverHitDiceRequest $request, Character $character): HitDiceResource
    {
        $result = $this->hitDiceService->recover(
            $character,
            $request->validated('quantity')
        );

        return new HitDiceResource($result);
    }
}
