<?php

namespace Database\Seeders\Testing;

use App\Models\Background;
use App\Models\CharacterTrait;
use App\Models\EntitySource;
use App\Models\Item;
use App\Models\Proficiency;
use App\Models\Skill;
use App\Models\Source;

class BackgroundFixtureSeeder extends FixtureSeeder
{
    protected function fixturePath(): string
    {
        return 'tests/fixtures/entities/backgrounds.json';
    }

    protected function model(): string
    {
        return Background::class;
    }

    protected function createFromFixture(array $item): void
    {
        // Generate source-prefixed slug
        $slug = $item['slug'];
        if (! empty($item['source'])) {
            $sourceCode = strtolower($item['source']);
            $slug = $sourceCode.':'.$item['slug'];
        }

        // Create background
        $background = Background::create([
            'name' => $item['name'],
            'slug' => $slug,
        ]);

        // Handle skill proficiencies
        if (! empty($item['skill_proficiencies'])) {
            foreach ($item['skill_proficiencies'] as $skillProf) {
                $skill = null;
                if (! empty($skillProf['skill_slug'])) {
                    $skill = Skill::where('slug', $skillProf['skill_slug'])->first();
                }

                Proficiency::create([
                    'reference_type' => Background::class,
                    'reference_id' => $background->id,
                    'proficiency_type' => 'skill',
                    'skill_id' => $skill?->id,
                    'proficiency_name' => $skillProf['proficiency_name'],
                    'is_choice' => $skillProf['is_choice'] ?? false,
                    'quantity' => $skillProf['quantity'] ?? null,
                ]);
            }
        }

        // Handle tool proficiencies
        if (! empty($item['tool_proficiencies'])) {
            foreach ($item['tool_proficiencies'] as $toolProf) {
                $item = null;
                if (! empty($toolProf['item_slug'])) {
                    $item = Item::where('slug', $toolProf['item_slug'])->first();
                }

                Proficiency::create([
                    'reference_type' => Background::class,
                    'reference_id' => $background->id,
                    'proficiency_type' => $toolProf['proficiency_type'],
                    'proficiency_subcategory' => $toolProf['proficiency_subcategory'] ?? null,
                    'proficiency_name' => $toolProf['proficiency_name'],
                    'item_id' => $item?->id,
                    'is_choice' => $toolProf['is_choice'] ?? false,
                    'quantity' => $toolProf['quantity'] ?? null,
                ]);
            }
        }

        // Handle language proficiencies
        if (! empty($item['language_proficiencies'])) {
            foreach ($item['language_proficiencies'] as $langProf) {
                Proficiency::create([
                    'reference_type' => Background::class,
                    'reference_id' => $background->id,
                    'proficiency_type' => 'language',
                    'proficiency_name' => $langProf['proficiency_name'],
                    'is_choice' => $langProf['is_choice'] ?? false,
                    'quantity' => $langProf['quantity'] ?? null,
                ]);
            }
        }

        // Handle other proficiencies (armor, weapon, etc.)
        if (! empty($item['other_proficiencies'])) {
            foreach ($item['other_proficiencies'] as $otherProf) {
                Proficiency::create([
                    'reference_type' => Background::class,
                    'reference_id' => $background->id,
                    'proficiency_type' => $otherProf['proficiency_type'],
                    'proficiency_subcategory' => $otherProf['proficiency_subcategory'] ?? null,
                    'proficiency_name' => $otherProf['proficiency_name'],
                    'is_choice' => $otherProf['is_choice'] ?? false,
                    'quantity' => $otherProf['quantity'] ?? null,
                ]);
            }
        }

        // Handle background features (traits)
        if (! empty($item['features'])) {
            foreach ($item['features'] as $feature) {
                CharacterTrait::create([
                    'reference_type' => Background::class,
                    'reference_id' => $background->id,
                    'name' => $feature['name'],
                    'category' => $feature['category'],
                    'description' => $feature['description'],
                ]);
            }
        }

        // Create entity source (if source is provided)
        if (! empty($item['source'])) {
            $source = Source::where('code', $item['source'])->first();
            if ($source) {
                EntitySource::create([
                    'reference_type' => Background::class,
                    'reference_id' => $background->id,
                    'source_id' => $source->id,
                    'pages' => $item['pages'] ?? null,
                ]);
            }
        }
    }
}
