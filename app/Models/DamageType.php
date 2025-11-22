<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class DamageType extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
    ];

    // Relationships
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'damage_type_id');
    }

    public function spellEffects(): HasMany
    {
        return $this->hasMany(SpellEffect::class);
    }

    /**
     * Get spells that deal this damage type
     *
     * Uses HasManyThrough relationship via spell_effects table.
     * Includes distinct() to prevent duplicates when spell has multiple effects.
     */
    public function spells(): HasManyThrough
    {
        return $this->hasManyThrough(
            Spell::class,           // Final model
            SpellEffect::class,     // Intermediate model
            'damage_type_id',       // FK on spell_effects table
            'id',                   // FK on spells table
            'id',                   // Local key on damage_types table
            'spell_id'              // Local key on spell_effects table
        )->distinct();
    }
}
