<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait HasLanguageScopes
 *
 * Provides query scopes for filtering models by language grants.
 * Used by: Race, Background
 *
 * Note: This trait expects the model to also use HasEntityChoices trait
 * which provides the languageChoices() relationship.
 */
trait HasLanguageScopes
{
    /**
     * Scope: Filter entities that grant a specific language
     *
     * Searches for fixed language grants (via entity_languages) with matching language name.
     *
     * Usage: Race::speaksLanguage('elvish')->get()
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeSpeaksLanguage($query, string $languageName)
    {
        return $query->whereHas('languages', function ($q) use ($languageName) {
            $q->whereHas('language', function ($langQuery) use ($languageName) {
                $langQuery->where('name', 'LIKE', "%{$languageName}%");
            });
        });
    }

    /**
     * Scope: Filter entities with specific number of language choices
     *
     * Filters by count of language choice records in entity_choices table.
     * Requires HasEntityChoices trait for languageChoices() relationship.
     *
     * Usage: Race::languageChoiceCount(2)->get()
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeLanguageChoiceCount($query, int $count)
    {
        return $query->whereHas('languageChoices', operator: '=', count: $count);
    }

    /**
     * Scope: Filter entities that grant any languages
     *
     * Returns entities that have at least one fixed language or language choice.
     *
     * Usage: Race::grantsLanguages()->get()
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeGrantsLanguages($query)
    {
        return $query->where(function ($q) {
            $q->has('languages')
                ->orHas('languageChoices');
        });
    }
}
