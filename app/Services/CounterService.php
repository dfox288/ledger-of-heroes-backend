<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterCounter;
use App\Models\EntityCounter;
use Illuminate\Support\Collection;

/**
 * Manages character resource counters (Rage uses, Ki Points, etc.).
 *
 * Counters are defined in entity_counters table and tracked per-character
 * in character_counters table. This service handles sync, use, and reset.
 */
class CounterService
{
    /**
     * Reset timing code to human-readable label.
     */
    private const RESET_LABELS = [
        'S' => 'short_rest',
        'L' => 'long_rest',
        'D' => 'dawn',
    ];

    /**
     * Sync counters for a character based on their current classes, feats, and race.
     *
     * Creates new counters and updates max_uses for existing ones.
     * Does not reset current_uses (preserves usage state).
     */
    public function syncCountersForCharacter(Character $character): void
    {
        $character->loadMissing('characterClasses.characterClass');

        // Collect all counter definitions that apply to this character
        $counterDefs = $this->getCounterDefinitionsForCharacter($character);

        foreach ($counterDefs as $def) {
            $this->syncCounter($character, $def);
        }

        // Remove counters for sources no longer on the character
        $this->removeOrphanedCounters($character, $counterDefs);
    }

    /**
     * Use a counter (decrement current_uses).
     *
     * @return bool True if use succeeded, false if no uses remaining
     */
    public function useCounter(Character $character, int $counterId): bool
    {
        $counter = CharacterCounter::where('id', $counterId)
            ->where('character_id', $character->id)
            ->first();

        if (! $counter) {
            return false;
        }

        return $counter->use();
    }

    /**
     * Reset counters matching given timings.
     *
     * @param  string  ...$timings  Reset timing codes (S, L, D)
     * @return int Number of counters reset
     */
    public function resetByTiming(Character $character, string ...$timings): int
    {
        $counters = CharacterCounter::where('character_id', $character->id)
            ->whereIn('reset_timing', $timings)
            ->whereNotNull('current_uses') // Only reset if not already full
            ->get();

        foreach ($counters as $counter) {
            $counter->reset();
        }

        return $counters->count();
    }

    /**
     * Get all counters for a character, formatted for API response.
     *
     * @return Collection<int, array>
     */
    public function getCountersForCharacter(Character $character): Collection
    {
        return CharacterCounter::where('character_id', $character->id)
            ->get()
            ->map(fn (CharacterCounter $counter) => [
                'id' => $counter->id,
                'name' => $counter->counter_name,
                'current' => $counter->remaining,
                'max' => $counter->max_uses,
                'reset_on' => self::RESET_LABELS[$counter->reset_timing] ?? null,
                'source_type' => $counter->source_type,
                'source_slug' => $counter->source_slug,
                'unlimited' => $counter->isUnlimited(),
            ]);
    }

    /**
     * Get all counter definitions that apply to a character.
     *
     * @return Collection<int, array{source_type: string, source_slug: string, counter_name: string, max_uses: int, reset_timing: string|null}>
     */
    private function getCounterDefinitionsForCharacter(Character $character): Collection
    {
        $definitions = collect();

        // Class counters
        foreach ($character->characterClasses as $classPivot) {
            $class = $classPivot->characterClass;
            if (! $class) {
                continue;
            }

            $classCounters = $this->getCounterDefsForEntity(
                CharacterClass::class,
                $class->id,
                $classPivot->level
            );

            foreach ($classCounters as $counterDef) {
                $definitions->push([
                    'source_type' => 'class',
                    'source_slug' => $class->slug,
                    'counter_name' => $counterDef['counter_name'],
                    'max_uses' => $counterDef['counter_value'],
                    'reset_timing' => $counterDef['reset_timing'],
                ]);
            }
        }

        // Future: Add feat and race counters here

        return $definitions;
    }

    /**
     * Get counter definitions for a specific entity up to a given level.
     *
     * Returns the highest applicable value for each counter name.
     *
     * @return Collection<int, array{counter_name: string, counter_value: int, reset_timing: string|null}>
     */
    private function getCounterDefsForEntity(string $entityType, int $entityId, int $level): Collection
    {
        return EntityCounter::where('reference_type', $entityType)
            ->where('reference_id', $entityId)
            ->where('level', '<=', $level)
            ->get()
            ->groupBy('counter_name')
            ->map(function ($counters) {
                // Get the highest level definition for this counter name
                return $counters->sortByDesc('level')->first();
            })
            ->values()
            ->map(fn ($counter) => [
                'counter_name' => $counter->counter_name,
                'counter_value' => $counter->counter_value,
                'reset_timing' => $counter->reset_timing,
            ]);
    }

    /**
     * Sync a single counter definition to the character.
     *
     * If max_uses decreases (e.g., level-down), current_uses is capped
     * to the new max to prevent invalid state.
     */
    private function syncCounter(Character $character, array $def): void
    {
        $counter = CharacterCounter::updateOrCreate(
            [
                'character_id' => $character->id,
                'source_type' => $def['source_type'],
                'source_slug' => $def['source_slug'],
                'counter_name' => $def['counter_name'],
            ],
            [
                'max_uses' => $def['max_uses'],
                'reset_timing' => $def['reset_timing'],
            ]
        );

        // Cap current_uses if it now exceeds max_uses (e.g., after level-down)
        if ($counter->current_uses !== null
            && $def['max_uses'] > 0
            && $counter->current_uses > $def['max_uses']) {
            $counter->update(['current_uses' => $def['max_uses']]);
        }
    }

    /**
     * Remove counters for sources no longer on the character.
     */
    private function removeOrphanedCounters(Character $character, Collection $validDefs): void
    {
        $validKeys = $validDefs->map(fn ($def) => implode('|', [
            $def['source_type'],
            $def['source_slug'],
            $def['counter_name'],
        ]))->toArray();

        CharacterCounter::where('character_id', $character->id)
            ->get()
            ->each(function (CharacterCounter $counter) use ($validKeys) {
                $key = implode('|', [
                    $counter->source_type,
                    $counter->source_slug,
                    $counter->counter_name,
                ]);

                if (! in_array($key, $validKeys)) {
                    $counter->delete();
                }
            });
    }
}
