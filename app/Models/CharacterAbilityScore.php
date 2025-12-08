<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterAbilityScore extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'character_id',
        'ability_score_code',
        'bonus',
        'source',
        'modifier_id',
    ];

    protected $casts = [
        'bonus' => 'integer',
        'modifier_id' => 'integer',
        'created_at' => 'datetime',
    ];

    // Relationships

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function abilityScore(): BelongsTo
    {
        return $this->belongsTo(AbilityScore::class, 'ability_score_code', 'code');
    }
}
