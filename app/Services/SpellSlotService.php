<?php

namespace App\Services;

use App\Enums\SpellSlotType;
use App\Exceptions\InsufficientSpellSlotsException;
use App\Models\Character;
use App\Models\CharacterSpellSlot;

class SpellSlotService
{
    public function __construct(
        private readonly MulticlassSpellSlotCalculator $calculator
    ) {}

    /**
     * Get all spell slots for a character.
     *
     * @return array{standard: array<int, array{max: int, used: int, available: int}>, pact_magic: array<int, array{max: int, used: int, available: int}>}
     */
    public function getSlots(Character $character): array
    {
        $slots = $character->spellSlots()->get();

        $result = [
            'standard' => [],
            'pact_magic' => [],
        ];

        foreach ($slots as $slot) {
            $key = $slot->slot_type === SpellSlotType::PACT_MAGIC ? 'pact_magic' : 'standard';
            $result[$key][$slot->spell_level] = [
                'max' => $slot->max_slots,
                'used' => $slot->used_slots,
                'available' => $slot->available,
            ];
        }

        return $result;
    }

    /**
     * Use a spell slot.
     *
     * @throws InsufficientSpellSlotsException
     */
    public function useSlot(Character $character, int $spellLevel, SpellSlotType $slotType): void
    {
        $slot = $character->spellSlots()
            ->where('spell_level', $spellLevel)
            ->where('slot_type', $slotType)
            ->first();

        if (! $slot) {
            throw new InsufficientSpellSlotsException($spellLevel, $slotType, 0);
        }

        if (! $slot->useSlot()) {
            throw new InsufficientSpellSlotsException($spellLevel, $slotType, $slot->available);
        }
    }

    /**
     * Reset spell slots of a specific type.
     */
    public function resetSlots(Character $character, SpellSlotType $slotType): void
    {
        $character->spellSlots()
            ->where('slot_type', $slotType)
            ->update(['used_slots' => 0]);
    }

    /**
     * Reset all spell slots (both standard and pact magic).
     */
    public function resetAllSlots(Character $character): void
    {
        $character->spellSlots()->update(['used_slots' => 0]);
    }

    /**
     * Recalculate max spell slots based on current class levels.
     *
     * Call this after level up or multiclassing to update available slots.
     */
    public function recalculateMaxSlots(Character $character): void
    {
        $character->load('characterClasses.characterClass');

        $slotResult = $this->calculator->calculate($character);

        // Handle standard spell slots
        if ($slotResult->standardSlots) {
            foreach ($slotResult->standardSlots as $levelKey => $maxSlots) {
                // Convert ordinal key ("1st", "2nd") to integer (1, 2)
                $spellLevel = $this->ordinalToInt($levelKey);

                if ($maxSlots > 0) {
                    CharacterSpellSlot::updateOrCreate(
                        [
                            'character_id' => $character->id,
                            'spell_level' => $spellLevel,
                            'slot_type' => SpellSlotType::STANDARD,
                        ],
                        [
                            'max_slots' => $maxSlots,
                            // Don't reset used_slots on recalculate
                        ]
                    );
                }
            }

            // Remove slots that are no longer available (if max became 0)
            $validLevels = array_map(
                fn ($key) => $this->ordinalToInt($key),
                array_keys(array_filter($slotResult->standardSlots, fn ($v) => $v > 0))
            );
            $character->spellSlots()
                ->where('slot_type', SpellSlotType::STANDARD)
                ->whereNotIn('spell_level', $validLevels)
                ->delete();
        } else {
            // No standard slots - remove any existing
            $character->spellSlots()
                ->where('slot_type', SpellSlotType::STANDARD)
                ->delete();
        }

        // Handle pact magic slots
        if ($slotResult->pactSlots) {
            CharacterSpellSlot::updateOrCreate(
                [
                    'character_id' => $character->id,
                    'spell_level' => $slotResult->pactSlots->level,
                    'slot_type' => SpellSlotType::PACT_MAGIC,
                ],
                [
                    'max_slots' => $slotResult->pactSlots->count,
                    // Don't reset used_slots on recalculate
                ]
            );

            // Remove pact slots at other levels (warlock slots upgrade to single level)
            $character->spellSlots()
                ->where('slot_type', SpellSlotType::PACT_MAGIC)
                ->where('spell_level', '!=', $slotResult->pactSlots->level)
                ->delete();
        } else {
            // No pact magic - remove any existing
            $character->spellSlots()
                ->where('slot_type', SpellSlotType::PACT_MAGIC)
                ->delete();
        }
    }

    /**
     * Convert ordinal string to integer.
     *
     * @param  string  $ordinal  e.g., "1st", "2nd", "3rd", "4th"
     * @return int e.g., 1, 2, 3, 4
     */
    private function ordinalToInt(string $ordinal): int
    {
        return (int) preg_replace('/[^0-9]/', '', $ordinal);
    }
}
