<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonsterAction extends BaseModel
{
    protected $fillable = [
        'monster_id',
        'action_type',
        'name',
        'description',
        'attack_data',
        'recharge',
        'sort_order',
    ];

    public function monster(): BelongsTo
    {
        return $this->belongsTo(Monster::class);
    }
}
