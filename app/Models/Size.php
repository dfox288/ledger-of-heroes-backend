<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Size extends BaseModel
{
    protected $fillable = [
        'code',
        'name',
    ];

    // Relationships
    public function races(): HasMany
    {
        return $this->hasMany(Race::class)
            ->orderBy('name');
    }

    public function monsters(): HasMany
    {
        return $this->hasMany(Monster::class)
            ->orderBy('name');
    }
}
