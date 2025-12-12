<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class CreatureType extends BaseModel
{
    protected $fillable = [
        'slug',
        'name',
        'typically_immune_to_poison',
        'typically_immune_to_charmed',
        'typically_immune_to_frightened',
        'typically_immune_to_exhaustion',
        'requires_sustenance',
        'requires_sleep',
        'description',
    ];

    protected $casts = [
        'typically_immune_to_poison' => 'boolean',
        'typically_immune_to_charmed' => 'boolean',
        'typically_immune_to_frightened' => 'boolean',
        'typically_immune_to_exhaustion' => 'boolean',
        'requires_sustenance' => 'boolean',
        'requires_sleep' => 'boolean',
    ];

    public function monsters(): HasMany
    {
        return $this->hasMany(Monster::class);
    }
}
