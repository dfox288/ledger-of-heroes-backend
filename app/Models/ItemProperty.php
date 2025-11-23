<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ItemProperty extends BaseModel
{
    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    // Relationships
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_property', 'property_id', 'item_id');
    }
}
