<?php

namespace App\Services\Parsers\Concerns;

use SimpleXMLElement;

/**
 * Parses class resource counters (Ki, Rage, Bardic Inspiration, etc.) from XML.
 *
 * Handles both counters defined in XML and special cases that need to be
 * added programmatically (like Unlimited Rage at level 20).
 */
trait ParsesClassCounters
{
    /**
     * Parse counters (Ki, Rage, etc.) from autolevel elements.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseCounters(SimpleXMLElement $element): array
    {
        $counters = [];
        $className = (string) $element->name;

        // Iterate through all autolevel elements
        foreach ($element->autolevel as $autolevel) {
            $level = (int) $autolevel['level'];

            // Parse each counter within this autolevel
            foreach ($autolevel->counter as $counterElement) {
                $name = (string) $counterElement->name;

                // Skip "Spells Known" counters - they're handled in spell_progression
                if ($name === 'Spells Known') {
                    continue;
                }

                $value = (int) $counterElement->value;

                // Parse reset timing
                $resetTiming = null;
                if (isset($counterElement->reset)) {
                    $reset = (string) $counterElement->reset;
                    $resetTiming = match ($reset) {
                        'S' => 'short_rest',
                        'L' => 'long_rest',
                        default => null,
                    };
                }

                // Parse subclass if present
                $subclass = null;
                if (isset($counterElement->subclass)) {
                    $subclass = (string) $counterElement->subclass;
                }

                $counters[] = [
                    'level' => $level,
                    'name' => $name,
                    'value' => $value,
                    'reset_timing' => $resetTiming,
                    'subclass' => $subclass,
                ];
            }
        }

        // Add special case counters not in XML source
        $counters = $this->addSpecialCaseCounters($counters, $className);

        return $counters;
    }

    /**
     * Add special case counters that are missing from XML source data.
     *
     * Per PHB rules, some counter values at certain levels are well-defined
     * but not included in the XML source files.
     *
     * @param  array<int, array<string, mixed>>  $counters  Existing counters
     * @param  string  $className  The class name
     * @return array<int, array<string, mixed>> Counters with special cases added
     */
    private function addSpecialCaseCounters(array $counters, string $className): array
    {
        // Barbarian: Unlimited Rage at level 20 (PHB p.49)
        // The XML source stops at level 17 with 6 rages, but per PHB:
        // "At 20th level, your rage becomes unlimited."
        // We represent "Unlimited" as -1
        if ($className === 'Barbarian') {
            $hasRageCounter = collect($counters)->contains(fn ($c) => $c['name'] === 'Rage');
            $hasLevel20Rage = collect($counters)->contains(fn ($c) => $c['name'] === 'Rage' && $c['level'] === 20);

            if ($hasRageCounter && ! $hasLevel20Rage) {
                // Find the reset timing from existing Rage counters
                $existingRage = collect($counters)->first(fn ($c) => $c['name'] === 'Rage');
                $resetTiming = $existingRage['reset_timing'] ?? 'long_rest';

                $counters[] = [
                    'level' => 20,
                    'name' => 'Rage',
                    'value' => -1, // -1 represents "Unlimited"
                    'reset_timing' => $resetTiming,
                    'subclass' => null,
                ];
            }
        }

        // Bard: Bardic Inspiration (PHB p.53)
        // Uses per rest = CHA modifier (computed at runtime)
        // Levels 1-4: Resets on long rest
        // Level 5+: Resets on short rest (Font of Inspiration feature)
        // Value of 1 is a placeholder - actual uses computed from CHA at runtime
        if ($className === 'Bard') {
            $hasBardicInspiration = collect($counters)->contains(fn ($c) => $c['name'] === 'Bardic Inspiration');

            if (! $hasBardicInspiration) {
                for ($level = 1; $level <= 20; $level++) {
                    $counters[] = [
                        'level' => $level,
                        'name' => 'Bardic Inspiration',
                        'value' => 1, // Placeholder - uses = CHA mod at runtime
                        'reset_timing' => $level >= 5 ? 'short_rest' : 'long_rest',
                        'subclass' => null,
                    ];
                }
            }
        }

        return $counters;
    }
}
