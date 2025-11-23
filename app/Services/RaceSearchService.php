<?php

namespace App\Services;

use App\DTOs\RaceSearchDTO;
use App\Models\Race;
use Illuminate\Database\Eloquent\Builder;

final class RaceSearchService
{
    /**
     * Default relationships to eager-load for both Scout and database queries
     */
    private const DEFAULT_RELATIONSHIPS = [
        'size',
        'sources.source',
        'proficiencies.skill',
        'traits.randomTables.entries',
        'modifiers.abilityScore',
        'conditions.condition',
        'spells.spell',
        'spells.abilityScore',
        'parent',
        'subraces',
        'languages.language',
        'tags',
    ];

    public function buildScoutQuery(RaceSearchDTO $dto): \Laravel\Scout\Builder
    {
        $search = Race::search($dto->searchQuery);

        if (isset($dto->filters['size'])) {
            $search->where('size_id', $dto->filters['size']);
        }

        return $search;
    }

    public function buildDatabaseQuery(RaceSearchDTO $dto): Builder
    {
        $query = Race::with(self::DEFAULT_RELATIONSHIPS);

        $this->applyFilters($query, $dto);
        $this->applySorting($query, $dto);

        return $query;
    }

    /**
     * Get default relationships for eager loading
     */
    public function getDefaultRelationships(): array
    {
        return self::DEFAULT_RELATIONSHIPS;
    }

    private function applyFilters(Builder $query, RaceSearchDTO $dto): void
    {
        if (isset($dto->filters['search'])) {
            $query->search($dto->filters['search']);
        }

        if (isset($dto->filters['size'])) {
            $sizeCode = strtoupper($dto->filters['size']);
            $query->whereHas('size', function ($q) use ($sizeCode) {
                $q->where('code', $sizeCode);
            });
        }

        if (isset($dto->filters['grants_proficiency'])) {
            $query->grantsProficiency($dto->filters['grants_proficiency']);
        }

        if (isset($dto->filters['grants_skill'])) {
            $query->grantsSkill($dto->filters['grants_skill']);
        }

        if (isset($dto->filters['grants_proficiency_type'])) {
            $query->grantsProficiencyType($dto->filters['grants_proficiency_type']);
        }

        if (isset($dto->filters['speaks_language'])) {
            $query->speaksLanguage($dto->filters['speaks_language']);
        }

        if (isset($dto->filters['language_choice_count'])) {
            $query->languageChoiceCount((int) $dto->filters['language_choice_count']);
        }

        if (isset($dto->filters['grants_languages']) && $dto->filters['grants_languages']) {
            $query->grantsLanguages();
        }

        // Spell filter (AND/OR logic) - Uses entity_spells polymorphic table
        if (isset($dto->filters['spells'])) {
            $spellSlugs = array_map('trim', explode(',', $dto->filters['spells']));
            $spellSlugs = array_map('strtolower', $spellSlugs); // Case-insensitive
            $operator = $dto->filters['spells_operator'] ?? 'AND';

            if ($operator === 'AND') {
                // Must have ALL spells (nested whereHas)
                foreach ($spellSlugs as $slug) {
                    $query->whereHas('entitySpells', function ($q) use ($slug) {
                        $q->whereRaw('LOWER(slug) = ?', [$slug]);
                    });
                }
            } else {
                // Must have AT LEAST ONE spell (single whereHas with whereIn)
                $query->whereHas('entitySpells', function ($q) use ($spellSlugs) {
                    $q->whereIn(\DB::raw('LOWER(slug)'), $spellSlugs);
                });
            }
        }

        // Spell level filter (races that know spells of specific level)
        if (isset($dto->filters['spell_level'])) {
            $query->whereHas('entitySpells', function ($q) use ($dto) {
                $q->where('level', $dto->filters['spell_level']);
            });
        }

        // Has innate spells filter
        if (isset($dto->filters['has_innate_spells']) && filter_var($dto->filters['has_innate_spells'], FILTER_VALIDATE_BOOLEAN)) {
            $query->has('entitySpells');
        }

        // Ability bonus filter (via modifiers relationship)
        if (isset($dto->filters['ability_bonus'])) {
            $abilityCode = strtoupper($dto->filters['ability_bonus']);

            $query->whereHas('modifiers', function ($q) use ($abilityCode) {
                $q->where('modifier_category', 'ability_score')
                    ->whereHas('abilityScore', function ($aq) use ($abilityCode) {
                        $aq->where('code', $abilityCode);
                    })
                    ->where('value', '>', 0); // Must be positive bonus
            });
        }

        // Min speed filter
        if (isset($dto->filters['min_speed'])) {
            $query->where('speed', '>=', (int) $dto->filters['min_speed']);
        }

        // Has darkvision filter (via traits)
        if (isset($dto->filters['has_darkvision'])) {
            $value = filter_var($dto->filters['has_darkvision'], FILTER_VALIDATE_BOOLEAN);

            if ($value) {
                $query->whereHas('traits', function ($q) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%darkvision%']);
                });
            }
        }
    }

    private function applySorting(Builder $query, RaceSearchDTO $dto): void
    {
        $query->orderBy($dto->sortBy, $dto->sortDirection);
    }
}
