<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * EntityLanguage - Polymorphic pivot for language grants.
 *
 * Table: entity_languages
 * Used by: Background, Race
 *
 * Represents languages granted by races or backgrounds.
 * Supports:
 * - Fixed languages (language_id set, is_choice=false)
 * - Unrestricted choices (language_id=null, is_choice=true)
 * - Restricted choices (language_id set, is_choice=true, choice_group/choice_option set)
 * - Conditional choices (condition_type='already_knows', condition_language_id set)
 *
 * For restricted choices, multiple rows share the same choice_group, each with a different
 * language_id and choice_option. The first row (choice_option=1) has the quantity.
 *
 * For conditional choices (e.g., "one other if you already speak Dwarvish"), the choice
 * only applies if the character already knows the condition_language from another source.
 */
class EntityLanguage extends BaseModel
{
    protected $fillable = [
        'reference_type',
        'reference_id',
        'language_id',
        'is_choice',
        'choice_group',
        'choice_option',
        'condition_type',
        'condition_language_id',
        'quantity',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'language_id' => 'integer',
        'is_choice' => 'boolean',
        'choice_option' => 'integer',
        'condition_language_id' => 'integer',
        'quantity' => 'integer',
    ];

    // Polymorphic relationship to parent entity (Race, Background, Class, etc.)
    public function entity(): MorphTo
    {
        return $this->morphTo(null, 'reference_type', 'reference_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * The language required for conditional choices (e.g., "if you already speak Dwarvish").
     */
    public function conditionLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'condition_language_id');
    }
}
