<?php

namespace App\Services\Importers;

use App\Models\CreatureType;
use App\Models\Monster;
use App\Models\MonsterAction;
use App\Models\MonsterLegendaryAction;
use App\Models\MonsterTrait;
use App\Services\Importers\Concerns\CachesLookupTables;
use App\Services\Importers\Concerns\ImportsConditions;
use App\Services\Importers\Concerns\ImportsModifiers;
use App\Services\Importers\Concerns\ImportsSenses;
use App\Services\Importers\Strategies\Monster\AberrationStrategy;
use App\Services\Importers\Strategies\Monster\BeastStrategy;
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

class MonsterImporter extends BaseImporter
{
    use CachesLookupTables;
    use ImportsConditions;
    use ImportsModifiers;
    use ImportsSenses;

    protected array $strategies = [];

    protected array $strategyStats = [];

    protected array $creatureTypeCache = [];

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
            new ElementalStrategy,
            new AberrationStrategy,
            new BeastStrategy,         // NEW
            new ShapechangerStrategy,  // Cross-cutting (after type-specific)
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

    /**
     * Get the parser instance for this importer.
     */
    public function getParser(): object
    {
        return new MonsterXmlParser;
    }

    /**
     * Import monsters from an XML file with statistics tracking.
     *
     * @param  string  $filePath  Path to XML file
     * @return array Returns statistics including created, updated, total, and strategy_stats
     */
    public function importWithStats(string $filePath): array
    {
        $parser = $this->getParser();
        $xmlContent = file_get_contents($filePath);
        $monsters = $parser->parse($xmlContent);

        $created = 0;
        $updated = 0;

        foreach ($monsters as $monsterData) {
            $monster = $this->import($monsterData);

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

    /**
     * Import a monster entity with strategy pattern support.
     *
     * This method is called by parent's import() which wraps it in a transaction.
     *
     * @param  array  $data  Parsed monster data
     * @return Monster The imported monster
     */
    protected function importEntity(array $data): Monster
    {
        $strategy = $this->selectStrategy($data);
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
        $data['traits'] = $strategy->enhanceTraits(
            $data['traits'],
            $data
        );

        $data['actions'] = $strategy->enhanceActions(
            array_merge($data['actions'], $data['reactions']),
            $data
        );

        $data['legendary'] = $strategy->enhanceLegendaryActions(
            $data['legendary'],
            $data
        );

        // Create/update monster
        $monster = $this->createOrUpdateMonster($data);

        // Strategy post-creation hook (for spellcasters, etc.)
        $strategy->afterCreate($monster, $data);

        // Sync tags from strategy
        $metadata = $strategy->extractMetadata($data);
        if (! empty($metadata['metrics']['tags_applied'])) {
            $monster->syncTags($metadata['metrics']['tags_applied']);
        }

        // Log strategy metadata
        if (! empty($metadata['warnings'])) {
            $this->strategyStats[$strategyName]['warnings'] += count($metadata['warnings']);
        }

        Log::channel('import-strategy')->info("Strategy applied: {$strategyName}", array_merge(
            ['strategy' => $strategyName, 'monster' => $data['name']],
            $metadata
        ));

        return $monster;
    }

    /**
     * Create or update the Monster model.
     *
     * @param  array  $monsterData  Parsed monster data
     */
    protected function createOrUpdateMonster(array $monsterData): Monster
    {
        // Lookup size
        $size = $this->cachedFind(\App\Models\Size::class, 'code', strtoupper($monsterData['size']));

        // Generate source-prefixed slug
        $sources = $monsterData['sources'] ?? [];
        $slug = $this->generateSlug($monsterData['name'], $sources);

        // Create/update monster
        $monster = Monster::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $monsterData['name'],
                'size_id' => $size->id,
                'type' => $monsterData['type'],
                'creature_type_id' => $this->resolveCreatureTypeId($monsterData['type']),
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
                'passive_perception' => $monsterData['passive_perception'],
                'languages' => $monsterData['languages'],
                'description' => $monsterData['description'],
                'sort_name' => $monsterData['sort_name'],
                'is_npc' => $monsterData['is_npc'],
            ]
        );

        // Import related data
        $this->importMonsterTraits($monster, $monsterData['traits']);
        $this->importMonsterActions($monster, $monsterData['actions']);
        $this->importMonsterLegendaryActions($monster, $monsterData['legendary']);
        $this->importMonsterModifiers($monster, $monsterData);
        $this->importEntitySenses($monster, $monsterData['senses']);

        // Import sources
        if (isset($monsterData['sources']) && is_array($monsterData['sources'])) {
            $this->importEntitySources($monster, $monsterData['sources']);
        }

        return $monster;
    }

    /**
     * Import monster-specific traits (not to be confused with BaseImporter's importTraits for character traits).
     */
    protected function importMonsterTraits(Monster $monster, array $traits): void
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

    /**
     * Import monster actions and reactions.
     */
    protected function importMonsterActions(Monster $monster, array $actions): void
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

    /**
     * Import legendary actions.
     */
    protected function importMonsterLegendaryActions(Monster $monster, array $legendary): void
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

    /**
     * Resolve creature type ID from the monster's type string.
     *
     * Lazy loads and caches creature types to avoid N+1 queries.
     *
     * @param  string  $type  Monster type string (e.g., "humanoid (elf)", "swarm of tiny beasts")
     * @return int|null Creature type ID or null if not found
     */
    protected function resolveCreatureTypeId(string $type): ?int
    {
        // Lazy load cache on first use
        if (empty($this->creatureTypeCache)) {
            $this->creatureTypeCache = CreatureType::pluck('id', 'slug')->all();
        }

        // extractBaseCreatureType returns lowercase, so we just prepend the prefix
        $baseType = $this->extractBaseCreatureType($type);
        $slug = 'core:'.$baseType;

        return $this->creatureTypeCache[$slug] ?? null;
    }

    /**
     * Extract base creature type from type string.
     *
     * Handles various D&D type formats:
     * - "humanoid (elf)" -> "humanoid"
     * - "fiend (demon, shapechanger)" -> "fiend"
     * - "swarm of tiny beasts" -> "swarm"
     *
     * Note: D&D 5e technically classifies swarms as their component creature type
     * (e.g., "swarm of bats" is type "beast"). However, we intentionally extract
     * "swarm" as a separate creature type for easier filtering in our API.
     *
     * @param  string  $type  Full type string from XML
     * @return string Base creature type (lowercase)
     */
    protected function extractBaseCreatureType(string $type): string
    {
        // Normalize to lowercase early for consistent handling
        $normalizedType = strtolower(trim($type));

        // Handle swarms first (special case - see note in docblock)
        if (str_starts_with($normalizedType, 'swarm')) {
            return 'swarm';
        }

        // Extract base type before parentheses
        if (str_contains($normalizedType, '(')) {
            return trim(explode('(', $normalizedType)[0]);
        }

        return $normalizedType;
    }
}
