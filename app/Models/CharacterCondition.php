<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_id',
        'condition_slug',
        'level',
        'source',
        'duration',
    ];

    protected $casts = [
        'level' => 'integer',
    ];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class, 'condition_slug', 'slug');
    }
}
