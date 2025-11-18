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
        $this->call([
            SourceSeeder::class,
            SpellSchoolSeeder::class,
            DamageTypeSeeder::class,
            SizeSeeder::class,
            AbilityScoreSeeder::class,
            SkillSeeder::class,              // Depends on AbilityScore
            ItemTypeSeeder::class,
            ItemPropertySeeder::class,
            CharacterClassSeeder::class,     // Depends on AbilityScore and Source
            ConditionSeeder::class,          // No dependencies
            ProficiencyTypeSeeder::class,    // No dependencies
        ]);
    }
}
