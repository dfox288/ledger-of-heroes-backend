<?php

namespace Database\Seeders\Testing;

use App\Models\CharacterClass;
use App\Models\EntityPrerequisite;
use App\Models\EntitySource;
use App\Models\OptionalFeature;
use App\Models\Source;
use App\Models\SpellSchool;

class OptionalFeatureFixtureSeeder extends FixtureSeeder
{
    protected function fixturePath(): string
    {
        return 'tests/fixtures/entities/optionalfeatures.json';
    }

    protected function model(): string
    {
        return OptionalFeature::class;
    }

    protected function createFromFixture(array $item): void
    {
        // Resolve spell school by code
        $spellSchoolId = null;
        if (! empty($item['spell_school'])) {
            $spellSchool = SpellSchool::where('code', $item['spell_school'])->first();
            $spellSchoolId = $spellSchool?->id;
        }

        // Generate full_slug from source (if available)
        $fullSlug = null;
        if (! empty($item['source'])) {
            $sourceCode = strtolower($item['source']);
            $fullSlug = $sourceCode.':'.$item['slug'];
        }

        // Create optional feature
        $feature = OptionalFeature::create([
            'name' => $item['name'],
            'slug' => $item['slug'],
            'full_slug' => $fullSlug,
            'feature_type' => $item['feature_type'],
            'level_requirement' => $item['level_requirement'],
            'prerequisite_text' => $item['prerequisite_text'],
            'description' => $item['description'],
            'casting_time' => $item['casting_time'],
            'range' => $item['range'],
            'duration' => $item['duration'],
            'spell_school_id' => $spellSchoolId,
            'resource_type' => $item['resource_type'],
            'resource_cost' => $item['resource_cost'],
        ]);

        // Create class associations
        if (! empty($item['classes'])) {
            foreach ($item['classes'] as $classSlug) {
                $class = CharacterClass::where('slug', $classSlug)->first();
                if ($class) {
                    // Determine subclass_name from the subclass_names array
                    // If there are subclass_names, use the first one for each class association
                    $subclassName = ! empty($item['subclass_names']) ? $item['subclass_names'][0] : null;

                    $feature->classes()->attach($class->id, [
                        'subclass_name' => $subclassName,
                    ]);
                }
            }
        }

        // Create prerequisites
        if (! empty($item['prerequisites'])) {
            foreach ($item['prerequisites'] as $prerequisite) {
                $this->createPrerequisite($feature, $prerequisite);
            }
        }

        // Create entity source
        if (! empty($item['source'])) {
            $source = Source::where('code', $item['source'])->first();
            if ($source) {
                EntitySource::create([
                    'reference_type' => OptionalFeature::class,
                    'reference_id' => $feature->id,
                    'source_id' => $source->id,
                    'pages' => $item['pages'] ?? null,
                ]);
            }
        }
    }

    /**
     * Create a prerequisite relationship for the optional feature.
     */
    protected function createPrerequisite(OptionalFeature $feature, array $prerequisite): void
    {
        $prerequisiteType = null;
        $prerequisiteId = null;

        // Resolve prerequisite based on type
        switch ($prerequisite['type']) {
            case 'CharacterClass':
                $prerequisiteType = CharacterClass::class;
                $class = CharacterClass::where('slug', $prerequisite['value'])->first();
                $prerequisiteId = $class?->id;
                break;

            case 'Spell':
                $prerequisiteType = \App\Models\Spell::class;
                $spell = \App\Models\Spell::where('slug', $prerequisite['value'])->first();
                $prerequisiteId = $spell?->id;
                break;

            case 'AbilityScore':
                $prerequisiteType = \App\Models\AbilityScore::class;
                $abilityScore = \App\Models\AbilityScore::where('code', $prerequisite['value'])->first();
                $prerequisiteId = $abilityScore?->id;
                break;
        }

        // Only create prerequisite if we found the referenced entity
        if ($prerequisiteType && $prerequisiteId) {
            EntityPrerequisite::create([
                'reference_type' => OptionalFeature::class,
                'reference_id' => $feature->id,
                'prerequisite_type' => $prerequisiteType,
                'prerequisite_id' => $prerequisiteId,
                'minimum_value' => $prerequisite['minimum_value'] ?? null,
                'description' => $prerequisite['description'] ?? null,
            ]);
        }
    }
}
