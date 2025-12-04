<?php

namespace App\Models;

use App\Models\Concerns\HasLimitedUses;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CharacterFeature extends Model
{
    use HasFactory;
    use HasLimitedUses;

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
}
