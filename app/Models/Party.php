<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Party extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'user_id',
    ];

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
