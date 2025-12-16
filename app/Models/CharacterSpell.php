<?php

namespace App\Models;

use App\Enums\ResetTiming;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks spells known/prepared by a character.
 *
 * For innate spellcasting from racial traits (e.g., Drow Magic), spells
 * can have limited uses tracked via max_uses, uses_remaining, and resets_on.
 */
class CharacterSpell extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'character_id',
        'spell_slug',
        'preparation_status',
        'source',
        'max_uses',
        'uses_remaining',
        'resets_on',
        'class_slug',
        'level_acquired',
    ];

    protected $casts = [
        'level_acquired' => 'integer',
        'max_uses' => 'integer',
        'uses_remaining' => 'integer',
        'resets_on' => ResetTiming::class,
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

    /**
     * Check if this spell has limited uses (innate spellcasting).
     */
    public function hasLimitedUses(): bool
    {
        return $this->max_uses !== null;
    }

    /**
     * Check if this spell has uses remaining.
     */
    public function hasUsesRemaining(): bool
    {
        return $this->uses_remaining === null || $this->uses_remaining > 0;
    }
}
