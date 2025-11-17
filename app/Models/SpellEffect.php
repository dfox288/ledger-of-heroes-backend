<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpellEffect extends Model
{
    public $timestamps = false;

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
    ];

    protected $casts = [
        'min_character_level' => 'integer',
        'min_spell_slot' => 'integer',
    ];

    // Relationships
    public function spell(): BelongsTo
    {
        return $this->belongsTo(Spell::class);
    }
}
