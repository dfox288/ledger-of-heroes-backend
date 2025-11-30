<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpellEffect extends BaseModel
{
    protected $fillable = [
        'spell_id',
        'effect_type',
        'description',
        'dice_formula',
        'base_value',
        'scaling_type',
        'min_character_level',
        'min_spell_slot',
        'scaling_increment',
        'projectile_count',
        'projectile_per_level',
        'projectile_name',
        'damage_type_id',
    ];

    protected $casts = [
        'min_character_level' => 'integer',
        'min_spell_slot' => 'integer',
        'projectile_count' => 'integer',
        'projectile_per_level' => 'integer',
    ];

    // Relationships
    public function spell(): BelongsTo
    {
        return $this->belongsTo(Spell::class);
    }

    public function damageType(): BelongsTo
    {
        return $this->belongsTo(DamageType::class);
    }
}
