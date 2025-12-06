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

/**
 * @property int $total_level Total character level across all classes
 * @property bool $is_multiclass Whether character has multiple classes
 * @property bool $is_complete Whether character has all required fields
 * @property array{is_complete: bool, missing: array<string>} $validation_status Validation status with missing fields
 * @property int $armor_class Calculated armor class
 * @property int|null $speed Walking speed from race
 * @property array{walk: int|null, fly: int|null, swim: int|null, climb: int|null}|null $speeds All movement speeds
 * @property string|null $size Character size from race
 * @property CharacterClass|null $primary_class Primary class (first class taken)
 */
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

    /**
     * Get the route key for the model.
     *
     * Uses public_id as the primary route key, but resolveRouteBinding
     * also accepts numeric IDs for backwards compatibility.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Resolve the model for route binding.
     *
     * Supports both public_id (slug format) and numeric id for backwards compatibility.
     * Frontend should use public_id, tests can use either.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        // If it's a numeric value, look up by id
        if (is_numeric($value)) {
            return $this->where('id', $value)->first();
        }

        // Otherwise look up by public_id
        return $this->where('public_id', $value)->first();
    }

    protected $fillable = [
        'public_id',
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
        'death_save_successes',
        'death_save_failures',
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
        'death_save_successes' => 'integer',
        'death_save_failures' => 'integer',
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

    /**
     * Get spell slots for this character.
     */
    public function spellSlots(): HasMany
    {
        return $this->hasMany(CharacterSpellSlot::class);
    }

    /**
     * Get active conditions for this character.
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(CharacterCondition::class);
    }

    /**
     * Get feature selections for this character (invocations, maneuvers, metamagic, etc.)
     */
    public function featureSelections(): HasMany
    {
        return $this->hasMany(FeatureSelection::class);
    }

    /**
     * Get languages known by this character.
     */
    public function languages(): HasMany
    {
        return $this->hasMany(CharacterLanguage::class);
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
     * Get all ability scores as an associative array (base scores only).
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

    /**
     * Get final ability scores with racial bonuses applied.
     *
     * Returns ability scores with fixed racial and subrace modifiers applied.
     * Does NOT include choice-based modifiers (those require user selection).
     *
     * @return array<string, int|null> Ability scores keyed by code (STR, DEX, etc.)
     */
    public function getFinalAbilityScoresArray(): array
    {
        $baseScores = $this->getAbilityScoresArray();

        // No race = return base scores
        if (! $this->race_id) {
            return $baseScores;
        }

        // Load race with modifiers if not already loaded
        if (! $this->relationLoaded('race')) {
            $this->load('race.modifiers.abilityScore', 'race.parent.modifiers.abilityScore');
        }

        // Get fixed ability score bonuses from race
        $racialBonuses = $this->getRacialAbilityBonuses();

        // Apply bonuses to base scores
        foreach ($baseScores as $code => $baseScore) {
            if ($baseScore !== null && isset($racialBonuses[$code])) {
                $baseScores[$code] = $baseScore + $racialBonuses[$code];
            }
        }

        return $baseScores;
    }

    /**
     * Get fixed racial ability score bonuses (includes parent race if subrace).
     *
     * @return array<string, int> Bonuses keyed by ability code
     */
    private function getRacialAbilityBonuses(): array
    {
        $bonuses = [];

        if (! $this->race) {
            return $bonuses;
        }

        // Get modifiers from current race
        $modifiers = $this->race->modifiers()
            ->where('modifier_category', 'ability_score')
            ->where('is_choice', false)
            ->whereNotNull('ability_score_id')
            ->with('abilityScore')
            ->get();

        foreach ($modifiers as $modifier) {
            if ($modifier->abilityScore) {
                $code = $modifier->abilityScore->code;
                $bonuses[$code] = ($bonuses[$code] ?? 0) + (int) $modifier->value;
            }
        }

        // If this is a subrace, also get modifiers from parent race
        if ($this->race->parent_race_id && $this->race->parent) {
            $parentModifiers = $this->race->parent->modifiers()
                ->where('modifier_category', 'ability_score')
                ->where('is_choice', false)
                ->whereNotNull('ability_score_id')
                ->with('abilityScore')
                ->get();

            foreach ($parentModifiers as $modifier) {
                if ($modifier->abilityScore) {
                    $code = $modifier->abilityScore->code;
                    $bonuses[$code] = ($bonuses[$code] ?? 0) + (int) $modifier->value;
                }
            }
        }

        return $bonuses;
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
