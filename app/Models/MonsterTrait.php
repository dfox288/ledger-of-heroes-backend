<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonsterTrait extends BaseModel
{
    protected $fillable = [
        'monster_id',
        'name',
        'description',
        'attack_data',
        'sort_order',
    ];

    public function monster(): BelongsTo
    {
        return $this->belongsTo(Monster::class);
    }
}
