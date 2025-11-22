<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonsterAction extends Model
{
    use HasFactory;

    public $timestamps = false;

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
