<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Proficiency - Polymorphic pivot for fixed proficiency grants.
 *
 * Table: entity_proficiencies (custom table name for polymorphic consistency)
 * Used by: Background, CharacterClass, Feat, Item, Race
 *
 * Represents fixed proficiencies granted by entities (weapon, armor, skill, tool, saving throw).
 * Choice-based proficiency grants are stored in entity_choices table.
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
        'level',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'proficiency_type_id' => 'integer',
        'skill_id' => 'integer',
        'item_id' => 'integer',
        'ability_score_id' => 'integer',
        'grants' => 'boolean',
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
