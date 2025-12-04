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
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Character extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

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
        'experience_points',
        'race_id',
        'background_id',
        'strength',
        'dexterity',
        'constitution',
        'intelligence',
        'wisdom',
        'charisma',
        'alignment',
        'has_inspiration',
        'ability_score_method',
        'max_hit_points',
        'current_hit_points',
        'temp_hit_points',
        'armor_class_override',
        'asi_choices_remaining',
        'portrait_url',
    ];

    protected $casts = [
        'experience_points' => 'integer',
        'strength' => 'integer',
        'dexterity' => 'integer',
        'constitution' => 'integer',
        'intelligence' => 'integer',
        'wisdom' => 'integer',
        'charisma' => 'integer',
        'has_inspiration' => 'boolean',
        'ability_score_method' => AbilityScoreMethod::class,
        'max_hit_points' => 'integer',
        'current_hit_points' => 'integer',
        'temp_hit_points' => 'integer',
        'armor_class_override' => 'integer',
        'asi_choices_remaining' => 'integer',
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

    /**
     * Valid D&D 5e alignments.
     */
    public const ALIGNMENTS = [
        'Lawful Good',
        'Neutral Good',
        'Chaotic Good',
        'Lawful Neutral',
        'True Neutral',
        'Chaotic Neutral',
        'Lawful Evil',
        'Neutral Evil',
        'Chaotic Evil',
        'Unaligned',
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

    public function notes(): HasMany
    {
        return $this->hasMany(CharacterNote::class)->orderBy('category')->orderBy('sort_order');
    }

    /**
     * Get all classes this character has (for multiclass support).
     */
    public function characterClasses(): HasMany
    {
        return $this->hasMany(CharacterClassPivot::class)->orderBy('order');
    }

    // Computed Accessors

    /**
     * Get the primary class (first class taken).
     */
    public function getPrimaryClassAttribute(): ?CharacterClass
    {
        return $this->characterClasses->firstWhere('is_primary', true)?->characterClass;
    }

    /**
     * Get total level across all classes.
     */
    public function getTotalLevelAttribute(): int
    {
        return $this->characterClasses->sum('level') ?: 0;
    }

    /**
     * Check if character has multiple classes.
     */
    public function getIsMulticlassAttribute(): bool
    {
        return $this->characterClasses->count() > 1;
    }

    /**
     * Check if character has all required fields set (wizard-style complete).
     */
    public function getIsCompleteAttribute(): bool
    {
        return $this->race_id !== null
            && $this->characterClasses->isNotEmpty()
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

        if ($this->characterClasses->isEmpty()) {
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
     * Get equipped weapons.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, CharacterEquipment>
     */
    public function equippedWeapons(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->equipment()
            ->where('equipped', true)
            ->whereHas('item.itemType', function ($query) {
                $query->whereIn('code', ItemTypeCode::weaponCodes());
            })
            ->with(['item.itemType', 'item.properties'])
            ->get();
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

    // Race-Derived Attributes

    /**
     * Get walking speed from race.
     */
    public function getSpeedAttribute(): ?int
    {
        return $this->race?->speed;
    }

    /**
     * Get size name from race.
     */
    public function getSizeAttribute(): ?string
    {
        return $this->race?->size?->name;
    }

    /**
     * Get all movement speeds from race.
     *
     * @return array<string, int|null>|null
     */
    public function getSpeedsAttribute(): ?array
    {
        if ($this->race === null) {
            return null;
        }

        return [
            'walk' => $this->race->speed,
            'fly' => $this->race->fly_speed,
            'swim' => $this->race->swim_speed,
            'climb' => $this->race->climb_speed,
        ];
    }

    // Media Collections

    /**
     * Register media collections for character portraits and tokens.
     */
    public function registerMediaCollections(): void
    {
        // Mime type validation is handled by MediaUploadRequest
        // Collection only enforces single file constraint
        $this->addMediaCollection('portrait')
            ->singleFile();

        $this->addMediaCollection('token')
            ->singleFile();
    }

    /**
     * Register media conversions for thumbnails.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->performOnCollections('portrait', 'token');

        $this->addMediaConversion('medium')
            ->width(300)
            ->height(300)
            ->performOnCollections('portrait', 'token');
    }
}
