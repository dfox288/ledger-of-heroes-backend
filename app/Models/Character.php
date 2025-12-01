<?php

namespace App\Models;

use App\Enums\AbilityScoreMethod;
use App\Enums\ItemTypeCode;
use App\Events\CharacterUpdated;
use App\Services\CharacterStatCalculator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Character extends Model
{
    use HasFactory;

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'updated' => CharacterUpdated::class,
    ];

    protected $fillable = [
        'user_id',
        'name',
        'level',
        'experience_points',
        'race_id',
        'class_id',
        'background_id',
        'strength',
        'dexterity',
        'constitution',
        'intelligence',
        'wisdom',
        'charisma',
        'ability_score_method',
        'max_hit_points',
        'current_hit_points',
        'temp_hit_points',
        'armor_class_override',
    ];

    protected $casts = [
        'level' => 'integer',
        'experience_points' => 'integer',
        'strength' => 'integer',
        'dexterity' => 'integer',
        'constitution' => 'integer',
        'intelligence' => 'integer',
        'wisdom' => 'integer',
        'charisma' => 'integer',
        'ability_score_method' => AbilityScoreMethod::class,
        'max_hit_points' => 'integer',
        'current_hit_points' => 'integer',
        'temp_hit_points' => 'integer',
        'armor_class_override' => 'integer',
    ];

    protected $appends = [
        'is_complete',
    ];

    /**
     * Ability score code to column name mapping.
     */
    public const ABILITY_SCORES = [
        'STR' => 'strength',
        'DEX' => 'dexterity',
        'CON' => 'constitution',
        'INT' => 'intelligence',
        'WIS' => 'wisdom',
        'CHA' => 'charisma',
    ];

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function race(): BelongsTo
    {
        return $this->belongsTo(Race::class);
    }

    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'class_id');
    }

    public function background(): BelongsTo
    {
        return $this->belongsTo(Background::class);
    }

    public function spells(): HasMany
    {
        return $this->hasMany(CharacterSpell::class);
    }

    public function proficiencies(): HasMany
    {
        return $this->hasMany(CharacterProficiency::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(CharacterFeature::class);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(CharacterEquipment::class);
    }

    // Computed Accessors

    /**
     * Check if character has all required fields set (wizard-style complete).
     */
    public function getIsCompleteAttribute(): bool
    {
        return $this->race_id !== null
            && $this->class_id !== null
            && $this->hasAllAbilityScores();
    }

    /**
     * Get validation status showing what's missing for completion.
     */
    public function getValidationStatusAttribute(): array
    {
        $missing = [];

        if ($this->race_id === null) {
            $missing[] = 'race';
        }

        if ($this->class_id === null) {
            $missing[] = 'class';
        }

        if (! $this->hasAllAbilityScores()) {
            $missing[] = 'ability_scores';
        }

        return [
            'is_complete' => empty($missing),
            'missing' => $missing,
        ];
    }

    /**
     * Get ability score by code (STR, DEX, etc.).
     */
    public function getAbilityScore(string $code): ?int
    {
        $column = self::ABILITY_SCORES[strtoupper($code)] ?? null;

        if ($column === null) {
            return null;
        }

        return $this->{$column};
    }

    /**
     * Check if all six ability scores are set.
     */
    public function hasAllAbilityScores(): bool
    {
        return $this->strength !== null
            && $this->dexterity !== null
            && $this->constitution !== null
            && $this->intelligence !== null
            && $this->wisdom !== null
            && $this->charisma !== null;
    }

    /**
     * Get all ability scores as an associative array.
     */
    public function getAbilityScoresArray(): array
    {
        return [
            'STR' => $this->strength,
            'DEX' => $this->dexterity,
            'CON' => $this->constitution,
            'INT' => $this->intelligence,
            'WIS' => $this->wisdom,
            'CHA' => $this->charisma,
        ];
    }

    // Equipment Helpers

    /**
     * Get equipped armor (Light, Medium, or Heavy).
     */
    public function equippedArmor(): ?CharacterEquipment
    {
        return $this->equipment()
            ->where('equipped', true)
            ->whereHas('item.itemType', function ($query) {
                $query->whereIn('code', ItemTypeCode::armorCodes());
            })
            ->with('item.itemType')
            ->first();
    }

    /**
     * Get equipped shield.
     */
    public function equippedShield(): ?CharacterEquipment
    {
        return $this->equipment()
            ->where('equipped', true)
            ->whereHas('item.itemType', function ($query) {
                $query->where('code', ItemTypeCode::SHIELD->value);
            })
            ->with('item.itemType')
            ->first();
    }

    /**
     * Calculate armor class from equipped items or use override.
     */
    public function getArmorClassAttribute(): int
    {
        // If override is set, use it
        if ($this->armor_class_override !== null) {
            return $this->armor_class_override;
        }

        return app(CharacterStatCalculator::class)->calculateArmorClass($this);
    }
}
