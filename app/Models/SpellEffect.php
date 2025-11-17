<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpellEffect extends Model
{
    use HasFactory;

    protected $fillable = [
        'spell_id',
        'effect_type',
        'dice_formula',
        'scaling_type',
        'scaling_trigger',
        'damage_type_id',
    ];

    protected $casts = [
        'scaling_trigger' => 'integer',
    ];

    public function spell(): BelongsTo
    {
        return $this->belongsTo(Spell::class);
    }

    public function damageType(): BelongsTo
    {
        return $this->belongsTo(DamageType::class);
    }
}
