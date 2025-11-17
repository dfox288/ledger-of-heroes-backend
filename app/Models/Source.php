<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    public $timestamps = false; // CRITICAL: No timestamps on static data

    protected $fillable = [
        'code',
        'name',
        'publisher',
        'publication_year',
        'edition',
    ];

    protected $casts = [
        'publication_year' => 'integer',
    ];

    // Relationships (will be used by entities)
    public function spells(): HasMany
    {
        return $this->hasMany(Spell::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function races(): HasMany
    {
        return $this->hasMany(Race::class);
    }

    public function backgrounds(): HasMany
    {
        return $this->hasMany(Background::class);
    }

    public function feats(): HasMany
    {
        return $this->hasMany(Feat::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassModel::class);
    }

    public function monsters(): HasMany
    {
        return $this->hasMany(Monster::class);
    }
}
