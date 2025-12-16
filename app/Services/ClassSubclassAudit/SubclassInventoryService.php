<?php

declare(strict_types=1);

namespace App\Services\ClassSubclassAudit;

use App\Models\CharacterClass;
use App\Models\ClassCounter;
use App\Models\ClassFeature;
use Illuminate\Support\Collection;

/**
 * Service to inventory what data exists for each class/subclass.
 *
 * Queries the database for features, spells, proficiencies, and counters
 * associated with each subclass. Used by audit:class-subclass-matrix command.
 */
class SubclassInventoryService
{
    /**
     * Get all base classes (excluding sidekicks).
     *
     * @return Collection<CharacterClass>
     */
    public function getBaseClasses(): Collection
    {
        return CharacterClass::whereNull('parent_class_id')
            ->whereNotIn('slug', [
                'tce:expert-sidekick',
                'tce:spellcaster-sidekick',
                'tce:warrior-sidekick',
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all subclasses for a given base class.
     *
     * @return Collection<CharacterClass>
     */
    public function getSubclasses(CharacterClass $baseClass): Collection
    {
        return CharacterClass::where('parent_class_id', $baseClass->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get full inventory for a single subclass.
     *
     * @return array{
     *   slug: string,
     *   name: string,
     *   parent_class: string,
     *   features: array{count: int, levels: array<int>, items: array},
     *   bonus_spells: array{count: int, by_level: array<int, array>, always_prepared: bool},
     *   proficiencies: array{count: int, items: array},
     *   counters: array<string, array<int, int>>
     * }
     */
    public function getSubclassInventory(CharacterClass $subclass): array
    {
        return [
            'slug' => $subclass->slug,
            'name' => $subclass->name,
            'parent_class' => $subclass->parentClass?->slug ?? 'unknown',
            'features' => $this->getFeatureInventory($subclass),
            'bonus_spells' => $this->getBonusSpellInventory($subclass),
            'proficiencies' => $this->getProficiencyInventory($subclass),
            'counters' => $this->getCounterInventory($subclass),
        ];
    }

    /**
     * Get feature inventory for a subclass.
     *
     * @return array{count: int, levels: array<int>, items: array}
     */
    private function getFeatureInventory(CharacterClass $subclass): array
    {
        $features = ClassFeature::where('class_id', $subclass->id)
            ->whereNull('parent_feature_id') // Top-level only
            ->orderBy('level')
            ->get(['id', 'feature_name', 'level', 'is_optional']);

        $levels = $features->pluck('level')->unique()->sort()->values()->all();

        return [
            'count' => $features->count(),
            'levels' => $levels,
            'items' => $features->map(fn ($f) => [
                'name' => $f->feature_name,
                'level' => $f->level,
                'is_optional' => $f->is_optional,
            ])->all(),
        ];
    }

    /**
     * Get bonus spell inventory for a subclass.
     *
     * Queries entity_spells via ClassFeature relationships to find
     * domain/circle/oath spells granted by subclass features.
     *
     * @return array{count: int, by_level: array<int, array>, always_prepared: bool, features_with_spells: array}
     */
    private function getBonusSpellInventory(CharacterClass $subclass): array
    {
        // Get all features for this subclass that have spells
        $features = ClassFeature::where('class_id', $subclass->id)
            ->whereNull('parent_feature_id')
            ->with(['spells', 'characterClass.parentClass'])
            ->get();

        $byLevel = [];
        $totalCount = 0;
        $alwaysPrepared = false;
        $featuresWithSpells = [];

        foreach ($features as $feature) {
            if ($feature->spells->isEmpty()) {
                continue;
            }

            $alwaysPrepared = $feature->is_always_prepared;
            $featuresWithSpells[] = $feature->feature_name;

            foreach ($feature->spells as $spell) {
                $levelReq = $spell->pivot->level_requirement ?? $feature->level;

                if (! isset($byLevel[$levelReq])) {
                    $byLevel[$levelReq] = [];
                }

                $byLevel[$levelReq][] = [
                    'slug' => $spell->slug,
                    'name' => $spell->name,
                    'spell_level' => $spell->level,
                    'is_cantrip' => $spell->pivot->is_cantrip ?? false,
                ];

                $totalCount++;
            }
        }

        ksort($byLevel);

        return [
            'count' => $totalCount,
            'by_level' => $byLevel,
            'always_prepared' => $alwaysPrepared,
            'features_with_spells' => $featuresWithSpells,
        ];
    }

    /**
     * Get proficiency inventory for a subclass.
     *
     * Queries entity_proficiencies for armor, weapon, tool, skill proficiencies
     * granted directly by the subclass (not via features).
     *
     * @return array{count: int, items: array, by_type: array<string, array>}
     */
    private function getProficiencyInventory(CharacterClass $subclass): array
    {
        // Direct proficiencies on the subclass
        $proficiencies = $subclass->proficiencies()
            ->with('proficiencyType')
            ->get();

        $byType = [];
        $items = [];

        foreach ($proficiencies as $prof) {
            $type = $prof->proficiency_type ?? $prof->proficiencyType?->name ?? 'unknown';

            if (! isset($byType[$type])) {
                $byType[$type] = [];
            }

            $item = [
                'name' => $prof->proficiency_name,
                'type' => $type,
            ];

            $byType[$type][] = $prof->proficiency_name;
            $items[] = $item;
        }

        // Also check proficiencies on subclass features
        $featureProficiencies = [];
        $features = ClassFeature::where('class_id', $subclass->id)
            ->whereNull('parent_feature_id')
            ->with('proficiencies.proficiencyType')
            ->get();

        foreach ($features as $feature) {
            foreach ($feature->proficiencies as $prof) {
                $type = $prof->proficiency_type ?? $prof->proficiencyType?->name ?? 'unknown';
                $featureProficiencies[] = [
                    'name' => $prof->proficiency_name,
                    'type' => $type,
                    'from_feature' => $feature->feature_name,
                    'level' => $feature->level,
                ];
            }
        }

        return [
            'count' => count($items),
            'items' => $items,
            'by_type' => $byType,
            'from_features' => $featureProficiencies,
        ];
    }

    /**
     * Get counter inventory for a subclass.
     *
     * Returns counters like "Maneuvers Known" with their values per level.
     *
     * @return array<string, array<int, int>>
     */
    private function getCounterInventory(CharacterClass $subclass): array
    {
        $counters = ClassCounter::where('class_id', $subclass->id)
            ->orderBy('counter_name')
            ->orderBy('level')
            ->get();

        $result = [];

        foreach ($counters as $counter) {
            $name = $counter->counter_name;

            if (! isset($result[$name])) {
                $result[$name] = [];
            }

            $result[$name][$counter->level] = $counter->counter_value;
        }

        return $result;
    }

    /**
     * Get full inventory for all classes and subclasses.
     *
     * @return array{
     *   generated_at: string,
     *   summary: array{base_classes: int, subclasses: int, issues: int},
     *   classes: array
     * }
     */
    public function getFullInventory(): array
    {
        $baseClasses = $this->getBaseClasses();
        $classes = [];
        $totalSubclasses = 0;
        $issues = 0;

        foreach ($baseClasses as $baseClass) {
            $subclasses = $this->getSubclasses($baseClass);
            $subclassData = [];

            foreach ($subclasses as $subclass) {
                $inventory = $this->getSubclassInventory($subclass);
                $subclassData[$subclass->slug] = $inventory;
                $totalSubclasses++;

                // Flag potential issues
                if ($inventory['features']['count'] === 0) {
                    $issues++;
                }
            }

            $classes[$baseClass->slug] = [
                'name' => $baseClass->name,
                'subclass_count' => $subclasses->count(),
                'subclass_level' => $baseClass->subclass_level,
                'is_spellcaster' => $baseClass->spellcasting_ability_id !== null,
                'subclasses' => $subclassData,
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'base_classes' => $baseClasses->count(),
                'subclasses' => $totalSubclasses,
                'issues_flagged' => $issues,
            ],
            'classes' => $classes,
        ];
    }
}
