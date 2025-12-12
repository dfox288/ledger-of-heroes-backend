<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Modifier - Polymorphic pivot for fixed stat modifiers.
 *
 * Table: entity_modifiers (custom table name for polymorphic consistency)
 * Used by: CharacterClass, Feat, Item, Monster, Race
 *
 * Represents fixed stat modifications granted by entities (ability score bonuses, skill bonuses, etc.).
 * modifier_category defines the type: ability_score, skill, damage_resistance, ac_magic, etc.
 * Choice-based modifiers (like Half-Elf's +1 to two abilities) are stored in entity_choices table.
 */
class Modifier extends BaseModel
{
    /**
     * Custom table name for polymorphic consistency with other entity_* tables.
     */
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
        'level',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'ability_score_id' => 'integer',
        'skill_id' => 'integer',
        'damage_type_id' => 'integer',
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
