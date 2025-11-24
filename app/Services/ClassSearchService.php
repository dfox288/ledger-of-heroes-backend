<?php

namespace App\Services;

use App\DTOs\ClassSearchDTO;
use App\Models\CharacterClass;
use Illuminate\Database\Eloquent\Builder;

final class ClassSearchService
{
    /**
     * Relationships for index/list endpoints (lightweight)
     */
    private const INDEX_RELATIONSHIPS = [
        'spellcastingAbility',
        'proficiencies.proficiencyType',
        'proficiencies.item',
        'traits',
        'sources.source',
        'features',
        'levelProgression',
        'counters',
        'subclasses.features',
        'subclasses.counters',
        'tags',
        'parentClass',
    ];

    /**
     * Relationships for show/detail endpoints (comprehensive)
     */
    private const SHOW_RELATIONSHIPS = [
        'spellcastingAbility',
        'parentClass.spellcastingAbility',
        'parentClass.proficiencies.proficiencyType',
        'parentClass.proficiencies.item',
        'parentClass.proficiencies.skill.abilityScore',
        'parentClass.proficiencies.abilityScore',
        'parentClass.traits.randomTables.entries',
        'parentClass.sources.source',
        'parentClass.features.randomTables.entries',
        'parentClass.levelProgression',
        'parentClass.counters',
        'parentClass.equipment.item',
        'parentClass.spells',
        'parentClass.tags',
        'subclasses',
        'proficiencies.proficiencyType',
        'proficiencies.item',
        'proficiencies.skill.abilityScore',
        'proficiencies.abilityScore',
        'traits.randomTables.entries',
        'sources.source',
        'features.randomTables.entries',
        'levelProgression',
        'counters',
        'equipment.item',
        'spells',
        'subclasses.features.randomTables.entries',
        'subclasses.counters',
        'tags',
    ];

    /**
     * Backward compatibility alias
     */
    private const DEFAULT_RELATIONSHIPS = self::INDEX_RELATIONSHIPS;

    public function buildScoutQuery(string $searchQuery): \Laravel\Scout\Builder
    {
        return CharacterClass::search($searchQuery);
    }

    public function buildDatabaseQuery(ClassSearchDTO $dto): Builder
    {
        $query = CharacterClass::with(self::INDEX_RELATIONSHIPS);

        $this->applyFilters($query, $dto);
        $this->applySorting($query, $dto);

        return $query;
    }

    /**
     * Get default relationships for eager loading (index endpoints)
     */
    public function getDefaultRelationships(): array
    {
        return self::INDEX_RELATIONSHIPS;
    }

    /**
     * Get relationships for index/list endpoints
     */
    public function getIndexRelationships(): array
    {
        return self::INDEX_RELATIONSHIPS;
    }

    /**
     * Get relationships for show/detail endpoints
     */
    public function getShowRelationships(): array
    {
        return self::SHOW_RELATIONSHIPS;
    }

    private function applyFilters(Builder $query, ClassSearchDTO $dto): void
    {
        if (isset($dto->filters['search'])) {
            $query->where('name', 'LIKE', '%'.$dto->filters['search'].'%');
        }

        if (isset($dto->filters['base_only']) && $dto->filters['base_only']) {
            $query->whereNull('parent_class_id');
        }

        if (isset($dto->filters['grants_proficiency'])) {
            $query->grantsProficiency($dto->filters['grants_proficiency']);
        }

        if (isset($dto->filters['grants_skill'])) {
            $query->grantsSkill($dto->filters['grants_skill']);
        }

        if (isset($dto->filters['grants_saving_throw'])) {
            $abilityName = $dto->filters['grants_saving_throw'];
            $query->whereHas('proficiencies', function ($q) use ($abilityName) {
                $q->where('proficiency_type', 'saving_throw')
                    ->whereHas('abilityScore', function ($abilityQuery) use ($abilityName) {
                        $abilityQuery->where('code', strtoupper($abilityName))
                            ->orWhere('name', 'LIKE', "%{$abilityName}%");
                    });
            });
        }

        // Spell filter (AND/OR logic)
        if (isset($dto->filters['spells'])) {
            $spellSlugs = array_map('trim', explode(',', $dto->filters['spells']));
            $operator = $dto->filters['spells_operator'] ?? 'AND';

            if ($operator === 'AND') {
                // Must have ALL spells (nested whereHas)
                foreach ($spellSlugs as $slug) {
                    $query->whereHas('spells', function ($q) use ($slug) {
                        $q->where('slug', strtolower($slug));
                    });
                }
            } else {
                // Must have AT LEAST ONE spell (single whereHas with whereIn)
                $spellSlugs = array_map('strtolower', $spellSlugs);
                $query->whereHas('spells', function ($q) use ($spellSlugs) {
                    $q->whereIn('slug', $spellSlugs);
                });
            }
        }

        // Spell level filter (classes that have spells of specific level)
        if (isset($dto->filters['spell_level'])) {
            $query->whereHas('spells', function ($q) use ($dto) {
                $q->where('level', $dto->filters['spell_level']);
            });
        }

        // Is spellcaster filter
        if (isset($dto->filters['is_spellcaster'])) {
            $value = filter_var($dto->filters['is_spellcaster'], FILTER_VALIDATE_BOOLEAN);

            if ($value) {
                // Has spellcasting ability
                $query->whereNotNull('spellcasting_ability_id');
            } else {
                // No spellcasting ability
                $query->whereNull('spellcasting_ability_id');
            }
        }

        // Hit die filter
        if (isset($dto->filters['hit_die'])) {
            $query->where('hit_die', $dto->filters['hit_die']);
        }

        // Max spell level filter (classes that have spells of this level)
        if (isset($dto->filters['max_spell_level'])) {
            $level = (int) $dto->filters['max_spell_level'];
            $query->whereHas('spells', function ($q) use ($level) {
                $q->where('level', $level);
            });
        }
    }

    private function applySorting(Builder $query, ClassSearchDTO $dto): void
    {
        $query->orderBy($dto->sortBy, $dto->sortDirection);
    }
}
