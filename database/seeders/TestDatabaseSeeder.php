<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TestDatabaseSeeder extends Seeder
{
    /**
     * Seed the test database with fixture data.
     *
     * Order matches import:all dependency chain:
     * 1. Lookups (sources, schools, damage types, etc.)
     * 2. Items (required by classes/backgrounds for equipment)
     * 3. Classes (required by spells for class lists)
     * 4. Spells
     * 5. Races
     * 6. Backgrounds
     * 7. Feats
     * 8. Monsters
     * 9. Optional Features
     */
    public function run(): void
    {
        // Step 1: Lookup tables (required by all entities)
        $this->call([
            SourceSeeder::class,
            SpellSchoolSeeder::class,
            DamageTypeSeeder::class,
            SizeSeeder::class,
            AbilityScoreSeeder::class,
            SkillSeeder::class,
            ItemTypeSeeder::class,
            ItemPropertySeeder::class,
            ConditionSeeder::class,
            ProficiencyTypeSeeder::class,
            LanguageSeeder::class,
            MulticlassSpellSlotSeeder::class,
        ]);

        // Step 2: Entity fixtures (order respects dependencies)
        $this->call([
            Testing\ItemFixtureSeeder::class,
            Testing\ClassFixtureSeeder::class,
            Testing\SpellFixtureSeeder::class,
            Testing\RaceFixtureSeeder::class,
            Testing\BackgroundFixtureSeeder::class,
            Testing\FeatFixtureSeeder::class,
            Testing\MonsterFixtureSeeder::class,
            Testing\OptionalFeatureFixtureSeeder::class,
        ]);

        // Step 3: Index searchable models for Meilisearch
        $this->indexSearchableModels();
    }

    /**
     * Index all searchable models for Meilisearch.
     * Called after entity fixtures are seeded.
     */
    protected function indexSearchableModels(): void
    {
        $models = [
            \App\Models\Spell::class,
            \App\Models\Monster::class,
            \App\Models\CharacterClass::class,
            \App\Models\Race::class,
            \App\Models\Item::class,
            \App\Models\Feat::class,
            \App\Models\Background::class,
            \App\Models\OptionalFeature::class,
        ];

        foreach ($models as $model) {
            if ($model::count() > 0) {
                $model::all()->searchable();
            }
        }
    }
}
