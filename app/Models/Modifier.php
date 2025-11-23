<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Modifier extends BaseModel
{
    protected $table = 'entity_modifiers';

    protected $fillable = [
        'reference_type',
        'reference_id',
        'modifier_category',
        'ability_score_id',
        'skill_id',
        'damage_type_id',
        'value',
        'condition',
        'is_choice',
        'choice_count',
        'choice_constraint',
        'level',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'ability_score_id' => 'integer',
        'skill_id' => 'integer',
        'damage_type_id' => 'integer',
        'is_choice' => 'boolean',
        'choice_count' => 'integer',
        'level' => 'integer',
    ];

    // Polymorphic relationship
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // Relationships to lookup tables
    public function abilityScore(): BelongsTo
    {
        return $this->belongsTo(AbilityScore::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function damageType(): BelongsTo
    {
        return $this->belongsTo(DamageType::class);
    }

    /**
     * Accessor for category (alias for modifier_category).
     */
    public function getCategoryAttribute(): ?string
    {
        return $this->modifier_category;
    }
}
