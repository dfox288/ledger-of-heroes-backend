<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait HasProficiencyScopes
 *
 * Provides query scopes for filtering models by proficiency grants.
 * Used by: CharacterClass, Race, Background, Feat
 */
trait HasProficiencyScopes
{
    /**
     * Scope: Filter by granted proficiency name
     *
     * Searches both proficiency_name field and related proficiency_type name.
     *
     * Usage: CharacterClass::grantsProficiency('longsword')->get()
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeGrantsProficiency($query, string $proficiencyName)
    {
        return $query->whereHas('proficiencies', function ($q) use ($proficiencyName) {
            $q->where('proficiency_name', 'LIKE', "%{$proficiencyName}%")
                ->orWhereHas('proficiencyType', function ($typeQuery) use ($proficiencyName) {
                    $typeQuery->where('name', 'LIKE', "%{$proficiencyName}%");
                });
        });
    }

    /**
     * Scope: Filter by granted skill proficiency
     *
     * Searches for skill-type proficiencies with matching skill name.
     *
     * Usage: CharacterClass::grantsSkill('insight')->get()
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeGrantsSkill($query, string $skillName)
    {
        return $query->whereHas('proficiencies', function ($q) use ($skillName) {
            $q->where('proficiency_type', 'skill')
                ->whereHas('skill', function ($skillQuery) use ($skillName) {
                    $skillQuery->where('name', 'LIKE', "%{$skillName}%");
                });
        });
    }

    /**
     * Scope: Filter by proficiency type category
     *
     * Searches proficiency type category or name (e.g., 'martial', 'armor', 'tools').
     *
     * Usage: CharacterClass::grantsProficiencyType('martial')->get()
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeGrantsProficiencyType($query, string $categoryOrName)
    {
        return $query->whereHas('proficiencies', function ($q) use ($categoryOrName) {
            $q->whereHas('proficiencyType', function ($typeQuery) use ($categoryOrName) {
                $typeQuery->where('category', 'LIKE', "%{$categoryOrName}%")
                    ->orWhere('name', 'LIKE', "%{$categoryOrName}%");
            });
        });
    }
}
