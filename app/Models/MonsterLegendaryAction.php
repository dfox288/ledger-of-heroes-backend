<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonsterLegendaryAction extends BaseModel
{
    protected $fillable = [
        'monster_id',
        'name',
        'description',
        'action_cost',
        'is_lair_action',
        'attack_data',
        'recharge',
        'sort_order',
    ];

    protected $casts = [
        'is_lair_action' => 'boolean',
    ];

    public function monster(): BelongsTo
    {
        return $this->belongsTo(Monster::class);
    }
}
