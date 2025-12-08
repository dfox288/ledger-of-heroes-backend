<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $languages = [
            // Standard Languages (PHB)
            [
                'name' => 'Common',
                'script' => 'Common',
                'typical_speakers' => 'Humans',
                'description' => 'The most widely spoken language in most D&D worlds',
            ],
            [
                'name' => 'Dwarvish',
                'script' => 'Dwarvish',
                'typical_speakers' => 'Dwarves',
                'description' => 'The language of dwarves, full of hard consonants and guttural sounds',
            ],
            [
                'name' => 'Elvish',
                'script' => 'Elvish',
                'typical_speakers' => 'Elves',
                'description' => 'The fluid and melodic language of elves',
            ],
            [
                'name' => 'Giant',
                'script' => 'Dwarvish',
                'typical_speakers' => 'Ogres, giants',
                'description' => 'The language of giants and their kin',
            ],
            [
                'name' => 'Gnomish',
                'script' => 'Dwarvish',
                'typical_speakers' => 'Gnomes',
                'description' => 'The language of gnomes, characterized by technical terms and precise vocabulary',
            ],
            [
                'name' => 'Goblin',
                'script' => 'Dwarvish',
                'typical_speakers' => 'Goblinoids',
                'description' => 'The harsh and grating language of goblins, hobgoblins, and bugbears',
            ],
            [
                'name' => 'Halfling',
                'script' => 'Common',
                'typical_speakers' => 'Halflings',
                'description' => 'The language of halflings',
            ],
            [
                'name' => 'Orc',
                'script' => 'Dwarvish',
                'typical_speakers' => 'Orcs',
                'description' => 'The harsh and brutal language of orcs',
            ],

            // Exotic Languages (PHB)
            [
                'name' => 'Abyssal',
                'script' => 'Infernal',
                'typical_speakers' => 'Demons',
                'description' => 'The chaotic language of the Abyss, spoken by demons',
            ],
            [
                'name' => 'Celestial',
                'script' => 'Celestial',
                'typical_speakers' => 'Celestials',
                'description' => 'The language of angels and other celestial beings',
            ],
            [
                'name' => 'Draconic',
                'script' => 'Draconic',
                'typical_speakers' => 'Dragons, dragonborn',
                'description' => 'The ancient language of dragons, also used by dragonborn and kobolds',
            ],
            [
                'name' => 'Deep Speech',
                'script' => null,
                'typical_speakers' => 'Aboleths, mind flayers',
                'description' => 'The alien language of aberrations and the Far Realm',
            ],
            [
                'name' => 'Infernal',
                'script' => 'Infernal',
                'typical_speakers' => 'Devils',
                'description' => 'The lawful language of the Nine Hells, spoken by devils',
            ],
            [
                'name' => 'Primordial',
                'script' => 'Dwarvish',
                'typical_speakers' => 'Elementals',
                'description' => 'The language of elementals, includes Aquan, Auran, Ignan, and Terran dialects',
            ],
            [
                'name' => 'Sylvan',
                'script' => 'Elvish',
                'typical_speakers' => 'Fey creatures',
                'description' => 'The language of the Feywild, spoken by fey creatures',
            ],
            [
                'name' => 'Undercommon',
                'script' => 'Elvish',
                'typical_speakers' => 'Underdark traders',
                'description' => 'The trade language of the Underdark',
            ],

            // Primordial Dialects
            [
                'name' => 'Aquan',
                'script' => 'Dwarvish',
                'typical_speakers' => 'Water elementals',
                'description' => 'The dialect of Primordial spoken by water elementals',
            ],
            [
                'name' => 'Auran',
                'script' => 'Dwarvish',
                'typical_speakers' => 'Air elementals',
                'description' => 'The dialect of Primordial spoken by air elementals',
            ],
            [
                'name' => 'Ignan',
                'script' => 'Dwarvish',
                'typical_speakers' => 'Fire elementals',
                'description' => 'The dialect of Primordial spoken by fire elementals',
            ],
            [
                'name' => 'Terran',
                'script' => 'Dwarvish',
                'typical_speakers' => 'Earth elementals',
                'description' => 'The dialect of Primordial spoken by earth elementals',
            ],

            // Secret Languages (not learnable - only granted by class features)
            [
                'name' => 'Druidic',
                'script' => 'Druidic',
                'typical_speakers' => 'Druids',
                'description' => 'The secret language of druids, forbidden to non-druids',
                'is_learnable' => false,
            ],
            [
                'name' => "Thieves' Cant",
                'script' => null,
                'typical_speakers' => 'Rogues',
                'description' => 'A secret mix of dialect, jargon, and code used by rogues',
                'is_learnable' => false,
            ],

            // Eberron Languages
            [
                'name' => 'Quori',
                'script' => null,
                'typical_speakers' => 'Quori, Kalashtar',
                'description' => 'The language of the quori, the evil spirits from Dal Quor',
            ],

            // Gith Languages (Mordenkainen's Tome of Foes)
            [
                'name' => 'Gith',
                'script' => null,
                'typical_speakers' => 'Githyanki, Githzerai',
                'description' => 'The language of the gith people',
            ],

            // Additional Exotic Languages
            [
                'name' => 'Aarakocra',
                'script' => null,
                'typical_speakers' => 'Aarakocra',
                'description' => 'The whistling language of the aarakocra',
            ],
            [
                'name' => 'Gnoll',
                'script' => null,
                'typical_speakers' => 'Gnolls',
                'description' => 'The savage language of gnolls',
            ],
            [
                'name' => 'Sahuagin',
                'script' => null,
                'typical_speakers' => 'Sahuagin',
                'description' => 'The language of the sahuagin',
            ],
            [
                'name' => 'Sphinx',
                'script' => null,
                'typical_speakers' => 'Sphinxes',
                'description' => 'The ancient language of sphinxes',
            ],
            [
                'name' => 'Worg',
                'script' => null,
                'typical_speakers' => 'Worgs',
                'description' => 'The language of worgs',
            ],

            // Dead Languages
            [
                'name' => 'Supernal',
                'script' => 'Celestial',
                'typical_speakers' => 'Upper planes natives',
                'description' => 'A dead celestial language, precursor to Celestial',
            ],
        ];

        foreach ($languages as $language) {
            $slug = Str::slug($language['name']);
            Language::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $language['name'],
                    'slug' => $slug,
                    'full_slug' => 'core:'.$slug,
                    'script' => $language['script'],
                    'typical_speakers' => $language['typical_speakers'],
                    'description' => $language['description'] ?? null,
                    'is_learnable' => $language['is_learnable'] ?? true,
                ]
            );
        }
    }
}
