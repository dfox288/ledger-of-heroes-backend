<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Proficiency - Polymorphic pivot for proficiency grants.
 *
 * Table: entity_proficiencies (custom table name for polymorphic consistency)
 * Used by: Background, CharacterClass, Feat, Item, Race
 *
 * Represents proficiencies granted by entities (weapon, armor, skill, tool, saving throw).
 * Supports both fixed proficiencies and proficiency choices (is_choice=true with choice_group).
 */
class Proficiency extends BaseModel
{
    /**
     * Custom table name for polymorphic consistency with other entity_* tables.
     */
    protected $table = 'entity_proficiencies';

    protected $fillable = [
        'reference_type',
        'reference_id',
        'proficiency_type',
        'proficiency_subcategory',
        'proficiency_type_id',
        'skill_id',
        'item_id',
        'ability_score_id',
        'proficiency_name',
        'grants',
        'is_choice',
        'choice_group',
        'choice_option',
        'quantity',
        'level',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'proficiency_type_id' => 'integer',
        'skill_id' => 'integer',
        'item_id' => 'integer',
        'ability_score_id' => 'integer',
        'grants' => 'boolean',
        'is_choice' => 'boolean',
        'choice_option' => 'integer',
        'quantity' => 'integer',
        'level' => 'integer',
    ];

    // Polymorphic relationship
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // Relationships to lookup tables
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function abilityScore(): BelongsTo
    {
        return $this->belongsTo(AbilityScore::class);
    }

    public function proficiencyType(): BelongsTo
    {
        return $this->belongsTo(ProficiencyType::class);
    }
}
