<?php

namespace Database\Seeders;

use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\Feat;
use App\Models\Item;
use App\Models\Monster;
use App\Models\OptionalFeature;
use App\Models\Race;
use App\Models\Spell;
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
     *
     * Flushes each index first so fixture tests don't inherit stale documents
     * from a previous `just import-test` run or a prior fixture seed — the
     * test_* indexes are shared across invocations and Scout's searchable()
     * only adds records, it doesn't remove orphans.
     */
    protected function indexSearchableModels(): void
    {
        $models = [
            Spell::class,
            Monster::class,
            CharacterClass::class,
            Race::class,
            Item::class,
            Feat::class,
            Background::class,
            OptionalFeature::class,
        ];

        foreach ($models as $model) {
            $model::removeAllFromSearch();
            if ($model::count() > 0) {
                $model::all()->searchable();
            }
        }
    }
}
