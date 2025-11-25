<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Order matters: dependencies first
        // Note: SourceSeeder removed - sources are imported from XML files (import:sources)
        $this->call([
            SpellSchoolSeeder::class,
            DamageTypeSeeder::class,
            SizeSeeder::class,
            AbilityScoreSeeder::class,
            SkillSeeder::class,              // Depends on AbilityScore
            ItemTypeSeeder::class,
            ItemPropertySeeder::class,
            ConditionSeeder::class,          // No dependencies
            ProficiencyTypeSeeder::class,    // No dependencies
            LanguageSeeder::class,           // No dependencies
            // Note: CharacterClassSeeder removed - classes are imported from XML files
        ]);
    }
}
