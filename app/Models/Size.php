<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Size extends Model
{
    use HasFactory;

    public $timestamps = false;

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
