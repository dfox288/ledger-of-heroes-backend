<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterOptionalFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_id',
        'optional_feature_id',
        'class_id',
        'subclass_name',
        'level_acquired',
        'uses_remaining',
        'max_uses',
    ];

    protected $casts = [
        'character_id' => 'integer',
        'optional_feature_id' => 'integer',
        'class_id' => 'integer',
        'level_acquired' => 'integer',
        'uses_remaining' => 'integer',
        'max_uses' => 'integer',
    ];

    // Relationships

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function optionalFeature(): BelongsTo
    {
        return $this->belongsTo(OptionalFeature::class);
    }

    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'class_id');
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
