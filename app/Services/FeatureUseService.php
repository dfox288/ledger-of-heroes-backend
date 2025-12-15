<?php

namespace App\Services;

use App\Enums\ResetTiming;
use App\Models\Character;
use App\Models\CharacterFeature;
use App\Models\ClassCounter;
use App\Models\ClassFeature;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FeatureUseService
{
    /**
     * Get all features with limited uses for a character.
     * Includes current uses, max uses, and reset timing.
     *
     * @return Collection<int, array{
     *     id: int,
     *     feature_name: string,
     *     feature_slug: string,
     *     source: string,
     *     uses_remaining: int,
     *     max_uses: int,
     *     resets_on: string|null
     * }>
     */
    public function getFeaturesWithUses(Character $character): Collection
    {
        return $character->features()
            ->whereNotNull('max_uses')
            ->with('feature')
            ->get()
            ->map(function (CharacterFeature $characterFeature) {
                $feature = $characterFeature->feature;
                $resetsOn = null;

                // Get resets_on from the underlying feature if it's a ClassFeature
                if ($feature instanceof ClassFeature && $feature->resets_on) {
                    $resetsOn = $feature->resets_on->value;
                }

                return [
                    'id' => $characterFeature->id,
                    'feature_name' => $feature?->feature_name ?? $feature?->name ?? 'Unknown',
                    'feature_slug' => $characterFeature->feature_slug,
                    'source' => $characterFeature->source,
                    'uses_remaining' => $characterFeature->uses_remaining,
                    'max_uses' => $characterFeature->max_uses,
                    'resets_on' => $resetsOn,
                ];
            });
    }

    /**
     * Get all counters for a character in the API response format.
     *
     * Returns counters from class features, subclass features, and feats.
     * Each counter includes current/max uses, reset timing, and source info.
     *
     * @return Collection<int, array{
     *     id: int,
     *     slug: string,
     *     name: string,
     *     current: int,
     *     max: int,
     *     reset_on: string|null,
     *     source: string,
     *     source_type: string,
     *     unlimited: bool
     * }>
     */
    public function getCountersForCharacter(Character $character): Collection
    {
        return $character->features()
            ->whereNotNull('max_uses')
            ->with(['feature', 'feature.characterClass'])
            ->get()
            ->map(function (CharacterFeature $characterFeature) {
                $feature = $characterFeature->feature;

                // Only ClassFeatures have counters (for now)
                if (! $feature instanceof ClassFeature) {
                    return null;
                }

                // Get reset timing
                $resetOn = $this->mapResetTiming($feature->resets_on);

                // Get source class name
                $className = $feature->characterClass?->name ?? 'Unknown';

                // Build slug: {source-prefix}:{class-slug}:{counter-name-slug}
                $classSlug = $feature->characterClass?->slug ?? 'unknown';
                $counterNameSlug = Str::slug($feature->feature_name);
                $slug = "{$classSlug}:{$counterNameSlug}";

                // Check for unlimited (-1)
                $isUnlimited = $characterFeature->max_uses === -1;

                return [
                    'id' => $characterFeature->id,
                    'slug' => $slug,
                    'name' => $feature->feature_name,
                    'current' => $characterFeature->uses_remaining,
                    'max' => $characterFeature->max_uses,
                    'reset_on' => $resetOn,
                    'source' => $className,
                    'source_type' => $characterFeature->source,
                    'unlimited' => $isUnlimited,
                ];
            })
            ->filter() // Remove nulls
            ->values();
    }

    /**
     * Map ResetTiming enum to API string format.
     */
    private function mapResetTiming(?ResetTiming $timing): ?string
    {
        if ($timing === null) {
            return null;
        }

        return match ($timing) {
            ResetTiming::SHORT_REST => 'short_rest',
            ResetTiming::LONG_REST => 'long_rest',
            ResetTiming::DAWN => 'dawn',
            default => $timing->value,
        };
    }

    /**
     * Use a feature (decrement uses_remaining).
     * Returns false if no uses remaining or feature not found.
     */
    public function useFeature(Character $character, int $characterFeatureId): bool
    {
        $characterFeature = $character->features()->find($characterFeatureId);

        if (! $characterFeature) {
            return false;
        }

        return $characterFeature->useFeature();
    }

    /**
     * Reset a specific feature's uses to max.
     */
    public function resetFeature(Character $character, int $characterFeatureId): void
    {
        $characterFeature = $character->features()->find($characterFeatureId);

        if ($characterFeature) {
            $characterFeature->resetUses();
        }
    }

    /**
     * Reset all features matching given recharge types.
     * Called by RestService during short/long rest.
     *
     * @return int Number of features reset
     */
    public function resetByRechargeType(Character $character, ResetTiming ...$types): int
    {
        $resetCount = 0;

        // Get all character features with limited uses
        $characterFeatures = $character->features()
            ->whereNotNull('max_uses')
            ->with('feature')
            ->get();

        foreach ($characterFeatures as $characterFeature) {
            $feature = $characterFeature->feature;

            // Only ClassFeatures have resets_on
            if (! $feature instanceof ClassFeature) {
                continue;
            }

            if (! $feature->resets_on) {
                continue;
            }

            // Check if this feature's reset timing matches any of the provided types
            if (in_array($feature->resets_on, $types, true)) {
                $characterFeature->resetUses();
                $resetCount++;
            }
        }

        return $resetCount;
    }

    /**
     * Initialize max_uses for a character feature based on class counters.
     * Called when feature is granted (CharacterFeatureService::createFeatureIfNotExists).
     */
    public function initializeUsesForFeature(CharacterFeature $characterFeature, int $classLevel): void
    {
        $feature = $characterFeature->feature;

        // Only ClassFeatures have counters
        if (! $feature instanceof ClassFeature) {
            return;
        }

        $counterValue = $this->getCounterValueForFeature($feature, $classLevel);

        if ($counterValue !== null) {
            $characterFeature->update([
                'max_uses' => $counterValue,
                'uses_remaining' => $counterValue,
            ]);
        }
    }

    /**
     * Recalculate max_uses for all features (on level up).
     * Some features scale with level (Ki, Rage, etc.)
     */
    public function recalculateMaxUses(Character $character): void
    {
        // Get class levels for each class the character has
        $classLevels = $character->characterClasses()
            ->with('characterClass')
            ->get()
            ->keyBy(fn ($pivot) => $pivot->characterClass?->id)
            ->map(fn ($pivot) => $pivot->level);

        $characterFeatures = $character->features()
            ->whereNotNull('max_uses')
            ->with('feature')
            ->get();

        foreach ($characterFeatures as $characterFeature) {
            $feature = $characterFeature->feature;

            if (! $feature instanceof ClassFeature) {
                continue;
            }

            $classLevel = $classLevels[$feature->class_id] ?? 1;
            $newMaxUses = $this->getCounterValueForFeature($feature, $classLevel);

            if ($newMaxUses !== null && $newMaxUses !== $characterFeature->max_uses) {
                $oldMaxUses = $characterFeature->max_uses;
                $usesRemaining = $characterFeature->uses_remaining;

                // Calculate the delta and add it to current uses
                // (e.g., if max went from 2 to 4, add 2 to remaining)
                $delta = $newMaxUses - $oldMaxUses;
                $newUsesRemaining = $usesRemaining + $delta;

                // Don't let uses exceed new max
                $newUsesRemaining = min($newUsesRemaining, $newMaxUses);

                $characterFeature->update([
                    'max_uses' => $newMaxUses,
                    'uses_remaining' => $newUsesRemaining,
                ]);
            }
        }
    }

    /**
     * Look up the counter value for a feature at a given level.
     * Returns null if no counter exists.
     */
    private function getCounterValueForFeature(ClassFeature $feature, int $level): ?int
    {
        // Find counter by matching feature_name to counter_name
        // Get the highest level counter <= character's class level
        return ClassCounter::where('class_id', $feature->class_id)
            ->where('counter_name', $feature->feature_name)
            ->where('level', '<=', $level)
            ->orderByDesc('level')
            ->value('counter_value');
    }
}
