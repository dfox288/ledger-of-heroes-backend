<?php

namespace App\Models\Concerns;

/**
 * Trait for models that track limited-use features (e.g., per-rest abilities).
 *
 * Requires the model to have:
 * - max_uses: ?int - Maximum uses per rest (null = unlimited)
 * - uses_remaining: ?int - Current uses remaining
 *
 * Used by: CharacterFeature, FeatureSelection
 */
trait HasLimitedUses
{
    /**
     * Check if this feature has limited uses (max_uses is set).
     */
    public function hasLimitedUses(): bool
    {
        return $this->max_uses !== null;
    }

    /**
     * Check if uses are remaining (unlimited or uses_remaining > 0).
     */
    public function hasUsesRemaining(): bool
    {
        return $this->uses_remaining === null || $this->uses_remaining > 0;
    }

    /**
     * Consume one use. Returns false if no uses remaining.
     */
    public function useFeature(): bool
    {
        if (! $this->hasLimitedUses()) {
            return true;
        }

        if ($this->uses_remaining > 0) {
            $this->decrement('uses_remaining');

            return true;
        }

        return false;
    }

    /**
     * Reset uses to max_uses (for rest mechanics).
     */
    public function resetUses(): void
    {
        if ($this->hasLimitedUses()) {
            $this->update(['uses_remaining' => $this->max_uses]);
        }
    }
}
