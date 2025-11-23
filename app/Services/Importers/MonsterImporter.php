<?php

namespace App\Services\Importers;

use App\Models\Monster;
use App\Models\MonsterAction;
use App\Models\MonsterLegendaryAction;
use App\Models\MonsterTrait;
use App\Services\Importers\Concerns\CachesLookupTables;
use App\Services\Importers\Concerns\GeneratesSlugs;
use App\Services\Importers\Concerns\ImportsConditions;
use App\Services\Importers\Concerns\ImportsModifiers;
use App\Services\Importers\Concerns\ImportsSources;
use App\Services\Importers\Strategies\Monster\AberrationStrategy;
use App\Services\Importers\Strategies\Monster\CelestialStrategy;
use App\Services\Importers\Strategies\Monster\ConstructStrategy;
use App\Services\Importers\Strategies\Monster\DefaultStrategy;
use App\Services\Importers\Strategies\Monster\DragonStrategy;
use App\Services\Importers\Strategies\Monster\ElementalStrategy;
use App\Services\Importers\Strategies\Monster\FiendStrategy;
use App\Services\Importers\Strategies\Monster\ShapechangerStrategy;
use App\Services\Importers\Strategies\Monster\SpellcasterStrategy;
use App\Services\Importers\Strategies\Monster\SwarmStrategy;
use App\Services\Importers\Strategies\Monster\UndeadStrategy;
use App\Services\Parsers\MonsterXmlParser;
use Illuminate\Support\Facades\Log;

class MonsterImporter
{
    use CachesLookupTables;
    use GeneratesSlugs;
    use ImportsConditions;
    use ImportsModifiers;
    use ImportsSources;

    protected array $strategies = [];

    protected array $strategyStats = [];

    public function __construct()
    {
        $this->initializeStrategies();
    }

    protected function initializeStrategies(): void
    {
        $this->strategies = [
            new SpellcasterStrategy,   // Highest priority
            new FiendStrategy,
            new CelestialStrategy,
            new ConstructStrategy,
            new ElementalStrategy,     // NEW
            new AberrationStrategy,    // NEW
            new ShapechangerStrategy,  // NEW (cross-cutting, runs after type-specific)
            new DragonStrategy,
            new UndeadStrategy,
            new SwarmStrategy,
            new DefaultStrategy,       // Fallback (always last)
        ];
    }

    protected function selectStrategy(array $monsterData): \App\Services\Importers\Strategies\Monster\AbstractMonsterStrategy
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->appliesTo($monsterData)) {
                return $strategy;
            }
        }

        // Should never reach here due to DefaultStrategy fallback
        return new DefaultStrategy;
    }

    public function import(string $xmlPath): array
    {
        $parser = new MonsterXmlParser;
        $monsters = $parser->parse($xmlPath);

        $created = 0;
        $updated = 0;

        foreach ($monsters as $monsterData) {
            $strategy = $this->selectStrategy($monsterData);
            $strategyName = class_basename($strategy);

            // Reset strategy state
            $strategy->reset();

            // Track strategy usage
            if (! isset($this->strategyStats[$strategyName])) {
                $this->strategyStats[$strategyName] = [
                    'count' => 0,
                    'warnings' => 0,
                ];
            }
            $this->strategyStats[$strategyName]['count']++;

            // Apply strategy enhancements
            $monsterData['traits'] = $strategy->enhanceTraits(
                $monsterData['traits'],
                $monsterData
            );

            $monsterData['actions'] = $strategy->enhanceActions(
                array_merge($monsterData['actions'], $monsterData['reactions']),
                $monsterData
            );

            $monsterData['legendary'] = $strategy->enhanceLegendaryActions(
                $monsterData['legendary'],
                $monsterData
            );

            // Import monster
            $monster = $this->importEntity($monsterData);

            // Strategy post-creation hook (for spellcasters, etc.)
            $strategy->afterCreate($monster, $monsterData);

            // Log strategy metadata
            $metadata = $strategy->extractMetadata($monsterData);
            if (! empty($metadata['warnings'])) {
                $this->strategyStats[$strategyName]['warnings'] += count($metadata['warnings']);
            }

            Log::channel('import-strategy')->info($strategyName, array_merge(
                ['monster' => $monsterData['name']],
                $metadata
            ));

            if ($monster->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => count($monsters),
            'strategy_stats' => $this->strategyStats,
        ];
    }

    protected function importEntity(array $monsterData): Monster
    {
        // Lookup size
        $size = $this->cachedFind(\App\Models\Size::class, 'code', strtoupper($monsterData['size']));

        // Create/update monster
        $monster = Monster::updateOrCreate(
            ['slug' => $this->generateSlug($monsterData['name'])],
            [
                'name' => $monsterData['name'],
                'size_id' => $size->id,
                'type' => $monsterData['type'],
                'alignment' => $monsterData['alignment'],
                'armor_class' => $monsterData['armor_class'],
                'armor_type' => $monsterData['armor_type'],
                'hit_points_average' => $monsterData['hit_points'],
                'hit_dice' => $monsterData['hit_dice'],
                'speed_walk' => $monsterData['speed_walk'],
                'speed_fly' => $monsterData['speed_fly'],
                'speed_swim' => $monsterData['speed_swim'],
                'speed_burrow' => $monsterData['speed_burrow'],
                'speed_climb' => $monsterData['speed_climb'],
                'can_hover' => $monsterData['can_hover'],
                'strength' => $monsterData['strength'],
                'dexterity' => $monsterData['dexterity'],
                'constitution' => $monsterData['constitution'],
                'intelligence' => $monsterData['intelligence'],
                'wisdom' => $monsterData['wisdom'],
                'charisma' => $monsterData['charisma'],
                'challenge_rating' => $monsterData['challenge_rating'],
                'experience_points' => $monsterData['experience_points'],
                'description' => $monsterData['description'],
            ]
        );

        // Import related data
        $this->importTraits($monster, $monsterData['traits']);
        $this->importActions($monster, $monsterData['actions']);
        $this->importLegendaryActions($monster, $monsterData['legendary']);
        $this->importMonsterModifiers($monster, $monsterData);

        return $monster;
    }

    protected function importTraits(Monster $monster, array $traits): void
    {
        // Clear existing
        $monster->traits()->delete();

        foreach ($traits as $trait) {
            MonsterTrait::create([
                'monster_id' => $monster->id,
                'name' => $trait['name'],
                'description' => $trait['description'],
                'attack_data' => $trait['attack_data'],
                'sort_order' => $trait['sort_order'],
            ]);
        }
    }

    protected function importActions(Monster $monster, array $actions): void
    {
        // Clear existing
        $monster->actions()->delete();

        foreach ($actions as $action) {
            MonsterAction::create([
                'monster_id' => $monster->id,
                'action_type' => $action['action_type'],
                'name' => $action['name'],
                'description' => $action['description'],
                'attack_data' => $action['attack_data'],
                'recharge' => $action['recharge'],
                'sort_order' => $action['sort_order'],
            ]);
        }
    }

    protected function importLegendaryActions(Monster $monster, array $legendary): void
    {
        // Clear existing
        $monster->legendaryActions()->delete();

        foreach ($legendary as $action) {
            MonsterLegendaryAction::create([
                'monster_id' => $monster->id,
                'name' => $action['name'],
                'description' => $action['description'],
                'action_cost' => $action['action_cost'],
                'is_lair_action' => $action['is_lair_action'],
                'attack_data' => $action['attack_data'],
                'recharge' => $action['recharge'],
                'sort_order' => $action['sort_order'],
            ]);
        }
    }

    protected function importMonsterModifiers(Monster $monster, array $monsterData): void
    {
        $modifiers = [];

        // Saving throw bonuses
        foreach ($monsterData['saving_throws'] as $save) {
            $modifiers[] = [
                'modifier_category' => 'saving_throw_'.strtolower($save['ability']),
                'value' => (string) $save['bonus'],
            ];
        }

        // Skill proficiencies
        foreach ($monsterData['skills'] as $skill) {
            $modifiers[] = [
                'modifier_category' => 'skill_'.strtolower(str_replace(' ', '_', $skill['skill'])),
                'value' => (string) $skill['bonus'],
            ];
        }

        // Damage resistances/immunities/vulnerabilities
        if (! empty($monsterData['damage_resistances'])) {
            $modifiers[] = [
                'modifier_category' => 'damage_resistance',
                'value' => '',
                'condition' => $monsterData['damage_resistances'],
            ];
        }

        if (! empty($monsterData['damage_immunities'])) {
            $modifiers[] = [
                'modifier_category' => 'damage_immunity',
                'value' => '',
                'condition' => $monsterData['damage_immunities'],
            ];
        }

        if (! empty($monsterData['damage_vulnerabilities'])) {
            $modifiers[] = [
                'modifier_category' => 'damage_vulnerability',
                'value' => '',
                'condition' => $monsterData['damage_vulnerabilities'],
            ];
        }

        $this->importEntityModifiers($monster, $modifiers);
    }
}
