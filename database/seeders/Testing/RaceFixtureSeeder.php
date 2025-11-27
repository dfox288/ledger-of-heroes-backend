<?php

namespace Database\Seeders\Testing;

use App\Models\AbilityScore;
use App\Models\CharacterTrait;
use App\Models\EntitySource;
use App\Models\Modifier;
use App\Models\Race;
use App\Models\Size;
use App\Models\Source;

class RaceFixtureSeeder extends FixtureSeeder
{
    protected function fixturePath(): string
    {
        return 'tests/fixtures/entities/races.json';
    }

    protected function model(): string
    {
        return Race::class;
    }

    protected function createFromFixture(array $item): void
    {
        // Resolve size by code
        $size = Size::where('code', $item['size'])->first();

        // Resolve parent race by slug (if exists)
        $parentRace = null;
        if (! empty($item['parent_race_slug'])) {
            $parentRace = Race::where('slug', $item['parent_race_slug'])->first();
        }

        // Create race
        $race = Race::create([
            'name' => $item['name'],
            'slug' => $item['slug'],
            'size_id' => $size?->id,
            'speed' => $item['speed'],
            'parent_race_id' => $parentRace?->id,
        ]);

        // Handle ability bonuses (create Modifiers)
        if (! empty($item['ability_bonuses'])) {
            foreach ($item['ability_bonuses'] as $bonus) {
                $abilityScore = null;
                if (! empty($bonus['ability'])) {
                    $abilityScore = AbilityScore::where('code', $bonus['ability'])->first();
                }

                Modifier::create([
                    'reference_type' => Race::class,
                    'reference_id' => $race->id,
                    'modifier_category' => 'ability_score',
                    'ability_score_id' => $abilityScore?->id,
                    'value' => $bonus['bonus'],
                    'is_choice' => $bonus['is_choice'] ?? false,
                ]);
            }
        }

        // Handle traits (create CharacterTraits)
        if (! empty($item['traits'])) {
            foreach ($item['traits'] as $trait) {
                CharacterTrait::create([
                    'reference_type' => Race::class,
                    'reference_id' => $race->id,
                    'name' => $trait['name'],
                    'category' => $trait['category'],
                    'description' => $trait['description'],
                ]);
            }
        }

        // Create entity source (if source is provided)
        if (! empty($item['source'])) {
            $source = Source::where('code', $item['source'])->first();
            if ($source) {
                EntitySource::create([
                    'reference_type' => Race::class,
                    'reference_id' => $race->id,
                    'source_id' => $source->id,
                    'pages' => $item['pages'] ?? null,
                ]);
            }
        }
    }
}
