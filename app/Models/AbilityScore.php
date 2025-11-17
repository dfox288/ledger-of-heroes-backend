<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbilityScore extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
    ];

    // Relationships
    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class);
    }

    public function abilityScoreBonuses(): HasMany
    {
        return $this->hasMany(AbilityScoreBonus::class);
    }
}
