<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonsterLegendaryAction extends Model
{
    use HasFactory;

    public $timestamps = false;

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
