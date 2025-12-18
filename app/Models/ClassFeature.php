<?php

namespace App\Models;

use App\Enums\ActionCost;
use App\Enums\ResetTiming;
use App\Models\Concerns\HasEntityChoices;
use App\Models\Concerns\HasModifiers;
use App\Models\Concerns\HasProficiencies;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class ClassFeature extends BaseModel
{
    use HasEntityChoices, HasModifiers, HasProficiencies;

    /**
     * Classes where subclass spells are "always prepared" (don't count against limit).
     *
     * D&D Context:
     * - Cleric domain spells
     * - Druid circle spells
     * - Paladin oath spells
     * - Artificer subclass spells
     *
     * Warlock expanded spells are NOT always prepared (added to spell list options only).
     */
    public const ALWAYS_PREPARED_CLASSES = ['cleric', 'druid', 'paladin', 'artificer'];

    protected $table = 'class_features';

    protected $fillable = [
        'class_id',
        'level',
        'feature_name',
        'is_optional',
        'is_multiclass_only',
        'choice_group',
        'parent_feature_id',
        'description',
        'sort_order',
        'resets_on',
        'action_cost',
    ];

    protected $casts = [
        'class_id' => 'integer',
        'level' => 'integer',
        'is_optional' => 'boolean',
        'is_multiclass_only' => 'boolean',
        'parent_feature_id' => 'integer',
        'sort_order' => 'integer',
        'resets_on' => ResetTiming::class,
        'action_cost' => ActionCost::class,
    ];

    // Relationships
    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'class_id');
    }

    /**
     * Get the parent feature if this is a choice option.
     *
     * Example: "Fighting Style: Archery" has parent "Fighting Style".
     */
    public function parentFeature(): BelongsTo
    {
        return $this->belongsTo(ClassFeature::class, 'parent_feature_id');
    }

    /**
     * Get child features (choice options) under this parent feature.
     *
     * Example: "Fighting Style" has children "Fighting Style: Archery", etc.
     */
    public function childFeatures(): HasMany
    {
        return $this->hasMany(ClassFeature::class, 'parent_feature_id');
    }

    /**
     * Data tables associated with this feature.
     * Includes random rolls, damage tables, and reference tables from feature text.
     */
    public function dataTables(): MorphMany
    {
        return $this->morphMany(
            EntityDataTable::class,
            'reference',
            'reference_type',
            'reference_id'
        );
    }

    /**
     * Special tags for this class feature (fighting styles, unarmored defense, etc.).
     */
    public function specialTags(): HasMany
    {
        return $this->hasMany(ClassFeatureSpecialTag::class);
    }

    /**
     * Spells granted by this feature (domain spells, circle spells, expanded spells).
     *
     * Uses entity_spells polymorphic table. The level_requirement pivot field
     * indicates the class level at which each spell is gained.
     */
    public function spells(): MorphToMany
    {
        return $this->morphToMany(
            Spell::class,
            'reference',
            'entity_spells',
            'reference_id',
            'spell_id'
        )->withPivot([
            'level_requirement',
            'is_cantrip',
            'usage_limit',
        ]);
    }

    // Accessors

    /**
     * Check if this feature is a choice option (has a parent feature).
     *
     * D&D Context: Choice options like "Fighting Style: Archery" are picked
     * from a list, rather than being gained automatically.
     */
    public function getIsChoiceOptionAttribute(): bool
    {
        return $this->parent_feature_id !== null;
    }

    /**
     * Check if this feature is a variant choice that requires player selection.
     *
     * Variant features are mutually exclusive options within a subclass:
     * - Circle of the Land terrain variants (Arctic, Coast, Desert, etc.)
     * - Path of the Totem Warrior animal variants (Bear, Eagle, Wolf, etc.)
     *
     * Features with the same choice_group are mutually exclusive.
     */
    public function getIsVariantChoiceAttribute(): bool
    {
        return $this->choice_group !== null;
    }

    /**
     * Extract the variant name from the feature name.
     *
     * Expected format: "VariantName (Subclass Name)" where:
     * - VariantName is the terrain/totem/etc. being extracted
     * - Subclass Name is in parentheses at the end
     *
     * Examples:
     * - "Arctic (Circle of the Land)" → "arctic"
     * - "Bear (Path of the Totem Warrior)" → "bear"
     *
     * Edge cases handled:
     * - No space before paren: "Arctic(Circle)" → "arctic"
     * - Multiple parens: "Bear (Revised) (Totem)" → "bear (revised)" (extracts up to last paren group)
     *
     * @return string|null The lowercase variant name, or null if not a variant
     */
    public function getVariantNameAttribute(): ?string
    {
        if ($this->choice_group === null) {
            return null;
        }

        // Pattern: Extract everything before the LAST parenthetical group
        // Uses greedy .+ to match as much as possible before the final (...)
        if (preg_match('/^(.+)\s*\([^)]+\)$/', $this->feature_name, $matches)) {
            return strtolower(trim($matches[1]));
        }

        return null;
    }

    /**
     * Check if spells from this feature are always prepared.
     *
     * D&D Context:
     * - Cleric domain spells: Always prepared, don't count against limit
     * - Druid circle spells: Always prepared, don't count against limit
     * - Paladin oath spells: Always prepared, don't count against limit
     * - Warlock expanded spells: Added to spell list options, NOT auto-prepared
     *
     * Determined by the base class (parent of subclass).
     */
    public function getIsAlwaysPreparedAttribute(): bool
    {
        // Get the base class name (parent of subclass, or self if base class)
        $class = $this->characterClass;
        if (! $class) {
            return false;
        }

        // If this is a subclass, get the parent class name
        $baseClassName = $class->parent_class_id !== null && $class->parentClass
            ? strtolower($class->parentClass->name)
            : strtolower($class->name);

        return in_array($baseClassName, self::ALWAYS_PREPARED_CLASSES);
    }

    // Helper Methods

    /**
     * Check if this feature has child features (choice options).
     */
    public function hasChildren(): bool
    {
        return $this->childFeatures()->exists();
    }

    // Scopes

    /**
     * Scope to only top-level features (exclude child/choice options).
     *
     * Use this to get the main feature list without nested options.
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_feature_id');
    }
}
