<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterLanguage extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'character_id',
        'language_id',
        'source',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Relationships

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
