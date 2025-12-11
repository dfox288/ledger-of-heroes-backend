<?php

namespace Database\Seeders;

use App\Models\Sense;
use Illuminate\Database\Seeder;

/**
 * Seeds the senses table with the 4 core D&D 5e sense types.
 *
 * This seeder populates/updates the senses table with source-prefixed slugs.
 * The base records may already exist from the create_senses_table migration.
 */
class SenseSeeder extends Seeder
{
    public function run(): void
    {
        $senses = [
            ['slug' => 'core:darkvision', 'name' => 'Darkvision'],
            ['slug' => 'core:blindsight', 'name' => 'Blindsight'],
            ['slug' => 'core:tremorsense', 'name' => 'Tremorsense'],
            ['slug' => 'core:truesight', 'name' => 'Truesight'],
        ];

        foreach ($senses as $sense) {
            Sense::updateOrCreate(
                ['slug' => $sense['slug']],
                ['name' => $sense['name']]
            );
        }
    }
}
