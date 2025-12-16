<?php

namespace App\Models;

use App\Enums\LevelingMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property LevelingMode $leveling_mode How characters in this party gain levels
 */
class Party extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'leveling_mode',
    ];

    protected function casts(): array
    {
        return [
            'leveling_mode' => LevelingMode::class,
        ];
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'party_characters')
            ->withPivot(['joined_at', 'display_order'])
            ->withTimestamps();
    }

    public function encounterMonsters(): HasMany
    {
        return $this->hasMany(EncounterMonster::class);
    }

    public function encounterPresets(): HasMany
    {
        return $this->hasMany(EncounterPreset::class);
    }
}
