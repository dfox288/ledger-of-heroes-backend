<?php

namespace App\Services\Importers;

use App\Enums\ActionCost;
use App\Models\CharacterClass;
use App\Models\ClassOptionalFeature;
use App\Models\OptionalFeature;
use App\Models\SpellSchool;
use App\Services\Importers\Concerns\CachesLookupTables;
use App\Services\Importers\Concerns\ImportsPrerequisites;
use App\Services\Parsers\OptionalFeatureXmlParser;
use Illuminate\Database\Eloquent\Model;

class OptionalFeatureImporter extends BaseImporter
{
    use CachesLookupTables;
    use ImportsPrerequisites;

    /**
     * Import an optional feature from parsed data.
     */
    protected function importEntity(array $data): Model
    {
        // 1. Look up spell school if provided
        $spellSchoolId = null;
        if (! empty($data['spell_school_code'])) {
            $spellSchoolId = $this->lookupSpellSchool($data['spell_school_code']);
        }

        // Generate source-prefixed slug
        $sources = $data['sources'] ?? [];
        $slug = $this->generateSlug($data['name'], $sources);

        // Parse action cost from casting_time
        $actionCost = ActionCost::fromCastingTime($data['casting_time']);

        // 2. Upsert optional feature using slug as unique key
        $feature = OptionalFeature::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $data['name'],
                'feature_type' => $data['feature_type'],
                'level_requirement' => $data['level_requirement'],
                'prerequisite_text' => $data['prerequisite_text'],
                'description' => $data['description'],
                'casting_time' => $data['casting_time'],
                'action_cost' => $actionCost,
                'range' => $data['range'],
                'duration' => $data['duration'],
                'spell_school_id' => $spellSchoolId,
                'resource_type' => $data['resource_type'],
                'resource_cost' => $data['resource_cost'],
            ]
        );

        // 2. Clear existing polymorphic relationships
        $feature->sources()->delete();
        $feature->prerequisites()->delete();

        // 3. Clear existing class associations
        ClassOptionalFeature::where('optional_feature_id', $feature->id)->delete();

        // 4. Import class associations
        if (isset($data['classes']) && is_array($data['classes'])) {
            $this->importClassAssociations($feature, $data['classes']);
        }

        // 5. Import sources using trait
        if (isset($data['sources']) && is_array($data['sources'])) {
            $this->importEntitySources($feature, $data['sources']);
        }

        // 6. Import prerequisites (level requirements become CharacterClass prerequisites)
        if ($data['level_requirement']) {
            $this->importLevelPrerequisite($feature, $data);
        }

        // 7. Refresh to load all relationships created during import
        $feature->refresh();

        return $feature;
    }

    /**
     * Import class associations for an optional feature.
     *
     * When a subclass name is provided, attempts to link directly to the subclass
     * entity. Falls back to the base class with subclass_name in pivot if the
     * subclass entity doesn't exist in the database.
     *
     * @param  OptionalFeature  $feature  The optional feature
     * @param  array  $classesData  Array of ['class' => 'Warlock', 'subclass' => null]
     */
    private function importClassAssociations(OptionalFeature $feature, array $classesData): void
    {
        foreach ($classesData as $classData) {
            $className = $classData['class'];
            $subclassName = $classData['subclass'] ?? null;

            // If subclass is specified, try to find the subclass entity first
            if ($subclassName !== null) {
                $subclass = CharacterClass::where('name', $subclassName)->first();

                if ($subclass) {
                    // Link directly to the subclass - no need for subclass_name in pivot
                    ClassOptionalFeature::create([
                        'class_id' => $subclass->id,
                        'optional_feature_id' => $feature->id,
                        'subclass_name' => null,
                    ]);

                    continue;
                }
            }

            // Find the base character class by name
            $characterClass = CharacterClass::where('name', $className)->first();

            if (! $characterClass) {
                // Log warning but continue import
                logger()->warning("Class not found for optional feature: {$className}", [
                    'feature' => $feature->name,
                    'class' => $className,
                ]);

                continue;
            }

            // Create class association - include subclass_name only if subclass entity wasn't found
            ClassOptionalFeature::create([
                'class_id' => $characterClass->id,
                'optional_feature_id' => $feature->id,
                'subclass_name' => $subclassName,
            ]);
        }
    }

    /**
     * Import level requirement as a CharacterClass prerequisite.
     *
     * @param  OptionalFeature  $feature  The optional feature
     * @param  array  $data  Parsed feature data
     */
    private function importLevelPrerequisite(OptionalFeature $feature, array $data): void
    {
        // Extract class name from prerequisite text or use first associated class
        $className = $this->extractClassFromPrerequisite($data['prerequisite_text']);

        if (! $className && isset($data['classes'][0])) {
            $className = $data['classes'][0]['class'];
        }

        if (! $className) {
            return; // Can't create prerequisite without knowing the class
        }

        $characterClass = CharacterClass::where('name', $className)->first();

        if (! $characterClass) {
            return;
        }

        // Create CharacterClass prerequisite with minimum_value = level requirement
        $prerequisitesData = [[
            'prerequisite_type' => CharacterClass::class,
            'prerequisite_id' => $characterClass->id,
            'minimum_value' => $data['level_requirement'],
            'description' => null,
            'group_id' => 1,
        ]];

        $this->importEntityPrerequisites($feature, $prerequisitesData);
    }

    /**
     * Extract class name from prerequisite text.
     *
     * Examples:
     * - "17th level Monk" -> "Monk"
     * - "9th level Warlock" -> "Warlock"
     * - "Eldritch Blast cantrip" -> null
     */
    private function extractClassFromPrerequisite(?string $prerequisiteText): ?string
    {
        if (! $prerequisiteText) {
            return null;
        }

        // Pattern: "Xth level ClassName"
        if (preg_match('/\d+(?:st|nd|rd|th)\s+level\s+(\w+)/i', $prerequisiteText, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Look up spell school by code.
     *
     * @param  string  $code  School code (EV, EN, T, A, C, D, I, N)
     * @return int|null Spell school ID or null if not found
     */
    private function lookupSpellSchool(string $code): ?int
    {
        return $this->cachedFind(
            SpellSchool::class,
            'code',
            strtoupper($code),
            useFail: false
        )?->id;
    }

    public function getParser(): object
    {
        return new OptionalFeatureXmlParser;
    }
}
