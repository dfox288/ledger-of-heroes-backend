<?php

namespace App\Http\Controllers\Api;

use App\Enums\SpellSlotType;
use App\Exceptions\InsufficientSpellSlotsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SpellSlot\UseSpellSlotRequest;
use App\Http\Resources\SpellSlotsResource;
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
     * @deprecated Use GET /api/v1/characters/{id}/spell-slots instead
     *
     * Returns spell slots grouped by type (standard and pact_magic), with each
     * spell level showing max, used, and available counts.
     *
     * **Deprecation Notice:**
     * This endpoint is deprecated and will be removed on 2026-06-01.
     * Use GET /api/v1/characters/{id}/spell-slots for consolidated slot data
     * that includes both slot maximums and tracked usage.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/spell-slots/tracked
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

        return (new SpellSlotsResource($slots))
            ->response()
            ->withHeaders([
                'Deprecation' => 'true',
                'Sunset' => 'Sat, 01 Jun 2026 00:00:00 GMT',
                'Link' => '</api/v1/characters/'.$character->id.'/spell-slots>; rel="successor-version"',
            ]);
    }

    /**
     * Use a spell slot
     *
     * Expends one spell slot of the specified level and type. Used when casting
     * a spell that consumes a slot.
     *
     * @x-flow gameplay-combat
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/spell-slots/use
     *
     * # Use a 1st level standard slot
     * {"spell_level": 1, "slot_type": "standard"}
     *
     * # Use a pact magic slot (Warlock)
     * {"spell_level": 3, "slot_type": "pact_magic"}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `spell_level` | integer | Yes | Spell level 1-9 (cantrips don't use slots) |
     * | `slot_type` | string | Yes | "standard" or "pact_magic" |
     *
     * **Slot Types:**
     * - `standard` - Normal spell slots (most spellcasters)
     * - `pact_magic` - Warlock pact magic slots (fewer slots, higher level, recharge on short rest)
     *
     * **Spell Levels (1-9):**
     * Cantrips (level 0) do not consume spell slots and cannot be specified here.
     *
     * @response 200 SpellSlotsResource with updated slot counts
     * @response 422 array{message: string, errors: array{spell_level: string[]}} No slots available at that level
     */
    public function use(UseSpellSlotRequest $request, Character $character): SpellSlotsResource|JsonResponse
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

            return new SpellSlotsResource($slots);
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
