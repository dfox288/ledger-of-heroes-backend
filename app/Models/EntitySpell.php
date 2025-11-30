<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EntitySpell extends BaseModel
{
    protected $fillable = [
        'reference_type',
        'reference_id',
        'spell_id',
        'ability_score_id',
        'level_requirement',
        'usage_limit',
        'is_cantrip',
        'is_choice',
        'choice_count',
        'choice_group',
        'max_level',
        'school_id',
        'class_id',
        'is_ritual_only',
    ];

    protected $casts = [
        'level_requirement' => 'integer',
        'is_cantrip' => 'boolean',
        'is_choice' => 'boolean',
        'choice_count' => 'integer',
        'max_level' => 'integer',
        'is_ritual_only' => 'boolean',
    ];

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    public function spell(): BelongsTo
    {
        return $this->belongsTo(Spell::class);
    }

    public function abilityScore(): BelongsTo
    {
        return $this->belongsTo(AbilityScore::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(SpellSchool::class, 'school_id');
    }

    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'class_id');
    }
}
