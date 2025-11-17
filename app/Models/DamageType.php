<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DamageType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
    ];

    public function spellEffects(): HasMany
    {
        return $this->hasMany(SpellEffect::class, 'damage_type_id');
    }
}
