<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait HasLanguageScopes
 *
 * Provides query scopes for filtering models by language grants.
 * Used by: Race, Background
 */
trait HasLanguageScopes
{
    /**
     * Scope: Filter entities that grant a specific language
     *
     * Searches for non-choice language grants with matching language name.
     *
     * Usage: Race::speaksLanguage('elvish')->get()
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeSpeaksLanguage($query, string $languageName)
    {
        return $query->whereHas('languages', function ($q) use ($languageName) {
            $q->where('is_choice', false)
                ->whereHas('language', function ($langQuery) use ($languageName) {
                    $langQuery->where('name', 'LIKE', "%{$languageName}%");
                });
        });
    }

    /**
     * Scope: Filter entities with specific number of language choices
     *
     * Filters by count of choice-based language slots.
     *
     * Usage: Race::languageChoiceCount(2)->get()
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeLanguageChoiceCount($query, int $count)
    {
        return $query->whereHas('languages', function ($q) {
            $q->where('is_choice', true);
        }, '=', $count);
    }

    /**
     * Scope: Filter entities that grant any languages
     *
     * Returns entities that have at least one language relationship.
     *
     * Usage: Race::grantsLanguages()->get()
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeGrantsLanguages($query)
    {
        return $query->has('languages');
    }
}
