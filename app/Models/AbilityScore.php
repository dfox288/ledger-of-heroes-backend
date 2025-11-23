<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class AbilityScore extends BaseModel
{
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

    public function entitiesRequiringSave(): MorphToMany
    {
        return $this->morphedByMany(
            Spell::class,
            'reference',
            'entity_saving_throws',
            'ability_score_id',
            'reference_id'
        )
            ->withPivot('save_effect', 'is_initial_save')
            ->withTimestamps();
    }

    /**
     * Get all spells that require saving throws with this ability score.
     */
    public function spells(): MorphToMany
    {
        return $this->morphedByMany(
            Spell::class,
            'reference',
            'entity_saving_throws',
            'ability_score_id',
            'reference_id'
        )
            ->withPivot('save_effect', 'is_initial_save', 'save_modifier', 'dc')
            ->withTimestamps();
    }
}
