<?php

namespace App\Http\Controllers\Api;

use App\Enums\SpellSlotType;
use App\Exceptions\InsufficientSpellSlotsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SpellSlot\UseSpellSlotRequest;
use App\Models\Character;
use App\Services\SpellSlotService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SpellSlotController extends Controller
{
    public function __construct(
        private SpellSlotService $spellSlotService
    ) {}

    /**
     * Get spell slots for a character
     *
     * Returns spell slots grouped by type (standard and pact_magic), with each
     * spell level showing max, used, and available counts.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/spell-slots
     * ```
     *
     * **Response:**
     * ```json
     * {
     *   "data": {
     *     "standard": {
     *       "1": { "max": 4, "used": 1, "available": 3 },
     *       "2": { "max": 3, "used": 0, "available": 3 }
     *     },
     *     "pact_magic": {
     *       "3": { "max": 2, "used": 1, "available": 1 }
     *     }
     *   }
     * }
     * ```
     */
    public function index(Character $character): JsonResponse
    {
        $slots = $this->spellSlotService->getSlots($character);

        return response()->json(['data' => $slots]);
    }

    /**
     * Use a spell slot
     *
     * Expends one spell slot of the specified level and type. Used when casting
     * a spell that consumes a slot.
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/spell-slots/use
     * {"spell_level": 1, "slot_type": "standard"}
     *
     * POST /api/v1/characters/1/spell-slots/use
     * {"spell_level": 3, "slot_type": "pact_magic"}
     * ```
     *
     * **Validation:**
     * - `spell_level` (required): 1-9
     * - `slot_type` (required): "standard" or "pact_magic"
     *
     * **Errors:**
     * - 422: No slots available at that level
     */
    public function use(UseSpellSlotRequest $request, Character $character): JsonResponse
    {
        $validated = $request->validated();
        $slotType = SpellSlotType::from($validated['slot_type']);

        try {
            $this->spellSlotService->useSlot(
                $character,
                $validated['spell_level'],
                $slotType
            );

            $slots = $this->spellSlotService->getSlots($character);

            return response()->json(['data' => $slots]);
        } catch (InsufficientSpellSlotsException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [
                    'spell_level' => [$e->getMessage()],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
