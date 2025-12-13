<?php

namespace App\Services\Parsers\Concerns;

use SimpleXMLElement;

/**
 * Parses spell slot progression and optional spellcasting from class XML.
 *
 * Handles both base class spellcasting (Wizard, Cleric) and optional
 * subclass spellcasting (Eldritch Knight, Arcane Trickster).
 */
trait ParsesSpellProgression
{
    /**
     * Parse spell slots from autolevel elements.
     * Skips optional slots (which are handled by parseOptionalSpellSlots).
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseSpellSlots(SimpleXMLElement $element): array
    {
        $spellProgression = [];
        $spellsKnownByLevel = [];
        $hasOptionalSlots = false;

        // First pass: collect spell slots and spells_known counters separately
        // This handles XML where slots and counters are in different <autolevel> elements
        foreach ($element->autolevel as $autolevel) {
            $level = (int) $autolevel['level'];

            // Check if this autolevel has spell slots
            if (isset($autolevel->slots)) {
                // Check if slots are marked as optional (subclass-only)
                $isOptional = isset($autolevel->slots['optional'])
                    && (string) $autolevel->slots['optional'] === 'YES';

                if ($isOptional) {
                    $hasOptionalSlots = true;

                    continue;
                }

                $slotsString = (string) $autolevel->slots;
                $slots = array_map('intval', explode(',', $slotsString));

                // Format: cantrips, 1st, 2nd, 3rd, ..., 9th
                $progression = [
                    'level' => $level,
                    'cantrips_known' => $slots[0] ?? 0,
                    'spell_slots_1st' => $slots[1] ?? 0,
                    'spell_slots_2nd' => $slots[2] ?? 0,
                    'spell_slots_3rd' => $slots[3] ?? 0,
                    'spell_slots_4th' => $slots[4] ?? 0,
                    'spell_slots_5th' => $slots[5] ?? 0,
                    'spell_slots_6th' => $slots[6] ?? 0,
                    'spell_slots_7th' => $slots[7] ?? 0,
                    'spell_slots_8th' => $slots[8] ?? 0,
                    'spell_slots_9th' => $slots[9] ?? 0,
                ];

                $spellProgression[$level] = $progression;
            }

            // Collect "Spells Known" counters (may be in separate autolevel from slots)
            foreach ($autolevel->counter as $counterElement) {
                if ((string) $counterElement->name === 'Spells Known') {
                    $spellsKnownByLevel[$level] = (int) $counterElement->value;
                    break;
                }
            }
        }

        // Second pass: merge spells_known into spell progression
        // Only if this class has non-optional spell slots (not Fighter/Rogue subclass casters)
        if (! empty($spellProgression) && ! $hasOptionalSlots) {
            foreach ($spellsKnownByLevel as $level => $spellsKnown) {
                if (isset($spellProgression[$level])) {
                    $spellProgression[$level]['spells_known'] = $spellsKnown;
                }
            }
        }

        // Re-index array to be sequential
        return array_values($spellProgression);
    }

    /**
     * Parse optional spell slots and match them to spellcasting subclasses.
     * Returns array keyed by subclass name containing spell progression data.
     *
     * @return array<string, array{spellcasting_ability: string, spell_progression: array}>
     */
    private function parseOptionalSpellSlots(SimpleXMLElement $element): array
    {
        $optionalSlots = [];
        $spellsKnownByLevel = [];
        $spellcastingAbility = null;

        // First pass: Collect all optional spell slots and "Spells Known" counters
        foreach ($element->autolevel as $autolevel) {
            $level = (int) $autolevel['level'];

            // Check if this autolevel has optional spell slots
            if (isset($autolevel->slots)) {
                $isOptional = isset($autolevel->slots['optional'])
                    && (string) $autolevel->slots['optional'] === 'YES';

                if ($isOptional) {
                    $slotsString = (string) $autolevel->slots;
                    $slots = array_map('intval', explode(',', $slotsString));

                    $optionalSlots[] = [
                        'level' => $level,
                        'cantrips_known' => $slots[0] ?? 0,
                        'spell_slots_1st' => $slots[1] ?? 0,
                        'spell_slots_2nd' => $slots[2] ?? 0,
                        'spell_slots_3rd' => $slots[3] ?? 0,
                        'spell_slots_4th' => $slots[4] ?? 0,
                        'spell_slots_5th' => $slots[5] ?? 0,
                        'spell_slots_6th' => $slots[6] ?? 0,
                        'spell_slots_7th' => $slots[7] ?? 0,
                        'spell_slots_8th' => $slots[8] ?? 0,
                        'spell_slots_9th' => $slots[9] ?? 0,
                    ];
                }
            }

            // Collect ALL "Spells Known" counters (might be in separate autolevel blocks)
            foreach ($autolevel->counter as $counterElement) {
                if ((string) $counterElement->name === 'Spells Known') {
                    $spellsKnownByLevel[$level] = (int) $counterElement->value;
                    break;
                }
            }
        }

        // If no optional slots, return empty
        if (empty($optionalSlots)) {
            return [];
        }

        // Get spellcasting ability if defined (for optional spellcasters)
        if (isset($element->spellAbility)) {
            $spellcastingAbility = (string) $element->spellAbility;
        }

        // Second pass: Find "Spellcasting (SubclassName)" features to match slots to subclass
        $spellcastingSubclass = null;
        foreach ($element->autolevel as $autolevel) {
            foreach ($autolevel->feature as $featureElement) {
                $featureName = (string) $featureElement->name;

                // Pattern: "Spellcasting (Arcane Trickster)" or "Spellcasting (Eldritch Knight)"
                if (preg_match('/^Spellcasting\s*\((.+)\)$/', $featureName, $matches)) {
                    $spellcastingSubclass = trim($matches[1]);
                    break 2; // Break both loops
                }
            }
        }

        // If we found a spellcasting subclass, assign the optional slots to it
        if ($spellcastingSubclass !== null) {
            // Merge spells_known counters into progression
            foreach ($optionalSlots as &$progression) {
                if (isset($spellsKnownByLevel[$progression['level']])) {
                    $progression['spells_known'] = $spellsKnownByLevel[$progression['level']];
                }
            }
            unset($progression);

            return [
                $spellcastingSubclass => [
                    'spellcasting_ability' => $spellcastingAbility,
                    'spell_progression' => $optionalSlots,
                ],
            ];
        }

        // No spellcasting subclass found - slots remain unassigned
        return [];
    }

    /**
     * Check if this class has any non-optional spell slots.
     * Used to determine if spell progression applies to base class vs subclass.
     *
     * Example: Rogue has only optional slots (Arcane Trickster), Wizard has non-optional.
     */
    private function hasNonOptionalSpellSlots(SimpleXMLElement $element): bool
    {
        foreach ($element->autolevel as $autolevel) {
            if (isset($autolevel->slots)) {
                $isOptional = isset($autolevel->slots['optional'])
                    && (string) $autolevel->slots['optional'] === 'YES';

                if (! $isOptional) {
                    return true; // Found at least one non-optional slot progression
                }
            }
        }

        return false; // No spell slots, or all are optional (subclass-only)
    }
}
