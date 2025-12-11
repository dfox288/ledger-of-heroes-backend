<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Skill extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'ability_score_id',
    ];

    protected $casts = [
        'ability_score_id' => 'integer',
    ];

    // Relationships
    public function abilityScore(): BelongsTo
    {
        return $this->belongsTo(AbilityScore::class);
    }

    public function skillProficiencies(): HasMany
    {
        return $this->hasMany(SkillProficiency::class);
    }
}
