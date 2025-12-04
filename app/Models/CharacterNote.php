<?php

namespace App\Models;

use App\Enums\NoteCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_id',
        'category',
        'title',
        'content',
        'sort_order',
    ];

    protected $casts = [
        'category' => NoteCategory::class,
        'sort_order' => 'integer',
    ];

    // Relationships

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
