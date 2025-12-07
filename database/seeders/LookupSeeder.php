<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds only lookup tables without entity fixtures.
 *
 * Use this seeder for tests that:
 * - Need lookup data (ability scores, spell schools, damage types, etc.)
 * - Create their own entity data via factories
 * - Don't want fixture data polluting their test state
 *
 * Usage in tests:
 *   protected $seed = true;
 *   protected $seeder = \Database\Seeders\LookupSeeder::class;
 */
class LookupSeeder extends Seeder
{
    public function run(): void
    {
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
            SenseSeeder::class,
            MulticlassSpellSlotSeeder::class,
        ]);
    }
}
