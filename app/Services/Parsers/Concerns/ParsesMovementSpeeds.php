<?php

namespace App\Services\Parsers\Concerns;

/**
 * Parses alternative movement speeds (fly, swim, climb) from trait descriptions.
 *
 * Handles patterns like:
 * - "flying speed of X feet"
 * - "swimming speed of X feet"
 * - "climbing speed of X feet"
 * - "swim speed of X feet" (shorthand)
 * - "flying speed equal to your walking speed"
 */
trait ParsesMovementSpeeds
{
    /**
     * Parse alternative movement speeds from traits.
     *
     * @param  array<int, array<string, mixed>>  $traits  Parsed trait arrays with 'description' key
     * @param  int  $walkingSpeed  The base walking speed for "equal to walking speed" patterns
     * @return array{fly_speed: int|null, swim_speed: int|null, climb_speed: int|null}
     */
    protected function parseMovementSpeedsFromTraits(array $traits, int $walkingSpeed): array
    {
        $speeds = [
            'fly_speed' => null,
            'swim_speed' => null,
            'climb_speed' => null,
        ];

        foreach ($traits as $trait) {
            $text = $trait['description'] ?? '';

            // Parse flying speed
            if ($speeds['fly_speed'] === null) {
                $speeds['fly_speed'] = $this->parseSpeedFromText($text, 'flying', $walkingSpeed)
                    ?? $this->parseSpeedFromText($text, 'fly', $walkingSpeed);
            }

            // Parse swimming speed
            if ($speeds['swim_speed'] === null) {
                $speeds['swim_speed'] = $this->parseSpeedFromText($text, 'swimming', $walkingSpeed)
                    ?? $this->parseSpeedFromText($text, 'swim', $walkingSpeed);
            }

            // Parse climbing speed
            if ($speeds['climb_speed'] === null) {
                $speeds['climb_speed'] = $this->parseSpeedFromText($text, 'climbing', $walkingSpeed)
                    ?? $this->parseSpeedFromText($text, 'climb', $walkingSpeed);
            }
        }

        return $speeds;
    }

    /**
     * Parse a specific movement speed type from text.
     *
     * @return int|null The speed value, or null if not found
     */
    private function parseSpeedFromText(string $text, string $speedType, int $walkingSpeed): ?int
    {
        // Pattern 1: "X speed of Y feet" (e.g., "flying speed of 50 feet", "swim speed of 30 feet")
        if (preg_match("/{$speedType}\s+speed\s+(?:of\s+)?(\d+)\s+feet/i", $text, $match)) {
            return (int) $match[1];
        }

        // Pattern 2: "X speed equal to your walking speed"
        if (preg_match("/{$speedType}\s+speed\s+equal\s+to\s+your\s+walking\s+speed/i", $text)) {
            return $walkingSpeed;
        }

        return null;
    }
}
