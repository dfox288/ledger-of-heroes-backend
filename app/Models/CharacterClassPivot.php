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
        'class_slug',
        'subclass_slug',
        'subclass_choices',
        'level',
        'is_primary',
        'order',
        'hit_dice_spent',
    ];

    protected $casts = [
        'character_id' => 'integer',
        'subclass_choices' => 'array',
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
        return $this->belongsTo(CharacterClass::class, 'class_slug', 'slug');
    }

    public function subclass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'subclass_slug', 'slug');
    }

    public function getMaxHitDiceAttribute(): int
    {
        return $this->level;
    }

    public function getAvailableHitDiceAttribute(): int
    {
        return $this->level - $this->hit_dice_spent;
    }

    /**
     * Get a specific subclass variant choice.
     *
     * Used for subclasses with variant options like:
     * - Circle of the Land: terrain (arctic, coast, desert, etc.)
     * - Path of the Totem Warrior: totem_spirit, totem_aspect, totem_attunement
     *
     * @param  string  $choiceGroup  The choice group to retrieve (e.g., 'terrain')
     * @return string|null The selected variant (e.g., 'arctic') or null if not set
     */
    public function getSubclassChoice(string $choiceGroup): ?string
    {
        return $this->subclass_choices[$choiceGroup] ?? null;
    }

    /**
     * Set a specific subclass variant choice.
     *
     * @param  string  $choiceGroup  The choice group (e.g., 'terrain')
     * @param  string  $value  The selected variant (e.g., 'arctic')
     */
    public function setSubclassChoice(string $choiceGroup, string $value): void
    {
        $choices = $this->subclass_choices ?? [];
        $choices[$choiceGroup] = $value;
        $this->subclass_choices = $choices;
    }
}
