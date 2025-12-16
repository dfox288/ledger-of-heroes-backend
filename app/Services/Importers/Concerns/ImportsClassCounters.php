<?php

namespace App\Services\Importers\Concerns;

use App\Models\CharacterClass;
use App\Models\EntityCounter;

/**
 * Trait for importing class resource counters (Ki, Rage, etc.).
 */
trait ImportsClassCounters
{
    /**
     * Import class counters (Second Wind, Ki, Rage, etc.).
     */
    protected function importCounters(CharacterClass $class, array $counters): void
    {
        foreach ($counters as $counterData) {
            // Skip subclass counters for now (will be handled in subclass import)
            if (! empty($counterData['subclass'])) {
                continue;
            }

            // Convert reset_timing back to single character for database
            $resetTiming = match ($counterData['reset_timing']) {
                'short_rest' => 'S',
                'long_rest' => 'L',
                default => null,
            };

            // Use updateOrCreate to prevent duplicates on re-import
            // Unique key: reference_type + reference_id + level + counter_name
            EntityCounter::updateOrCreate(
                [
                    'reference_type' => CharacterClass::class,
                    'reference_id' => $class->id,
                    'level' => $counterData['level'],
                    'counter_name' => $counterData['name'],
                ],
                [
                    'counter_value' => $counterData['value'],
                    'reset_timing' => $resetTiming,
                ]
            );
        }
    }
}
