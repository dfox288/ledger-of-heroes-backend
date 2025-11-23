<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemType extends BaseModel
{
    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    // Relationships
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
