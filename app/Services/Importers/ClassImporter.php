<?php

namespace App\Services\Importers;

use App\Models\CharacterClass;
use App\Models\ClassCounter;
use App\Services\Importers\Concerns\ImportsClassCounters;
use App\Services\Importers\Concerns\ImportsClassFeatures;
use App\Services\Importers\Concerns\ImportsEntityItems;
use App\Services\Importers\Concerns\ImportsModifiers;
use App\Services\Importers\Concerns\ImportsRandomTablesFromText;
use App\Services\Importers\Concerns\ImportsSpellProgression;
use App\Services\Importers\Strategies\CharacterClass\BaseClassStrategy;
use App\Services\Importers\Strategies\CharacterClass\SubclassStrategy;
use App\Services\Parsers\ClassXmlParser;
use Illuminate\Support\Facades\Log;

class ClassImporter extends BaseImporter
{
    use ImportsClassCounters;
    use ImportsClassFeatures;
    use ImportsEntityItems;
    use ImportsModifiers;
    use ImportsRandomTablesFromText;
    use ImportsSpellProgression;

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

        // For base classes (not subclasses), clear existing related data before re-importing
        // This ensures updateOrCreate doesn't skip features/modifiers/progression
        // Subclasses are handled separately in importSubclass() method
        if (empty($data['parent_class_id'])) {
            $this->clearClassRelatedData($class);
        }

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

        // Refresh to load all relationships created during import
        $class->refresh();

        return $class;
    }

    /**
     * Clear all related data for a class before re-importing.
     *
     * This ensures that updateOrCreate properly refreshes all relationships
     * instead of leaving stale data from previous imports.
     *
     * Called for base classes only - subclasses handled in importSubclass().
     */
    private function clearClassRelatedData(CharacterClass $class): void
    {
        // Clear features (and their special tags cascade via foreign key)
        $class->features()->delete();

        // Clear counters (Ki, Rage, Second Wind, etc.)
        $class->counters()->delete();

        // Clear spell progression
        $class->levelProgression()->delete();

        // Clear modifiers (ASIs, speed bonuses, AC bonuses, etc.)
        $class->modifiers()->delete();

        // Clear proficiencies
        $class->proficiencies()->delete();

        // Clear equipment (starting equipment choices)
        $class->equipment()->delete();

        // Clear traits (sources are preserved via entity_sources polymorphic table)
        $class->traits()->delete();

        // Note: We do NOT clear sources or subclasses:
        // - Sources are cumulative across files (PHB + XGE + TCE)
        // - Subclasses are handled separately in importSubclass()
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

        // 3. Extract description from first feature (intro feature has the subclass lore)
        $description = "Subclass of {$parentClass->name}";
        if (! empty($subclassData['features'])) {
            $firstFeature = $subclassData['features'][0];
            if (! empty($firstFeature['description'])) {
                $description = $firstFeature['description'];
            }
        }

        // 4. Create or update subclass
        $subclass = CharacterClass::updateOrCreate(
            ['slug' => $fullSlug],
            [
                'name' => $subclassData['name'],
                'parent_class_id' => $parentClass->id,
                'hit_die' => $parentClass->hit_die, // Inherit from parent
                'description' => $description,
                'spellcasting_ability_id' => $spellcastingAbilityId,
            ]
        );

        // 5. Clear existing relationships
        $subclass->features()->delete();
        $subclass->counters()->delete();
        $subclass->levelProgression()->delete();

        // 6. Import subclass-specific features
        if (! empty($subclassData['features'])) {
            $this->importFeatures($subclass, $subclassData['features']);
        }

        // 7. Import subclass-specific counters
        if (! empty($subclassData['counters'])) {
            foreach ($subclassData['counters'] as $counterData) {
                // Convert reset_timing back to single character for database
                $resetTiming = match ($counterData['reset_timing']) {
                    'short_rest' => 'S',
                    'long_rest' => 'L',
                    default => null,
                };

                // Use updateOrCreate to prevent duplicates on re-import
                // Unique key: class_id + level + counter_name
                ClassCounter::updateOrCreate(
                    [
                        'class_id' => $subclass->id,
                        'level' => $counterData['level'],
                        'counter_name' => $counterData['name'],
                    ],
                    [
                        'counter_value' => $counterData['value'],
                        'reset_timing' => $resetTiming,
                    ]
                );
            }
        }

        // 8. Import subclass-specific spell progression (e.g., Arcane Trickster, Eldritch Knight)
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
        // Apply strategies to transform raw data (e.g., resolve spellcasting_ability to ID)
        // This must happen before merge to ensure spellcasting_ability_id is populated
        foreach ($this->strategies as $strategy) {
            if ($strategy->appliesTo($data)) {
                $data = $strategy->enhance($data);
            }
        }

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
     * Also updates base class attributes (hit_die, spellcasting_ability) if the
     * incoming data has valid values and existing class has missing/zero values.
     * This handles cases where supplement files (DMG, XGE) are imported before
     * the base PHB file due to alphabetical ordering.
     *
     * @param  CharacterClass  $existingClass  Base class from PHB
     * @param  array  $supplementData  Data from XGE/TCE/SCAG or PHB
     */
    private function mergeSupplementData(CharacterClass $existingClass, array $supplementData): CharacterClass
    {
        $mergedSubclasses = 0;
        $skippedSubclasses = 0;
        $baseClassUpdated = false;
        $baseClassRelationshipsImported = false;

        // Merge base class attributes if incoming data has valid values
        // This fixes the issue where supplement files (DMG) are imported before PHB
        // due to alphabetical ordering, leaving hit_die=0 and spellcasting_ability=null
        $updates = [];

        if (($supplementData['hit_die'] ?? 0) > 0 && $existingClass->hit_die === 0) {
            $updates['hit_die'] = $supplementData['hit_die'];
        }

        if (! empty($supplementData['spellcasting_ability_id']) && $existingClass->spellcasting_ability_id === null) {
            $updates['spellcasting_ability_id'] = $supplementData['spellcasting_ability_id'];
        }

        // Update description if existing is a stub ("No description available")
        // Build description from traits if not directly provided (same logic as importEntity)
        $existingDescription = $existingClass->description ?? '';
        $incomingDescription = $supplementData['description'] ?? null;
        if (empty($incomingDescription) && ! empty($supplementData['traits'])) {
            $incomingDescription = $supplementData['traits'][0]['description'] ?? '';
        }

        if (
            ! empty($incomingDescription)
            && ($existingDescription === 'No description available' || str_starts_with($existingDescription, 'Subclass of '))
        ) {
            $updates['description'] = $incomingDescription;
        }

        if (! empty($updates)) {
            $existingClass->update($updates);
            $baseClassUpdated = true;

            Log::channel('import-strategy')->info('Updated base class attributes', [
                'class' => $existingClass->name,
                'updates' => array_keys($updates),
            ]);
        }

        // Import base class relationships if existing class is missing them
        // This handles the case where DMG (with only subclass data) is imported
        // before PHB (with full base class data) due to alphabetical ordering
        if ($this->existingClassMissingBaseData($existingClass)) {
            $baseClassRelationshipsImported = $this->importBaseClassRelationships($existingClass, $supplementData);
        }

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
            'base_class_updated' => $baseClassUpdated,
            'base_relationships_imported' => $baseClassRelationshipsImported,
            'subclasses_merged' => $mergedSubclasses,
            'subclasses_skipped' => $skippedSubclasses,
        ]);

        return $existingClass->fresh(); // Reload to get new subclasses
    }

    /**
     * Check if existing class is missing base class relationship data.
     *
     * A class is considered "missing base data" if it has no features, proficiencies,
     * traits, or level progression. This happens when a supplement file (like DMG)
     * that only contains subclass data is imported before the PHB file with full data.
     */
    private function existingClassMissingBaseData(CharacterClass $existingClass): bool
    {
        return $existingClass->features()->count() === 0
            && $existingClass->proficiencies()->count() === 0
            && $existingClass->traits()->count() === 0
            && $existingClass->levelProgression()->count() === 0;
    }

    /**
     * Import base class relationship data from supplement data.
     *
     * This imports proficiencies, traits, features, spell progression, counters,
     * and equipment for a base class that was previously created as a stub.
     *
     * @return bool True if any relationships were imported
     */
    private function importBaseClassRelationships(CharacterClass $existingClass, array $supplementData): bool
    {
        $imported = false;

        // Import traits (class lore/description)
        if (! empty($supplementData['traits'])) {
            $createdTraits = $this->importEntityTraits($existingClass, $supplementData['traits']);
            $imported = true;

            // Import random tables from traits
            foreach ($createdTraits as $index => $trait) {
                if (isset($supplementData['traits'][$index]['description'])) {
                    $this->importRandomTablesFromText($trait, $supplementData['traits'][$index]['description']);
                }
            }

            // Extract and import sources from traits
            $sources = [];
            foreach ($supplementData['traits'] as $trait) {
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
                $this->importEntitySources($existingClass, $sources);
            }

            Log::channel('import-strategy')->debug('Imported traits for existing class', [
                'class' => $existingClass->name,
                'count' => count($createdTraits),
            ]);
        }

        // Import proficiencies
        if (! empty($supplementData['proficiencies'])) {
            $this->importEntityProficiencies($existingClass, $supplementData['proficiencies']);
            $imported = true;

            Log::channel('import-strategy')->debug('Imported proficiencies for existing class', [
                'class' => $existingClass->name,
                'count' => count($supplementData['proficiencies']),
            ]);
        }

        // Import level progression (spell slots)
        if (! empty($supplementData['spell_progression'])) {
            $this->importSpellProgression($existingClass, $supplementData['spell_progression']);
            $imported = true;

            Log::channel('import-strategy')->debug('Imported spell progression for existing class', [
                'class' => $existingClass->name,
                'count' => count($supplementData['spell_progression']),
            ]);
        }

        // Import features
        if (! empty($supplementData['features'])) {
            $this->importFeatures($existingClass, $supplementData['features']);
            $imported = true;

            Log::channel('import-strategy')->debug('Imported features for existing class', [
                'class' => $existingClass->name,
                'count' => count($supplementData['features']),
            ]);
        }

        // Import counters (Channel Divinity, etc.)
        if (! empty($supplementData['counters'])) {
            $this->importCounters($existingClass, $supplementData['counters']);
            $imported = true;

            Log::channel('import-strategy')->debug('Imported counters for existing class', [
                'class' => $existingClass->name,
                'count' => count($supplementData['counters']),
            ]);
        }

        // Import starting equipment
        if (! empty($supplementData['equipment'])) {
            $this->importEquipment($existingClass, $supplementData['equipment']);
            $imported = true;

            Log::channel('import-strategy')->debug('Imported equipment for existing class', [
                'class' => $existingClass->name,
            ]);
        }

        return $imported;
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

    public function getParser(): object
    {
        return new ClassXmlParser;
    }
}
