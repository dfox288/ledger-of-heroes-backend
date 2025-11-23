<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class SpellSchool extends BaseModel
{
    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    // Relationships
    public function spells(): HasMany
    {
        return $this->hasMany(Spell::class);
    }
}
