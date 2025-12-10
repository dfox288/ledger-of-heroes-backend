<?php

namespace App\Services\Parsers\Concerns;

/**
 * Parses movement cost modifiers from description text.
 *
 * Handles patterns like:
 * - "Climbing doesn't cost you extra movement"
 * - "Swimming doesn't cost you extra movement"
 * - "When you are prone, standing up uses only 5 feet of your movement"
 * - "difficult terrain doesn't cost you extra movement"
 * - "running long jump or a running high jump after moving only 5 feet"
 */
trait ParsesMovementModifiers
{
    /**
     * Parse movement cost modifiers from description text.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseMovementModifiers(string $text): array
    {
        $modifiers = [];

        // Pattern 1: "Climbing/Swimming doesn't cost you extra movement"
        if (preg_match_all('/(climbing|swimming)\s+(?:doesn\'t|does not)\s+cost\s+(?:you\s+)?extra\s+movement/i', $text, $matches)) {
            foreach ($matches[1] as $activity) {
                $modifiers[] = [
                    'type' => 'movement_cost',
                    'activity' => strtolower($activity),
                    'cost' => 'normal', // No extra cost = normal movement rate
                    'condition' => null,
                ];
            }
        }

        // Pattern 2: "standing up uses only X feet of your movement"
        if (preg_match('/(?:when\s+you\s+are\s+prone,?\s+)?standing\s+up\s+uses\s+only\s+(\d+)\s+feet\s+of\s+your\s+movement/i', $text, $match)) {
            $modifiers[] = [
                'type' => 'movement_cost',
                'activity' => 'standing_from_prone',
                'cost' => (int) $match[1],
                'condition' => null,
            ];
        }

        // Pattern 3: "difficult terrain doesn't cost you extra movement" (with optional condition)
        if (preg_match('/(when\s+you\s+use\s+the\s+Dash\s+action,?\s+)?difficult\s+terrain\s+(?:doesn\'t|does not)\s+cost\s+(?:you\s+)?extra\s+movement/i', $text, $match)) {
            $condition = null;
            if (! empty($match[1])) {
                $condition = 'When you use the Dash action';
            }

            $modifiers[] = [
                'type' => 'movement_cost',
                'activity' => 'difficult_terrain',
                'cost' => 'normal',
                'condition' => $condition,
            ];
        }

        // Pattern 4: "running long jump or a running high jump after moving only X feet"
        if (preg_match('/running\s+(?:long\s+)?jump\s+(?:or\s+a\s+running\s+high\s+jump\s+)?after\s+moving\s+only\s+(\d+)\s+feet/i', $text, $match)) {
            $modifiers[] = [
                'type' => 'movement_cost',
                'activity' => 'running_jump',
                'cost' => (int) $match[1],
                'condition' => null,
            ];
        }

        return $modifiers;
    }
}
