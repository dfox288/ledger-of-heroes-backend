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
