<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Order matters: dependencies first
        $seeders = [
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
        ];

        // SourceSeeder only runs in testing; production uses import:sources for full XML data
        if (App::environment('testing')) {
            array_unshift($seeders, SourceSeeder::class);
        }

        $this->call($seeders);
    }
}
