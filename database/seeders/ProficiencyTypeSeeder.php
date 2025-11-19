<?php

namespace Database\Seeders;

use App\Models\ProficiencyType;
use Illuminate\Database\Seeder;

class ProficiencyTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $proficiencyTypes = [
            // Armor
            ['name' => 'Light Armor', 'category' => 'armor'],
            ['name' => 'Medium Armor', 'category' => 'armor'],
            ['name' => 'Heavy Armor', 'category' => 'armor'],
            ['name' => 'Shields', 'category' => 'armor'],

            // Weapons - Categories
            ['name' => 'Simple Weapons', 'category' => 'weapon'],
            ['name' => 'Martial Weapons', 'category' => 'weapon'],
            ['name' => 'Firearms', 'category' => 'weapon'],

            // Weapons - Simple Melee
            ['name' => 'Club', 'category' => 'weapon'],
            ['name' => 'Dagger', 'category' => 'weapon'],
            ['name' => 'Greatclub', 'category' => 'weapon'],
            ['name' => 'Handaxe', 'category' => 'weapon'],
            ['name' => 'Javelin', 'category' => 'weapon'],
            ['name' => 'Light Hammer', 'category' => 'weapon'],
            ['name' => 'Mace', 'category' => 'weapon'],
            ['name' => 'Quarterstaff', 'category' => 'weapon'],
            ['name' => 'Sickle', 'category' => 'weapon'],
            ['name' => 'Spear', 'category' => 'weapon'],

            // Weapons - Simple Ranged
            ['name' => 'Light Crossbow', 'category' => 'weapon'],
            ['name' => 'Dart', 'category' => 'weapon'],
            ['name' => 'Shortbow', 'category' => 'weapon'],
            ['name' => 'Sling', 'category' => 'weapon'],

            // Weapons - Martial Melee
            ['name' => 'Battleaxe', 'category' => 'weapon'],
            ['name' => 'Flail', 'category' => 'weapon'],
            ['name' => 'Glaive', 'category' => 'weapon'],
            ['name' => 'Greataxe', 'category' => 'weapon'],
            ['name' => 'Greatsword', 'category' => 'weapon'],
            ['name' => 'Halberd', 'category' => 'weapon'],
            ['name' => 'Lance', 'category' => 'weapon'],
            ['name' => 'Longsword', 'category' => 'weapon'],
            ['name' => 'Maul', 'category' => 'weapon'],
            ['name' => 'Morningstar', 'category' => 'weapon'],
            ['name' => 'Pike', 'category' => 'weapon'],
            ['name' => 'Rapier', 'category' => 'weapon'],
            ['name' => 'Scimitar', 'category' => 'weapon'],
            ['name' => 'Shortsword', 'category' => 'weapon'],
            ['name' => 'Trident', 'category' => 'weapon'],
            ['name' => 'War Pick', 'category' => 'weapon'],
            ['name' => 'Warhammer', 'category' => 'weapon'],
            ['name' => 'Whip', 'category' => 'weapon'],

            // Weapons - Martial Ranged
            ['name' => 'Blowgun', 'category' => 'weapon'],
            ['name' => 'Hand Crossbow', 'category' => 'weapon'],
            ['name' => 'Heavy Crossbow', 'category' => 'weapon'],
            ['name' => 'Longbow', 'category' => 'weapon'],
            ['name' => 'Net', 'category' => 'weapon'],

            // Weapons - Special/Exotic
            ['name' => 'Double-Bladed Scimitar', 'category' => 'weapon'],

            // Artisan's Tools
            ['name' => "Alchemist's Supplies", 'category' => 'tool'],
            ['name' => "Brewer's Supplies", 'category' => 'tool'],
            ['name' => "Calligrapher's Supplies", 'category' => 'tool'],
            ['name' => "Carpenter's Tools", 'category' => 'tool'],
            ['name' => "Cartographer's Tools", 'category' => 'tool'],
            ['name' => "Cobbler's Tools", 'category' => 'tool'],
            ['name' => "Cook's Utensils", 'category' => 'tool'],
            ['name' => "Glassblower's Tools", 'category' => 'tool'],
            ['name' => "Jeweler's Tools", 'category' => 'tool'],
            ['name' => "Leatherworker's Tools", 'category' => 'tool'],
            ['name' => "Mason's Tools", 'category' => 'tool'],
            ['name' => "Painter's Supplies", 'category' => 'tool'],
            ['name' => "Potter's Tools", 'category' => 'tool'],
            ['name' => "Smith's Tools", 'category' => 'tool'],
            ['name' => "Tinker's Tools", 'category' => 'tool'],
            ['name' => "Weaver's Tools", 'category' => 'tool'],
            ['name' => "Woodcarver's Tools", 'category' => 'tool'],

            // Other Tools
            ['name' => 'Disguise Kit', 'category' => 'tool'],
            ['name' => 'Forgery Kit', 'category' => 'tool'],
            ['name' => 'Herbalism Kit', 'category' => 'tool'],
            ['name' => "Navigator's Tools", 'category' => 'tool'],
            ['name' => "Poisoner's Kit", 'category' => 'tool'],
            ['name' => "Thieves' Tools", 'category' => 'tool'],

            // Vehicles
            ['name' => 'Land Vehicles', 'category' => 'vehicle'],
            ['name' => 'Water Vehicles', 'category' => 'vehicle'],

            // Gaming Sets
            ['name' => 'Dice Set', 'category' => 'gaming_set'],
            ['name' => 'Playing Card Set', 'category' => 'gaming_set'],

            // Musical Instruments
            ['name' => 'Bagpipes', 'category' => 'musical_instrument'],
            ['name' => 'Drum', 'category' => 'musical_instrument'],
            ['name' => 'Dulcimer', 'category' => 'musical_instrument'],
            ['name' => 'Flute', 'category' => 'musical_instrument'],
            ['name' => 'Lute', 'category' => 'musical_instrument'],
            ['name' => 'Lyre', 'category' => 'musical_instrument'],
            ['name' => 'Horn', 'category' => 'musical_instrument'],
            ['name' => 'Pan Flute', 'category' => 'musical_instrument'],
            ['name' => 'Shawm', 'category' => 'musical_instrument'],
            ['name' => 'Viol', 'category' => 'musical_instrument'],
        ];

        foreach ($proficiencyTypes as $type) {
            ProficiencyType::updateOrCreate(
                ['name' => $type['name'], 'category' => $type['category']],
                $type
            );
        }
    }
}
