<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterClassPivot extends Model
{
    use HasFactory;

    protected $table = 'character_classes';

    protected $fillable = [
        'character_id',
        'class_id',
        'subclass_id',
        'level',
        'is_primary',
        'order',
        'hit_dice_spent',
    ];

    protected $casts = [
        'character_id' => 'integer',
        'class_id' => 'integer',
        'subclass_id' => 'integer',
        'level' => 'integer',
        'is_primary' => 'boolean',
        'order' => 'integer',
        'hit_dice_spent' => 'integer',
    ];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'class_id');
    }

    public function subclass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'subclass_id');
    }

    public function getMaxHitDiceAttribute(): int
    {
        return $this->level;
    }

    public function getAvailableHitDiceAttribute(): int
    {
        return $this->level - $this->hit_dice_spent;
    }
}
