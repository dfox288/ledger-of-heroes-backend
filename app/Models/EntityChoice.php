<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * EntityChoice - Unified polymorphic table for all character creation choices.
 *
 * Table: entity_choices
 * Used by: Race, Background, CharacterClass, Feat, ClassFeature, Item
 *
 * Consolidates choices from entity_languages, entity_spells, entity_proficiencies,
 * entity_modifiers, and entity_items into a single table.
 *
 * Choice types:
 * - language: Pick from available languages
 * - spell: Pick cantrips/spells from a list
 * - proficiency: Pick skills, tools, weapons, armor, saving throws
 * - ability_score: Pick ability scores for bonuses (e.g., Half-Elf +1 to two)
 * - equipment: Pick starting equipment options
 *
 * Target types indicate what the choice resolves to:
 * - NULL target = unrestricted (any from the category)
 * - Specific target_type + target_slug = restricted to that option
 */
class EntityChoice extends BaseModel
{
    /**
     * Enable timestamps for this model.
     */
    public $timestamps = true;

    protected $fillable = [
        'reference_type',
        'reference_id',
        'choice_type',
        'choice_group',
        'quantity',
        'constraint',
        'choice_option',
        'target_type',
        'target_slug',
        'spell_max_level',
        'spell_list_slug',
        'spell_school_slug',
        'proficiency_type',
        'constraints',
        'description',
        'level_granted',
        'is_required',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'quantity' => 'integer',
        'choice_option' => 'integer',
        'spell_max_level' => 'integer',
        'constraints' => 'array',
        'level_granted' => 'integer',
        'is_required' => 'boolean',
    ];

    /**
     * Valid choice types.
     */
    public const CHOICE_TYPES = [
        'language',
        'spell',
        'proficiency',
        'ability_score',
        'equipment',
    ];

    /**
     * Valid target types for slug resolution.
     */
    public const TARGET_TYPES = [
        'spell',
        'language',
        'skill',
        'item',
        'proficiency_type',
        'ability_score',
    ];

    /**
     * Polymorphic relationship to parent entity (Race, Background, Class, Feat, etc.)
     */
    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    /**
     * Scope to filter by choice type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('choice_type', $type);
    }

    /**
     * Scope to filter by reference.
     */
    public function scopeForReference($query, string $type, int $id)
    {
        return $query->where('reference_type', $type)
            ->where('reference_id', $id);
    }

    /**
     * Scope to get cantrip choices.
     */
    public function scopeCantrips($query)
    {
        return $query->where('choice_type', 'spell')
            ->where('spell_max_level', 0);
    }

    /**
     * Scope to get non-cantrip spell choices.
     */
    public function scopeSpells($query)
    {
        return $query->where('choice_type', 'spell')
            ->where(function ($q) {
                $q->whereNull('spell_max_level')
                    ->orWhere('spell_max_level', '>', 0);
            });
    }

    /**
     * Check if this is an unrestricted choice (any from category).
     */
    public function isUnrestricted(): bool
    {
        return $this->target_type === null && $this->target_slug === null;
    }

    /**
     * Get the constraint value from JSON or column.
     */
    public function getConstraintValue(string $key, mixed $default = null): mixed
    {
        return $this->constraints[$key] ?? $default;
    }
}
