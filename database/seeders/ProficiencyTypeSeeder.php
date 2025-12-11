<?php

namespace Database\Seeders;

use App\Models\ProficiencyType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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
            ['name' => 'Simple Weapons', 'category' => 'weapon', 'subcategory' => 'simple'],
            ['name' => 'Martial Weapons', 'category' => 'weapon', 'subcategory' => 'martial'],
            ['name' => 'Firearms', 'category' => 'weapon', 'subcategory' => 'firearm'],

            // Weapons - Simple Melee
            ['name' => 'Club', 'category' => 'weapon', 'subcategory' => 'simple_melee'],
            ['name' => 'Dagger', 'category' => 'weapon', 'subcategory' => 'simple_melee'],
            ['name' => 'Greatclub', 'category' => 'weapon', 'subcategory' => 'simple_melee'],
            ['name' => 'Handaxe', 'category' => 'weapon', 'subcategory' => 'simple_melee'],
            ['name' => 'Javelin', 'category' => 'weapon', 'subcategory' => 'simple_melee'],
            ['name' => 'Light Hammer', 'category' => 'weapon', 'subcategory' => 'simple_melee'],
            ['name' => 'Mace', 'category' => 'weapon', 'subcategory' => 'simple_melee'],
            ['name' => 'Quarterstaff', 'category' => 'weapon', 'subcategory' => 'simple_melee'],
            ['name' => 'Sickle', 'category' => 'weapon', 'subcategory' => 'simple_melee'],
            ['name' => 'Spear', 'category' => 'weapon', 'subcategory' => 'simple_melee'],

            // Weapons - Simple Ranged
            ['name' => 'Light Crossbow', 'category' => 'weapon', 'subcategory' => 'simple_ranged'],
            ['name' => 'Dart', 'category' => 'weapon', 'subcategory' => 'simple_ranged'],
            ['name' => 'Shortbow', 'category' => 'weapon', 'subcategory' => 'simple_ranged'],
            ['name' => 'Sling', 'category' => 'weapon', 'subcategory' => 'simple_ranged'],

            // Weapons - Martial Melee
            ['name' => 'Battleaxe', 'category' => 'weapon', 'subcategory' => 'martial_melee'],
            ['name' => 'Flail', 'category' => 'weapon', 'subcategory' => 'martial_melee'],
            ['name' => 'Glaive', 'category' => 'weapon', 'subcategory' => 'martial_melee'],
            ['name' => 'Greataxe', 'category' => 'weapon', 'subcategory' => 'martial_melee'],
            ['name' => 'Greatsword', 'category' => 'weapon', 'subcategory' => 'martial_melee'],
            ['name' => 'Halberd', 'category' => 'weapon', 'subcategory' => 'martial_melee'],
            ['name' => 'Lance', 'category' => 'weapon', 'subcategory' => 'martial_melee'],
            ['name' => 'Longsword', 'category' => 'weapon', 'subcategory' => 'martial_melee'],
            ['name' => 'Maul', 'category' => 'weapon', 'subcategory' => 'martial_melee'],
            ['name' => 'Morningstar', 'category' => 'weapon', 'subcategory' => 'martial_melee'],
            ['name' => 'Pike', 'category' => 'weapon', 'subcategory' => 'martial_melee'],
            ['name' => 'Rapier', 'category' => 'weapon', 'subcategory' => 'martial_melee'],
            ['name' => 'Scimitar', 'category' => 'weapon', 'subcategory' => 'martial_melee'],
            ['name' => 'Shortsword', 'category' => 'weapon', 'subcategory' => 'martial_melee'],
            ['name' => 'Trident', 'category' => 'weapon', 'subcategory' => 'martial_melee'],
            ['name' => 'War Pick', 'category' => 'weapon', 'subcategory' => 'martial_melee'],
            ['name' => 'Warhammer', 'category' => 'weapon', 'subcategory' => 'martial_melee'],
            ['name' => 'Whip', 'category' => 'weapon', 'subcategory' => 'martial_melee'],

            // Weapons - Martial Ranged
            ['name' => 'Blowgun', 'category' => 'weapon', 'subcategory' => 'martial_ranged'],
            ['name' => 'Hand Crossbow', 'category' => 'weapon', 'subcategory' => 'martial_ranged'],
            ['name' => 'Heavy Crossbow', 'category' => 'weapon', 'subcategory' => 'martial_ranged'],
            ['name' => 'Longbow', 'category' => 'weapon', 'subcategory' => 'martial_ranged'],
            ['name' => 'Net', 'category' => 'weapon', 'subcategory' => 'martial_ranged'],

            // Weapons - Special/Exotic
            ['name' => 'Double-Bladed Scimitar', 'category' => 'weapon', 'subcategory' => 'martial_melee'],

            // Artisan's Tools (subcategory: artisan)
            ['name' => "Alchemist's Supplies", 'category' => 'tool', 'subcategory' => 'artisan'],
            ['name' => "Brewer's Supplies", 'category' => 'tool', 'subcategory' => 'artisan'],
            ['name' => "Calligrapher's Supplies", 'category' => 'tool', 'subcategory' => 'artisan'],
            ['name' => "Carpenter's Tools", 'category' => 'tool', 'subcategory' => 'artisan'],
            ['name' => "Cartographer's Tools", 'category' => 'tool', 'subcategory' => 'artisan'],
            ['name' => "Cobbler's Tools", 'category' => 'tool', 'subcategory' => 'artisan'],
            ['name' => "Cook's Utensils", 'category' => 'tool', 'subcategory' => 'artisan'],
            ['name' => "Glassblower's Tools", 'category' => 'tool', 'subcategory' => 'artisan'],
            ['name' => "Jeweler's Tools", 'category' => 'tool', 'subcategory' => 'artisan'],
            ['name' => "Leatherworker's Tools", 'category' => 'tool', 'subcategory' => 'artisan'],
            ['name' => "Mason's Tools", 'category' => 'tool', 'subcategory' => 'artisan'],
            ['name' => "Painter's Supplies", 'category' => 'tool', 'subcategory' => 'artisan'],
            ['name' => "Potter's Tools", 'category' => 'tool', 'subcategory' => 'artisan'],
            ['name' => "Smith's Tools", 'category' => 'tool', 'subcategory' => 'artisan'],
            ['name' => "Tinker's Tools", 'category' => 'tool', 'subcategory' => 'artisan'],
            ['name' => "Weaver's Tools", 'category' => 'tool', 'subcategory' => 'artisan'],
            ['name' => "Woodcarver's Tools", 'category' => 'tool', 'subcategory' => 'artisan'],

            // Miscellaneous Tools (subcategory: misc)
            ['name' => 'Disguise Kit', 'category' => 'tool', 'subcategory' => 'misc'],
            ['name' => 'Forgery Kit', 'category' => 'tool', 'subcategory' => 'misc'],
            ['name' => 'Herbalism Kit', 'category' => 'tool', 'subcategory' => 'misc'],
            ['name' => "Navigator's Tools", 'category' => 'tool', 'subcategory' => 'misc'],
            ['name' => "Poisoner's Kit", 'category' => 'tool', 'subcategory' => 'misc'],
            ['name' => "Thieves' Tools", 'category' => 'tool', 'subcategory' => 'misc'],

            // Vehicles
            ['name' => 'Land Vehicles', 'category' => 'vehicle'],
            ['name' => 'Water Vehicles', 'category' => 'vehicle'],

            // Gaming Sets (category already specific, no subcategory needed)
            ['name' => 'Dice Set', 'category' => 'gaming_set'],
            ['name' => 'Dragonchess Set', 'category' => 'gaming_set'],
            ['name' => 'Playing Card Set', 'category' => 'gaming_set'],
            ['name' => 'Three-Dragon Ante Set', 'category' => 'gaming_set'],

            // Musical Instruments - Parent category (grants proficiency in ALL musical instruments)
            // This is used when a class/race grants "Musical Instruments" proficiency broadly
            ['name' => 'Musical Instruments', 'category' => 'musical_instrument'],

            // Musical Instruments - Individual instruments (category: tool, subcategory: musical_instrument)
            // This matches the pattern used for artisan tools and allows lookups to work correctly
            // Query: ?category=tool&subcategory=musical_instrument returns all individual instruments
            ['name' => 'Bagpipes', 'category' => 'tool', 'subcategory' => 'musical_instrument'],
            ['name' => 'Drum', 'category' => 'tool', 'subcategory' => 'musical_instrument'],
            ['name' => 'Dulcimer', 'category' => 'tool', 'subcategory' => 'musical_instrument'],
            ['name' => 'Flute', 'category' => 'tool', 'subcategory' => 'musical_instrument'],
            ['name' => 'Horn', 'category' => 'tool', 'subcategory' => 'musical_instrument'],
            ['name' => 'Lute', 'category' => 'tool', 'subcategory' => 'musical_instrument'],
            ['name' => 'Lyre', 'category' => 'tool', 'subcategory' => 'musical_instrument'],
            ['name' => 'Pan Flute', 'category' => 'tool', 'subcategory' => 'musical_instrument'],
            ['name' => 'Shawm', 'category' => 'tool', 'subcategory' => 'musical_instrument'],
            ['name' => 'Viol', 'category' => 'tool', 'subcategory' => 'musical_instrument'],
        ];

        foreach ($proficiencyTypes as $type) {
            // Auto-generate source-prefixed slug from name
            $type['slug'] = 'core:'.Str::slug($type['name']);

            // Use slug as the unique key for updateOrCreate
            // This ensures we update existing records when category changes
            ProficiencyType::updateOrCreate(
                ['slug' => $type['slug']],
                $type
            );
        }
    }
}
