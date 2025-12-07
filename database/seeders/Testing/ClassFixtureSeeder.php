<?php

namespace Database\Seeders\Testing;

use App\Models\AbilityScore;
use App\Models\CharacterClass;
use App\Models\EntitySource;
use App\Models\Item;
use App\Models\Proficiency;
use App\Models\Skill;
use App\Models\Source;

class ClassFixtureSeeder extends FixtureSeeder
{
    protected function fixturePath(): string
    {
        return 'tests/fixtures/entities/classes.json';
    }

    protected function model(): string
    {
        return CharacterClass::class;
    }

    protected function createFromFixture(array $item): void
    {
        // Resolve spellcasting ability by code
        $spellcastingAbility = null;
        if (! empty($item['spellcasting_ability'])) {
            $spellcastingAbility = AbilityScore::where('code', $item['spellcasting_ability'])->first();
        }

        // Resolve parent class by slug (if this is a subclass)
        $parentClass = null;
        if (! empty($item['parent_class_slug'])) {
            $parentClass = CharacterClass::where('slug', $item['parent_class_slug'])->first();
        }

        // Generate full_slug from source (if available)
        $fullSlug = null;
        if (! empty($item['source'])) {
            $sourceCode = strtolower($item['source']);
            $fullSlug = $sourceCode.':'.$item['slug'];
        }

        // Create character class
        $class = CharacterClass::create([
            'name' => $item['name'],
            'slug' => $item['slug'],
            'full_slug' => $fullSlug,
            'hit_die' => $item['hit_die'],
            'description' => $item['description'],
            'primary_ability' => $item['primary_ability'],
            'spellcasting_ability_id' => $spellcastingAbility?->id,
            'parent_class_id' => $parentClass?->id,
        ]);

        // Create proficiencies
        if (! empty($item['proficiencies'])) {
            foreach ($item['proficiencies'] as $profData) {
                // Resolve skill by code
                $skill = null;
                if (! empty($profData['skill_code'])) {
                    $skill = Skill::where('code', $profData['skill_code'])->first();
                }

                // Resolve ability score by code
                $abilityScore = null;
                if (! empty($profData['ability_code'])) {
                    $abilityScore = AbilityScore::where('code', $profData['ability_code'])->first();
                }

                // Resolve item by slug
                $profItem = null;
                if (! empty($profData['item_slug'])) {
                    $profItem = Item::where('slug', $profData['item_slug'])->first();
                }

                Proficiency::create([
                    'reference_type' => CharacterClass::class,
                    'reference_id' => $class->id,
                    'proficiency_type' => $profData['proficiency_type'],
                    'proficiency_subcategory' => $profData['proficiency_subcategory'],
                    'proficiency_name' => $profData['proficiency_name'],
                    'skill_id' => $skill?->id,
                    'ability_score_id' => $abilityScore?->id,
                    'item_id' => $profItem?->id,
                    'grants' => $profData['grants'],
                    'is_choice' => $profData['is_choice'],
                    'choice_group' => $profData['choice_group'],
                    'choice_option' => $profData['choice_option'],
                    'quantity' => $profData['quantity'],
                    'level' => $profData['level'],
                ]);
            }
        }

        // Create entity source (if source is provided)
        if (! empty($item['source'])) {
            $source = Source::where('code', $item['source'])->first();
            if ($source) {
                EntitySource::create([
                    'reference_type' => CharacterClass::class,
                    'reference_id' => $class->id,
                    'source_id' => $source->id,
                    'pages' => $item['pages'],
                ]);
            }
        }
    }
}
