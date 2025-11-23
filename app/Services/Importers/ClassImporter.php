<?php

namespace App\Services\Importers;

use App\Models\CharacterClass;
use App\Models\ClassCounter;
use App\Models\ClassFeature;
use App\Models\ClassLevelProgression;
use App\Models\Modifier;
use App\Models\Proficiency;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use App\Services\Importers\Concerns\ImportsEntityItems;
use App\Services\Importers\Concerns\ImportsModifiers;
use App\Services\Importers\Concerns\ImportsRandomTablesFromText;
use App\Services\Importers\Strategies\CharacterClass\BaseClassStrategy;
use App\Services\Importers\Strategies\CharacterClass\SubclassStrategy;
use App\Services\Parsers\ClassXmlParser;
use Illuminate\Support\Facades\Log;

class ClassImporter extends BaseImporter
{
    use ImportsEntityItems;
    use ImportsModifiers;
    use ImportsRandomTablesFromText;

    private array $strategies = [];

    public function __construct()
    {
        $this->initializeStrategies();
    }

    /**
     * Initialize class import strategies.
     */
    private function initializeStrategies(): void
    {
        $this->strategies = [
            new BaseClassStrategy,
            new SubclassStrategy,
        ];
    }

    /**
     * Import a class from parsed data.
     */
    protected function importEntity(array $data): CharacterClass
    {
        // Apply all applicable strategies
        foreach ($this->strategies as $strategy) {
            if ($strategy->appliesTo($data)) {
                $data = $strategy->enhance($data);
                $this->logStrategyApplication($strategy, $data);
            }
        }

        // If slug not set by strategy, generate from name
        if (! isset($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['name']);
        }

        // Build description from traits if not directly provided
        $description = $data['description'] ?? null;
        if (empty($description) && ! empty($data['traits'])) {
            $description = $data['traits'][0]['description'] ?? '';
        }

        // Create or update class using slug as unique key
        $class = CharacterClass::updateOrCreate(
            ['slug' => $data['slug']],
            [
                'name' => $data['name'],
                'parent_class_id' => $data['parent_class_id'] ?? null,
                'hit_die' => $data['hit_die'],
                'description' => $description ?: 'No description available',
                'spellcasting_ability_id' => $data['spellcasting_ability_id'] ?? null,
            ]
        );

        // Import relationships
        if (! empty($data['traits'])) {
            $createdTraits = $this->importEntityTraits($class, $data['traits']);

            // Import random tables from traits with pipe-delimited tables or <roll> elements
            foreach ($createdTraits as $index => $trait) {
                if (isset($data['traits'][$index]['description'])) {
                    // This handles both pipe-delimited tables AND <roll> XML tags
                    $this->importRandomTablesFromText($trait, $data['traits'][$index]['description']);
                }
            }

            // Extract and import sources from traits
            $sources = [];
            foreach ($data['traits'] as $trait) {
                if (! empty($trait['sources'])) {
                    $sources = array_merge($sources, $trait['sources']);
                }
            }

            // Remove duplicates based on code
            $uniqueSources = [];
            foreach ($sources as $source) {
                $uniqueSources[$source['code']] = $source;
            }
            $sources = array_values($uniqueSources);

            if (! empty($sources)) {
                $this->importEntitySources($class, $sources);
            }
        }

        if (! empty($data['proficiencies'])) {
            $this->importEntityProficiencies($class, $data['proficiencies']);
        }

        // Import level progression if present
        if (! empty($data['spell_progression'])) {
            $this->importSpellProgression($class, $data['spell_progression']);
        }

        // Import features if present
        if (! empty($data['features'])) {
            $this->importFeatures($class, $data['features']);
        }

        // Import counters if present
        if (! empty($data['counters'])) {
            $this->importCounters($class, $data['counters']);
        }

        // Import subclasses if present
        if (! empty($data['subclasses'])) {
            foreach ($data['subclasses'] as $subclassData) {
                $this->importSubclass($class, $subclassData);
            }
        }

        // Import starting equipment
        if (! empty($data['equipment'])) {
            $this->importEquipment($class, $data['equipment']);
        }

        return $class;
    }

    /**
     * Log strategy application to import-strategy channel.
     */
    private function logStrategyApplication($strategy, array $data): void
    {
        Log::channel('import-strategy')->info('Strategy applied', [
            'class' => $data['name'],
            'strategy' => class_basename($strategy),
            'warnings' => $strategy->getWarnings(),
            'metrics' => $strategy->getMetrics(),
        ]);

        // Reset strategy for next entity
        $strategy->reset();
    }

    /**
     * Import class features.
     */
    private function importFeatures(CharacterClass $class, array $features): void
    {
        foreach ($features as $featureData) {
            $feature = ClassFeature::create([
                'class_id' => $class->id,
                'level' => $featureData['level'],
                'feature_name' => $featureData['name'],
                'is_optional' => $featureData['is_optional'],
                'description' => $featureData['description'],
                'sort_order' => $featureData['sort_order'],
            ]);

            // Detect Ability Score Improvement and create entity_modifiers
            if (stripos($featureData['name'], 'Ability Score Improvement') !== false) {
                // Create modifier directly without clearing existing ones
                Modifier::create([
                    'reference_type' => get_class($class),
                    'reference_id' => $class->id,
                    'modifier_category' => 'ability_score',
                    'value' => '+2',
                    'ability_score_id' => null, // Player chooses
                    'is_choice' => true,
                    'choice_count' => 2, // Standard ASI allows 2 increases
                    'level' => $featureData['level'],
                    'condition' => 'Choose one ability score to increase by 2, or two ability scores to increase by 1 each',
                ]);
            }

            // Detect Bonus Proficiencies and create proficiency records
            if (stripos($featureData['name'], 'Bonus Proficiencies') !== false) {
                $this->importBonusProficiencies($class, $featureData);
            }

            // Import random tables from <roll> XML elements
            if (! empty($featureData['rolls'])) {
                $this->importFeatureRolls($feature, $featureData['rolls']);
            }

            // Import random tables from pipe-delimited tables in description text
            // This handles BOTH dice-based random tables AND reference tables (dice_type = null)
            $this->importRandomTablesFromText($feature, $featureData['description'], clearExisting: false);
        }
    }

    /**
     * Import bonus proficiencies from feature description text.
     */
    private function importBonusProficiencies(CharacterClass $class, array $featureData): void
    {
        $text = $featureData['description'];
        $level = $featureData['level'];

        // Check if it's a choice-based proficiency
        if (preg_match('/proficiency with (\w+) skills? of your choice/i', $text, $matches)) {
            // Extract number (e.g., "three skills" -> 3)
            $quantityWords = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5];
            $quantityWord = strtolower($matches[1]);
            $quantity = $quantityWords[$quantityWord] ?? 1;

            // Create a single choice record - player chooses X skills from all available
            Proficiency::create([
                'reference_type' => get_class($class),
                'reference_id' => $class->id,
                'proficiency_type' => 'skill',
                'proficiency_name' => null, // Player chooses
                'grants' => true,
                'is_choice' => true,
                'quantity' => $quantity,
                'level' => $level,
            ]);

            return;
        }

        // Parse fixed proficiencies from text
        // Patterns: "proficiency with X, Y, and Z" or "proficiency with X and Y"
        if (preg_match('/proficiency with (.+?)(?:\.|$)/i', $text, $matches)) {
            $proficienciesText = $matches[1];

            // Split by commas and "and"
            $proficiencies = preg_split('/,\s*(?:and\s+)?|\s+and\s+/', $proficienciesText);

            foreach ($proficiencies as $profName) {
                $profName = trim($profName);
                if (empty($profName)) {
                    continue;
                }

                // Determine proficiency type based on keywords
                $profType = $this->determineProficiencyType($profName);

                Proficiency::create([
                    'reference_type' => get_class($class),
                    'reference_id' => $class->id,
                    'proficiency_type' => $profType,
                    'proficiency_name' => $profName,
                    'grants' => true,
                    'is_choice' => false,
                    'level' => $level,
                ]);
            }
        }
    }

    /**
     * Determine proficiency type from proficiency name.
     */
    private function determineProficiencyType(string $name): string
    {
        $lowerName = strtolower($name);

        if (str_contains($lowerName, 'armor')) {
            return 'armor';
        }
        if (str_contains($lowerName, 'weapon') || str_contains($lowerName, 'martial')) {
            return 'weapon';
        }
        if (str_contains($lowerName, 'kit') || str_contains($lowerName, 'tools')) {
            return 'tool';
        }
        if (str_contains($lowerName, 'shield')) {
            return 'armor'; // Shields are armor category
        }

        // Default to tool for unknown types
        return 'tool';
    }

    /**
     * Import spell progression (level progression with spell slots).
     */
    private function importSpellProgression(CharacterClass $class, array $progression): void
    {
        foreach ($progression as $levelData) {
            ClassLevelProgression::create([
                'class_id' => $class->id,
                'level' => $levelData['level'],
                'cantrips_known' => $levelData['cantrips_known'],
                'spell_slots_1st' => $levelData['spell_slots_1st'],
                'spell_slots_2nd' => $levelData['spell_slots_2nd'],
                'spell_slots_3rd' => $levelData['spell_slots_3rd'],
                'spell_slots_4th' => $levelData['spell_slots_4th'],
                'spell_slots_5th' => $levelData['spell_slots_5th'],
                'spell_slots_6th' => $levelData['spell_slots_6th'],
                'spell_slots_7th' => $levelData['spell_slots_7th'],
                'spell_slots_8th' => $levelData['spell_slots_8th'],
                'spell_slots_9th' => $levelData['spell_slots_9th'],
                'spells_known' => $levelData['spells_known'] ?? null,
            ]);
        }
    }

    /**
     * Import class counters (Second Wind, Ki, Rage, etc.).
     */
    private function importCounters(CharacterClass $class, array $counters): void
    {
        foreach ($counters as $counterData) {
            // Skip subclass counters for now (will be handled in subclass import)
            if (! empty($counterData['subclass'])) {
                continue;
            }

            // Convert reset_timing back to single character for database
            $resetTiming = match ($counterData['reset_timing']) {
                'short_rest' => 'S',
                'long_rest' => 'L',
                default => null,
            };

            ClassCounter::create([
                'class_id' => $class->id,
                'level' => $counterData['level'],
                'counter_name' => $counterData['name'],
                'counter_value' => $counterData['value'],
                'reset_timing' => $resetTiming,
            ]);
        }
    }

    /**
     * Import a subclass (e.g., Battle Master, Eldritch Knight).
     */
    public function importSubclass(CharacterClass $parentClass, array $subclassData): CharacterClass
    {
        // 1. Generate hierarchical slug: "fighter-battle-master"
        $fullSlug = $this->generateSlug($subclassData['name'], $parentClass->slug);

        // 2. Determine spellcasting ability
        // Use subclass-specific ability if present (e.g., Arcane Trickster, Eldritch Knight)
        // Otherwise inherit from parent
        $spellcastingAbilityId = $parentClass->spellcasting_ability_id;
        if (! empty($subclassData['spellcasting_ability'])) {
            $ability = \App\Models\AbilityScore::where('name', $subclassData['spellcasting_ability'])->first();
            $spellcastingAbilityId = $ability?->id;
        }

        // 3. Create or update subclass
        $subclass = CharacterClass::updateOrCreate(
            ['slug' => $fullSlug],
            [
                'name' => $subclassData['name'],
                'parent_class_id' => $parentClass->id,
                'hit_die' => $parentClass->hit_die, // Inherit from parent
                'description' => "Subclass of {$parentClass->name}",
                'spellcasting_ability_id' => $spellcastingAbilityId,
            ]
        );

        // 4. Clear existing relationships
        $subclass->features()->delete();
        $subclass->counters()->delete();
        $subclass->levelProgression()->delete();

        // 4. Import subclass-specific features
        if (! empty($subclassData['features'])) {
            $this->importFeatures($subclass, $subclassData['features']);
        }

        // 5. Import subclass-specific counters
        if (! empty($subclassData['counters'])) {
            foreach ($subclassData['counters'] as $counterData) {
                // Convert reset_timing back to single character for database
                $resetTiming = match ($counterData['reset_timing']) {
                    'short_rest' => 'S',
                    'long_rest' => 'L',
                    default => null,
                };

                ClassCounter::create([
                    'class_id' => $subclass->id,
                    'level' => $counterData['level'],
                    'counter_name' => $counterData['name'],
                    'counter_value' => $counterData['value'],
                    'reset_timing' => $resetTiming,
                ]);
            }
        }

        // 6. Import subclass-specific spell progression (e.g., Arcane Trickster, Eldritch Knight)
        if (! empty($subclassData['spell_progression'])) {
            $this->importSpellProgression($subclass, $subclassData['spell_progression']);
        }

        return $subclass;
    }

    /**
     * Import class with merge strategy for multi-file imports.
     *
     * Handles scenarios like:
     * - PHB defines base Barbarian class with Path of the Berserker
     * - XGE adds Path of the Ancestral Guardian, Path of the Storm Herald
     * - TCE adds Path of the Beast
     *
     * @param  array  $data  Parsed class data
     * @param  MergeMode  $mode  Merge strategy
     */
    public function importWithMerge(array $data, MergeMode $mode = MergeMode::CREATE): CharacterClass
    {
        $slug = $this->generateSlug($data['name']);
        $existingClass = CharacterClass::where('slug', $slug)->first();

        // Handle SKIP_IF_EXISTS mode
        if ($existingClass && $mode === MergeMode::SKIP_IF_EXISTS) {
            Log::channel('import-strategy')->info('Skipped existing class', [
                'class' => $data['name'],
                'slug' => $slug,
                'mode' => $mode->value,
            ]);

            return $existingClass;
        }

        // Handle MERGE mode
        if ($existingClass && $mode === MergeMode::MERGE) {
            return $this->mergeSupplementData($existingClass, $data);
        }

        // Default CREATE mode
        return $this->import($data);
    }

    /**
     * Merge supplement data (subclasses, features) into existing class.
     *
     * @param  CharacterClass  $existingClass  Base class from PHB
     * @param  array  $supplementData  Data from XGE/TCE/SCAG
     */
    private function mergeSupplementData(CharacterClass $existingClass, array $supplementData): CharacterClass
    {
        $mergedSubclasses = 0;
        $skippedSubclasses = 0;

        // Get existing subclass names to prevent duplicates
        $existingSubclassNames = $existingClass->subclasses()
            ->pluck('name')
            ->map(fn ($name) => strtolower(trim($name)))
            ->toArray();

        // Merge subclasses
        if (! empty($supplementData['subclasses'])) {
            foreach ($supplementData['subclasses'] as $subclassData) {
                $normalizedName = strtolower(trim($subclassData['name']));

                if (in_array($normalizedName, $existingSubclassNames)) {
                    $skippedSubclasses++;
                    Log::channel('import-strategy')->debug('Skipped duplicate subclass', [
                        'class' => $existingClass->name,
                        'subclass' => $subclassData['name'],
                    ]);

                    continue;
                }

                // Import new subclass
                $this->importSubclass($existingClass, $subclassData);
                $mergedSubclasses++;
            }
        }

        Log::channel('import-strategy')->info('Merged supplement data', [
            'class' => $existingClass->name,
            'subclasses_merged' => $mergedSubclasses,
            'subclasses_skipped' => $skippedSubclasses,
        ]);

        return $existingClass->fresh(); // Reload to get new subclasses
    }

    /**
     * Import starting equipment for a class.
     *
     * Uses existing entity_items polymorphic table.
     *
     * @param  CharacterClass  $class  The class model
     * @param  array  $equipmentData  Parsed equipment data
     */
    private function importEquipment(CharacterClass $class, array $equipmentData): void
    {
        if (empty($equipmentData['items'])) {
            return;
        }

        // Clear existing equipment
        $class->equipment()->delete();

        foreach ($equipmentData['items'] as $itemData) {
            // Try to match item description to Item record
            $item = $this->matchItemByDescription($itemData['description']);

            $class->equipment()->create([
                'item_id' => $item?->id,
                'description' => $itemData['description'],
                'is_choice' => $itemData['is_choice'],
                'choice_group' => $itemData['choice_group'] ?? null,
                'choice_option' => $itemData['choice_option'] ?? null,
                'quantity' => $itemData['quantity'],
                'choice_description' => $itemData['is_choice']
                    ? 'Starting equipment choice'
                    : null,
            ]);
        }
    }

    /**
     * Import random tables from <roll> XML elements.
     *
     * Groups rolls by description to create tables with level-based entries.
     *
     * Example: Sneak Attack has 10 rolls with description "Extra Damage"
     *          → Creates 1 table with 10 entries (one per level)
     *
     * @param  array  $rolls  Array of ['description' => string, 'formula' => string, 'level' => int|null]
     */
    private function importFeatureRolls(ClassFeature $feature, array $rolls): void
    {
        // Group rolls by description (table name)
        // Example: All "Extra Damage" rolls → one table
        $groupedRolls = collect($rolls)->groupBy('description');

        foreach ($groupedRolls as $tableName => $rollGroup) {
            // Extract dice type from first roll formula
            // Examples: "1d6" → "d6", "2d12" → "d12"
            $firstFormula = $rollGroup->first()['formula'];
            $diceType = $this->extractDiceType($firstFormula);

            $table = RandomTable::create([
                'reference_type' => ClassFeature::class,
                'reference_id' => $feature->id,
                'table_name' => $tableName,
                'dice_type' => $diceType,
                'description' => null,
            ]);

            foreach ($rollGroup as $index => $roll) {
                RandomTableEntry::create([
                    'random_table_id' => $table->id,
                    'roll_min' => $roll['level'] ?? 1, // Use level as roll value, or 1 if no level
                    'roll_max' => $roll['level'] ?? 1,
                    'result_text' => $roll['formula'], // "1d6", "2d6", etc.
                    'level' => $roll['level'] ?? null, // Character level when this roll becomes available
                    'sort_order' => $index,
                ]);
            }
        }
    }

    /**
     * Extract dice type from formula (e.g., "1d6" → "d6", "2d12" → "d12").
     *
     * @param  string  $formula  Dice formula like "1d6", "2d12+5"
     * @return string|null Dice type like "d6", "d12" or null if not found
     */
    private function extractDiceType(string $formula): ?string
    {
        if (preg_match('/\d*d\d+/', $formula, $matches)) {
            // Remove leading number: "2d6" → "d6"
            return preg_replace('/^\d+/', '', $matches[0]);
        }

        return null;
    }

    protected function getParser(): object
    {
        return new ClassXmlParser;
    }
}
