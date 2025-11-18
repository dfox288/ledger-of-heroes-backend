<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
