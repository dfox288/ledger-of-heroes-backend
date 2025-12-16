<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterSpell extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'character_id',
        'spell_slug',
        'preparation_status',
        'source',
        'class_slug',
        'level_acquired',
    ];

    protected $casts = [
        'level_acquired' => 'integer',
        'created_at' => 'datetime',
    ];

    // Relationships

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function spell(): BelongsTo
    {
        return $this->belongsTo(Spell::class, 'spell_slug', 'slug');
    }

    /**
     * The class that grants this spell (for multiclass spellcasting).
     */
    public function grantingClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'class_slug', 'slug');
    }

    // Helper methods

    public function isPrepared(): bool
    {
        return in_array($this->preparation_status, ['prepared', 'always_prepared']);
    }

    public function isAlwaysPrepared(): bool
    {
        return $this->preparation_status === 'always_prepared';
    }
}
