<?php

namespace Database\Seeders;

use App\Models\Sense;
use Illuminate\Database\Seeder;

/**
 * Seeds the senses table with the 4 core D&D 5e sense types.
 *
 * This seeder populates/updates the senses table with full_slug values.
 * The base records may already exist from the create_senses_table migration.
 */
class SenseSeeder extends Seeder
{
    public function run(): void
    {
        $senses = [
            ['slug' => 'darkvision', 'name' => 'Darkvision'],
            ['slug' => 'blindsight', 'name' => 'Blindsight'],
            ['slug' => 'tremorsense', 'name' => 'Tremorsense'],
            ['slug' => 'truesight', 'name' => 'Truesight'],
        ];

        foreach ($senses as $sense) {
            Sense::updateOrCreate(
                ['slug' => $sense['slug']],
                [
                    'name' => $sense['name'],
                    'full_slug' => 'core:'.$sense['slug'],
                ]
            );
        }
    }
}
