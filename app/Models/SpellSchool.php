<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpellSchool extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
    ];

    // Relationships
    public function spells(): HasMany
    {
        return $this->hasMany(Spell::class);
    }
}
