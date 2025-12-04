<?php

namespace App\Models;

use App\Enums\SpellSlotType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterSpellSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_id',
        'spell_level',
        'max_slots',
        'used_slots',
        'slot_type',
    ];

    protected $casts = [
        'character_id' => 'integer',
        'spell_level' => 'integer',
        'max_slots' => 'integer',
        'used_slots' => 'integer',
        'slot_type' => SpellSlotType::class,
    ];

    // Relationships

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    // Computed Attributes

    /**
     * Get remaining slots (max - used).
     */
    public function getAvailableAttribute(): int
    {
        return $this->max_slots - $this->used_slots;
    }

    /**
     * Check if any slots are available.
     */
    public function hasAvailable(): bool
    {
        return $this->available > 0;
    }

    // Helper Methods

    /**
     * Use a spell slot.
     *
     * @return bool True if slot was used, false if none available
     */
    public function useSlot(): bool
    {
        if ($this->used_slots >= $this->max_slots) {
            return false;
        }

        $this->increment('used_slots');

        return true;
    }

    /**
     * Reset used slots to zero.
     */
    public function reset(): void
    {
        $this->update(['used_slots' => 0]);
    }
}
