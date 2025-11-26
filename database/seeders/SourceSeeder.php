<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the sources table with D&D 5th Edition sourcebooks.
 *
 * This seeder provides minimal source data for tests.
 * For production, use `php artisan import:sources` to import full source data from XML.
 */
class SourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('sources')->insert([
            // Core Rulebooks
            ['code' => 'PHB', 'name' => 'Player\'s Handbook', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014],
            ['code' => 'DMG', 'name' => 'Dungeon Master\'s Guide', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014],
            ['code' => 'MM', 'name' => 'Monster Manual', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014],

            // Major Expansions
            ['code' => 'XGE', 'name' => 'Xanathar\'s Guide to Everything', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2017],
            ['code' => 'TCE', 'name' => 'Tasha\'s Cauldron of Everything', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2020],
            ['code' => 'VGM', 'name' => 'Volo\'s Guide to Monsters', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2016],

            // Eberron Setting
            ['code' => 'ERLW', 'name' => 'Eberron: Rising from the Last War', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2019],

            // Forgotten Realms
            ['code' => 'SCAG', 'name' => 'Sword Coast Adventurer\'s Guide', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2015],

            // Adventures
            ['code' => 'TWBTW', 'name' => 'The Wild Beyond the Witchlight', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2021],
        ]);
    }
}
