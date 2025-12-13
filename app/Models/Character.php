<?php

namespace App\Models;

use App\Enums\AbilityScoreMethod;
use App\Enums\ItemTypeCode;
use App\Events\CharacterUpdated;
use App\Services\CharacterChoiceService;
use App\Services\CharacterStatCalculator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'race_slug',
        'size_id',
        'background_slug',
        'equipment_mode',
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
        'hp_levels_resolved',
        'hp_calculation_method',
        'death_save_successes',
        'death_save_failures',
        'is_dead',
        'armor_class_override',
        'asi_choices_remaining',
        'hp_levels_resolved',
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
        'hp_levels_resolved' => 'array',
        'death_save_successes' => 'integer',
        'death_save_failures' => 'integer',
        'is_dead' => 'boolean',
        'armor_class_override' => 'integer',
        'asi_choices_remaining' => 'integer',
        'hp_levels_resolved' => 'array',
        'size_id' => 'integer',
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
        return $this->belongsTo(Race::class, 'race_slug', 'slug');
    }

    /**
     * Get the character's size (for races with size choice like Custom Lineage).
     *
     * Returns the explicitly chosen size if set, otherwise null.
     * Use the `size` accessor for the effective size (chosen or race default).
     */
    public function sizeChoice(): BelongsTo
    {
        return $this->belongsTo(Size::class, 'size_id');
    }

    public function background(): BelongsTo
    {
        return $this->belongsTo(Background::class, 'background_slug', 'slug');
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
     * Get parties this character belongs to.
     */
    public function parties(): BelongsToMany
    {
        return $this->belongsToMany(Party::class, 'party_characters')
            ->withPivot(['joined_at', 'display_order'])
            ->withTimestamps();
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

    /**
     * Get chosen racial ability score bonuses.
     */
    public function abilityScores(): HasMany
    {
        return $this->hasMany(CharacterAbilityScore::class);
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
        return $this->race_slug !== null
            && $this->characterClasses->isNotEmpty()
            && $this->hasAllAbilityScores()
            && $this->hasAllRequiredChoicesResolved();
    }

    /**
     * Check if all required pending choices have been resolved.
     *
     * Returns true early if the character has dangling references (race_slug
     * or class_slug pointing to non-existent entities), since the choice
     * system requires valid relationships to function.
     */
    public function hasAllRequiredChoicesResolved(): bool
    {
        // Guard: If race_slug is set but race relationship is null (dangling reference),
        // skip choice validation since choice handlers require valid entities
        if ($this->race_slug !== null && $this->race === null) {
            return true;
        }

        // Guard: If we have class pivots but any point to non-existent classes, skip
        foreach ($this->characterClasses as $classPivot) {
            if ($classPivot->characterClass === null) {
                return true;
            }
        }

        $choiceService = app(CharacterChoiceService::class);
        $summary = $choiceService->getSummary($this);

        return $summary['required_pending'] === 0;
    }

    /**
     * Get validation status showing what's missing for completion.
     */
    public function getValidationStatusAttribute(): array
    {
        $missing = [];

        if ($this->race_slug === null) {
            $missing[] = 'race';
        }

        if ($this->characterClasses->isEmpty()) {
            $missing[] = 'class';
        }

        if (! $this->hasAllAbilityScores()) {
            $missing[] = 'ability_scores';
        }

        if (! $this->hasAllRequiredChoicesResolved()) {
            $missing[] = 'pending_choices';
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

    // HP Tracking Methods

    /**
     * Check if HP has been resolved for a specific level.
     */
    public function hasResolvedHpForLevel(int $level): bool
    {
        return in_array($level, $this->hp_levels_resolved ?? [], true);
    }

    /**
     * Mark HP as resolved for a specific level.
     */
    public function markHpResolvedForLevel(int $level): void
    {
        $resolved = $this->hp_levels_resolved ?? [];
        if (! in_array($level, $resolved, true)) {
            $resolved[] = $level;
            sort($resolved);
            $this->hp_levels_resolved = $resolved;
            $this->save();
        }
    }

    /**
     * Get all levels that still need HP choices resolved.
     */
    public function getPendingHpLevels(): array
    {
        $totalLevel = $this->total_level;
        $resolved = $this->hp_levels_resolved ?? [];

        $pending = [];
        for ($level = 1; $level <= $totalLevel; $level++) {
            if (! in_array($level, $resolved, true)) {
                $pending[] = $level;
            }
        }

        return $pending;
    }

    /**
     * Check if this character uses calculated HP (vs manual).
     */
    public function usesCalculatedHp(): bool
    {
        return ($this->hp_calculation_method ?? 'calculated') === 'calculated';
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
     * Returns ability scores with fixed racial and subrace modifiers applied,
     * plus any chosen ability score bonuses (from character_ability_scores).
     *
     * @return array<string, int|null> Ability scores keyed by code (STR, DEX, etc.)
     */
    public function getFinalAbilityScoresArray(): array
    {
        $baseScores = $this->getAbilityScoresArray();

        // No race = return base scores
        if (! $this->race_slug) {
            return $baseScores;
        }

        // Load race with modifiers if not already loaded
        if (! $this->relationLoaded('race')) {
            $this->load('race.modifiers.abilityScore', 'race.parent.modifiers.abilityScore');
        }

        // Get fixed ability score bonuses from race
        $racialBonuses = $this->getRacialAbilityBonuses();

        // Get chosen ability score bonuses (from character_ability_scores)
        $chosenBonuses = $this->getChosenAbilityBonuses();

        // Apply all bonuses to base scores
        foreach ($baseScores as $code => $baseScore) {
            if ($baseScore !== null) {
                $total = ($racialBonuses[$code] ?? 0) + ($chosenBonuses[$code] ?? 0);
                if ($total > 0) {
                    $baseScores[$code] = $baseScore + $total;
                }
            }
        }

        return $baseScores;
    }

    /**
     * Get fixed racial ability score bonuses (includes parent race if subrace).
     *
     * Note: All entity_modifiers are now fixed (non-choice) by definition.
     * Choice-based modifiers were moved to entity_choices table.
     *
     * @return array<string, int> Bonuses keyed by ability code
     */
    private function getRacialAbilityBonuses(): array
    {
        $bonuses = [];

        if (! $this->race) {
            return $bonuses;
        }

        // Get modifiers from current race (all entity_modifiers are now fixed)
        $modifiers = $this->race->modifiers()
            ->where('modifier_category', 'ability_score')
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

    /**
     * Get chosen racial ability score bonuses (from character_ability_scores).
     *
     * @return array<string, int> Bonuses keyed by ability code
     */
    private function getChosenAbilityBonuses(): array
    {
        if (! $this->relationLoaded('abilityScores')) {
            $this->load('abilityScores');
        }

        $bonuses = [];
        foreach ($this->abilityScores as $choice) {
            $bonuses[$choice->ability_score_code] =
                ($bonuses[$choice->ability_score_code] ?? 0) + $choice->bonus;
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
     * Get the character's effective size.
     *
     * Priority:
     * 1. Character's chosen size (for races with size choice like Custom Lineage)
     * 2. Race's default size
     * 3. null if no race
     */
    public function getSizeAttribute(): ?string
    {
        // If character has explicitly chosen a size, use it
        if ($this->size_id !== null) {
            return $this->sizeChoice?->name;
        }

        // Otherwise, fall back to race's default size
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

    // Currency (derived from equipment)

    /**
     * Currency item slugs (PHB coins).
     */
    private const CURRENCY_SLUGS = [
        'pp' => 'phb:platinum-pp',
        'gp' => 'phb:gold-gp',
        'ep' => 'phb:electrum-ep',
        'sp' => 'phb:silver-sp',
        'cp' => 'phb:copper-cp',
    ];

    /**
     * Get character's currency from inventory items.
     *
     * Currency is derived from equipment items with specific slugs.
     * Each coin type quantity is summed from the character's inventory.
     *
     * @note When using in collection responses, eager-load 'equipment' to avoid N+1 queries
     *
     * @return array{pp: int, gp: int, ep: int, sp: int, cp: int}
     */
    public function getCurrencyAttribute(): array
    {
        // Load equipment if not already loaded
        if (! $this->relationLoaded('equipment')) {
            $this->load('equipment');
        }

        $currency = [
            'pp' => 0,
            'gp' => 0,
            'ep' => 0,
            'sp' => 0,
            'cp' => 0,
        ];

        foreach ($this->equipment as $item) {
            foreach (self::CURRENCY_SLUGS as $type => $slug) {
                if ($item->item_slug === $slug) {
                    $currency[$type] += $item->quantity;
                    break;
                }
            }
        }

        return $currency;
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
            ->format('webp')
            ->performOnCollections('portrait', 'token');

        $this->addMediaConversion('medium')
            ->width(300)
            ->height(300)
            ->format('webp')
            ->performOnCollections('portrait', 'token');
    }
}
