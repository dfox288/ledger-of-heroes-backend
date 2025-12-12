<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * EntitySpell - Polymorphic pivot for fixed innate/granted spell relationships.
 *
 * Table: entity_spells
 * Used by: Feat, Monster, Race, Item
 *
 * Represents fixed spells granted by entities (racial spells, feat spells, monster innate casting).
 * Choice-based spell grants are stored in entity_choices table.
 *
 * Note: CharacterClass uses a separate class_spells table for class spell lists.
 */
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
        'charges_cost_min',
        'charges_cost_max',
        'charges_cost_formula',
    ];

    protected $casts = [
        'level_requirement' => 'integer',
        'is_cantrip' => 'boolean',
        'charges_cost_min' => 'integer',
        'charges_cost_max' => 'integer',
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
}
