<?php

namespace App\Http\Controllers\Api;

use App\Enums\SpellSlotType;
use App\Exceptions\InsufficientSpellSlotsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Character\Combat\UpdateSpellSlotRequest;
use App\Http\Requests\Character\Combat\UseSpellSlotRequest;
use App\Http\Resources\SpellSlotResource;
use App\Http\Resources\SpellSlotsResource;
use App\Models\Character;
use App\Models\CharacterSpellSlot;
use App\Services\CharacterStatCalculator;
use App\Services\SpellSlotService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SpellSlotController extends Controller
{
    public function __construct(
        private SpellSlotService $spellSlotService,
        private CharacterStatCalculator $statCalculator
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

    /**
     * Update spell slot usage for a specific level
     *
     * Modifies the spent slots for a specific spell level. Supports both
     * absolute value updates and action-based updates.
     *
     * @x-flow gameplay-combat
     *
     * **Examples:**
     * ```
     * PATCH /api/v1/characters/1/spell-slots/1
     *
     * # Set absolute spent value
     * {"spent": 2}
     *
     * # Action-based updates
     * {"action": "use"}      // spent += 1
     * {"action": "restore"}  // spent -= 1
     * {"action": "reset"}    // spent = 0
     *
     * # With explicit slot type (for multiclass with pact magic)
     * {"spent": 1, "slot_type": "pact_magic"}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `spent` | integer | No | Absolute number of spent slots (0 to max) |
     * | `action` | string | No | One of: "use", "restore", "reset" |
     * | `slot_type` | string | No | "standard" (default) or "pact_magic" |
     *
     * **Note:** Either `spent` or `action` must be provided, not both.
     *
     * @param  Character  $character  The character
     * @param  int  $level  Spell slot level (1-9)
     */
    public function update(UpdateSpellSlotRequest $request, Character $character, int $level): SpellSlotResource|JsonResponse
    {
        // Validate spell level range
        if ($level < 1 || $level > 9) {
            return response()->json([
                'message' => 'Spell slot not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $slotTypeValue = $request->input('slot_type', 'standard');
        $slotType = SpellSlotType::from($slotTypeValue);

        // Find or create the slot record
        $slot = $this->findOrCreateSlot($character, $level, $slotType);

        if (! $slot) {
            return response()->json([
                'message' => 'Spell slot not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Handle the update
        if ($request->has('spent')) {
            $slot->update(['used_slots' => $request->integer('spent')]);
        } elseif ($request->has('action')) {
            $action = $request->input('action');

            switch ($action) {
                case 'use':
                    if (! $slot->hasAvailable()) {
                        return response()->json([
                            'message' => 'No spell slots available at this level.',
                        ], Response::HTTP_UNPROCESSABLE_ENTITY);
                    }
                    $slot->useSlot();

                    break;

                case 'restore':
                    if ($slot->used_slots <= 0) {
                        return response()->json([
                            'message' => 'No spell slots to restore at this level.',
                        ], Response::HTTP_UNPROCESSABLE_ENTITY);
                    }
                    $slot->decrement('used_slots');

                    break;

                case 'reset':
                    $slot->reset();

                    break;
            }
        }

        $slot->refresh();

        return new SpellSlotResource([
            'level' => $slot->spell_level,
            'total' => $slot->max_slots,
            'spent' => $slot->used_slots,
            'available' => $slot->available,
            'slot_type' => $slot->slot_type->value,
        ]);
    }

    /**
     * Find or create a spell slot record.
     *
     * Creates the slot if it doesn't exist but the character has calculated slots at this level.
     */
    private function findOrCreateSlot(Character $character, int $level, SpellSlotType $slotType): ?CharacterSpellSlot
    {
        // Try to find existing slot
        $slot = $character->spellSlots()
            ->where('spell_level', $level)
            ->where('slot_type', $slotType)
            ->first();

        if ($slot) {
            return $slot;
        }

        // Calculate what slots this character should have
        $class = $character->primary_class;

        if (! $class) {
            return null;
        }

        $baseClassName = $class->parent_class_id
            ? strtolower($class->parentClass->name ?? '')
            : strtolower($class->name);

        $calculatedSlots = $this->statCalculator->getSpellSlots($baseClassName, $character->total_level);

        // Check if the character should have a slot at this level
        if (! isset($calculatedSlots[$level]) || $calculatedSlots[$level] <= 0) {
            return null;
        }

        // Determine the correct slot type for this character
        $expectedSlotType = strtolower($baseClassName) === 'warlock'
            ? SpellSlotType::PACT_MAGIC
            : SpellSlotType::STANDARD;

        if ($slotType !== $expectedSlotType) {
            return null;
        }

        // Create the slot record
        return CharacterSpellSlot::create([
            'character_id' => $character->id,
            'spell_level' => $level,
            'max_slots' => $calculatedSlots[$level],
            'used_slots' => 0,
            'slot_type' => $slotType,
        ]);
    }
}
