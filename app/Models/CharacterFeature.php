<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Tracks granted features for a character.
 *
 * Note: Limited-use tracking (max_uses, uses_remaining) has been moved
 * to the character_counters table. See CharacterCounter model.
 */
class CharacterFeature extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'character_id',
        'feature_type',
        'feature_id',
        'feature_slug',
        'source',
        'level_acquired',
    ];

    protected $casts = [
        'feature_id' => 'integer',
        'level_acquired' => 'integer',
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
}
