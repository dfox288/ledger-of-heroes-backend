<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the sources table with D&D 5th Edition sourcebooks.
 *
 * This seeder populates core sourcebooks including Player's Handbook,
 * Dungeon Master's Guide, Monster Manual, and major expansion books.
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
            ['code' => 'PHB', 'name' => 'Player\'s Handbook', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014, 'edition' => '5e'],
            ['code' => 'DMG', 'name' => 'Dungeon Master\'s Guide', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014, 'edition' => '5e'],
            ['code' => 'MM', 'name' => 'Monster Manual', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014, 'edition' => '5e'],

            // Major Expansions
            ['code' => 'XGE', 'name' => 'Xanathar\'s Guide to Everything', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2017, 'edition' => '5e'],
            ['code' => 'TCE', 'name' => 'Tasha\'s Cauldron of Everything', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2020, 'edition' => '5e'],
            ['code' => 'VGTM', 'name' => 'Volo\'s Guide to Monsters', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2016, 'edition' => '5e'],

            // Eberron Setting
            ['code' => 'ERLW', 'name' => 'Eberron: Rising from the Last War', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2019, 'edition' => '5e'],
            ['code' => 'WGTE', 'name' => 'Wayfinder\'s Guide to Eberron', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2018, 'edition' => '5e'],
        ]);
    }
}
