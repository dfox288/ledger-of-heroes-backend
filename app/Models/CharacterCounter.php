<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks limited-use resource pools for a character.
 *
 * Counters are separate from features - a feature is an ability (Rage),
 * while a counter tracks the resource pool (Rage uses).
 *
 * Source types:
 * - class: Counter from class/subclass feature (e.g., Rage from Barbarian)
 * - feat: Counter from feat (e.g., Luck Points from Lucky)
 * - race: Counter from racial trait (e.g., Infernal Legacy from Tiefling)
 *
 * Reset timing:
 * - S = Short rest
 * - L = Long rest
 * - D = Dawn
 *
 * @property int $id
 * @property int $character_id
 * @property string $source_type
 * @property string $source_slug
 * @property string $counter_name
 * @property int|null $current_uses Null = full uses available
 * @property int $max_uses -1 = unlimited
 * @property string|null $reset_timing S/L/D
 * @property int|null $remaining Accessor for remaining uses
 */
class CharacterCounter extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_id',
        'source_type',
        'source_slug',
        'counter_name',
        'current_uses',
        'max_uses',
        'reset_timing',
    ];

    protected $casts = [
        'character_id' => 'integer',
        'current_uses' => 'integer',
        'max_uses' => 'integer',
    ];

    // Relationships

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    // Helper methods

    /**
     * Check if this counter has unlimited uses.
     */
    public function isUnlimited(): bool
    {
        return $this->max_uses === -1;
    }

    /**
     * Use one charge. Returns false if no uses remaining.
     */
    public function use(): bool
    {
        // Unlimited counters always succeed
        if ($this->isUnlimited()) {
            return true;
        }

        // Calculate current remaining
        $remaining = $this->current_uses ?? $this->max_uses;

        if ($remaining <= 0) {
            return false;
        }

        $this->update(['current_uses' => $remaining - 1]);

        return true;
    }

    /**
     * Reset counter to full (null current_uses).
     */
    public function reset(): void
    {
        $this->update(['current_uses' => null]);
    }

    /**
     * Get remaining uses (null = unlimited).
     */
    public function getRemainingAttribute(): ?int
    {
        if ($this->isUnlimited()) {
            return null;
        }

        return $this->current_uses ?? $this->max_uses;
    }
}
