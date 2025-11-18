<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemAbility extends Model
{
    use HasFactory;

    public $timestamps = false;

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
