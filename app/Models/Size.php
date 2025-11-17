<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Size extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
    ];

    // Relationships
    public function races(): HasMany
    {
        return $this->hasMany(Race::class);
    }

    public function monsters(): HasMany
    {
        return $this->hasMany(Monster::class);
    }
}
