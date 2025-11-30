<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CharacterFeature extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'character_id',
        'feature_type',
        'feature_id',
        'source',
        'level_acquired',
        'uses_remaining',
        'max_uses',
    ];

    protected $casts = [
        'feature_id' => 'integer',
        'level_acquired' => 'integer',
        'uses_remaining' => 'integer',
        'max_uses' => 'integer',
        'created_at' => 'datetime',
    ];

    // Relationships

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function feature(): MorphTo
    {
        return $this->morphTo();
    }

    // Helper methods

    public function hasLimitedUses(): bool
    {
        return $this->max_uses !== null;
    }

    public function hasUsesRemaining(): bool
    {
        return $this->uses_remaining === null || $this->uses_remaining > 0;
    }

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

    public function resetUses(): void
    {
        if ($this->hasLimitedUses()) {
            $this->update(['uses_remaining' => $this->max_uses]);
        }
    }
}
