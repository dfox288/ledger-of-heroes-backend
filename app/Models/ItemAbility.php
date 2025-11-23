<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemAbility extends BaseModel
{
    protected $fillable = [
        'item_id',
        'ability_type',
        'spell_id',
        'name',
        'description',
        'roll_formula',
        'charges_cost',
        'usage_limit',
        'save_dc',
        'attack_bonus',
        'sort_order',
    ];

    // Relationships

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function spell(): BelongsTo
    {
        return $this->belongsTo(Spell::class);
    }
}
