<?php

namespace App\Services\Importers;

use App\Models\AbilityScore;
use App\Models\CharacterClass;
use App\Models\ClassCounter;
use App\Models\ClassFeature;
use App\Models\ClassLevelProgression;
use App\Services\Importers\Concerns\GeneratesSlugs;
use App\Services\Importers\Concerns\ImportsProficiencies;
use App\Services\Importers\Concerns\ImportsSources;
use App\Services\Importers\Concerns\ImportsTraits;
use Illuminate\Support\Facades\DB;

class ClassImporter
{
    use GeneratesSlugs, ImportsProficiencies, ImportsSources, ImportsTraits;

    /**
     * Import a class from parsed data.
     */
    public function import(array $data): CharacterClass
    {
        return DB::transaction(function () use ($data) {
            // 1. Generate slug
            $slug = $this->generateSlug($data['name']);

            // 2. Check if this file has complete base class data
            // Files with hit_die = 0 only contain supplemental content (subclasses, flavor text)
            $hasBaseClassData = ($data['hit_die'] ?? 0) > 0;

            // 3. Look up spellcasting ability if present
            $spellcastingAbilityId = null;
            if (! empty($data['spellcasting_ability'])) {
                $ability = AbilityScore::where('name', $data['spellcasting_ability'])->first();
                $spellcastingAbilityId = $ability?->id;
            }

            // 4. Build description from traits if not directly provided
            $description = $data['description'] ?? null;
            if (empty($description) && ! empty($data['traits'])) {
                // Use first trait's description as class description
                $description = $data['traits'][0]['description'] ?? '';
            }

            // 5. Create or update base class using slug as unique key
            if ($hasBaseClassData) {
                // Full base class data - create or fully update
                $class = CharacterClass::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $data['name'],
                        'parent_class_id' => null, // Base class
                        'hit_die' => $data['hit_die'],
                        'description' => $description ?: 'No description available',
                        'spellcasting_ability_id' => $spellcastingAbilityId,
                    ]
                );

                // Clear existing relationships when doing full update
                $class->proficiencies()->delete();
                $class->traits()->delete();
                $class->sources()->delete();
                $class->features()->delete();
                $class->levelProgression()->delete();
                $class->counters()->delete();
            } else {
                // Supplemental file - only add to existing class, don't overwrite
                $class = CharacterClass::firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $data['name'],
                        'parent_class_id' => null,
                        'hit_die' => 0, // Will be set by full file
                        'description' => $description ?: 'No description available',
                        'spellcasting_ability_id' => null,
                    ]
                );

                // Don't clear existing relationships - we're adding, not replacing
            }

            // 6-11. Only import base class data if this file has complete base class info
            // Supplemental files (TCE, XGE) only add subclasses, not base mechanics
            if ($hasBaseClassData) {
                // 6. Import proficiencies
                if (isset($data['proficiencies'])) {
                    $this->importEntityProficiencies($class, $data['proficiencies']);
                }

                // 7. Import traits (flavor text)
                if (isset($data['traits'])) {
                    $this->importEntityTraits($class, $data['traits']);
                }

                // 8. Import sources
                if (isset($data['traits'])) {
                    // Extract sources from all traits
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

                // 9. Import features
                if (isset($data['features'])) {
                    $this->importFeatures($class, $data['features']);
                }

                // 10. Import spell progression
                if (isset($data['spell_progression'])) {
                    $this->importSpellProgression($class, $data['spell_progression']);
                }

                // 11. Import counters
                if (isset($data['counters'])) {
                    $this->importCounters($class, $data['counters']);
                }
            }

            // 12. Import subclasses
            if (isset($data['subclasses'])) {
                foreach ($data['subclasses'] as $subclassData) {
                    $this->importSubclass($class, $subclassData);
                }
            }

            return $class;
        });
    }

    /**
     * Import class features.
     */
    private function importFeatures(CharacterClass $class, array $features): void
    {
        foreach ($features as $featureData) {
            ClassFeature::create([
                'class_id' => $class->id,
                'level' => $featureData['level'],
                'feature_name' => $featureData['name'],
                'is_optional' => $featureData['is_optional'],
                'description' => $featureData['description'],
                'sort_order' => $featureData['sort_order'],
            ]);
        }
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
        return DB::transaction(function () use ($parentClass, $subclassData) {
            // 1. Generate hierarchical slug: "fighter-battle-master"
            $fullSlug = $this->generateSlug($subclassData['name'], $parentClass->slug);

            // 2. Create or update subclass
            $subclass = CharacterClass::updateOrCreate(
                ['slug' => $fullSlug],
                [
                    'name' => $subclassData['name'],
                    'parent_class_id' => $parentClass->id,
                    'hit_die' => $parentClass->hit_die, // Inherit from parent
                    'description' => "Subclass of {$parentClass->name}",
                    'spellcasting_ability_id' => $parentClass->spellcasting_ability_id, // Inherit from parent
                ]
            );

            // 3. Clear existing relationships
            $subclass->features()->delete();
            $subclass->counters()->delete();

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

            return $subclass;
        });
    }
}
