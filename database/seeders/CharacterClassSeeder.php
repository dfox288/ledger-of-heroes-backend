<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the classes table with the 13 core D&D character classes.
 *
 * This seeder populates all base character classes from the Player's Handbook
 * and Tasha's Cauldron of Everything (Artificer). Each class includes hit die,
 * description, primary ability, spellcasting ability (if applicable), and
 * source reference.
 *
 * Note: This seeder depends on SourceSeeder and AbilityScoreSeeder running first.
 */
class CharacterClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get PHB source ID for entity_sources
        $phbSourceId = DB::table('sources')->where('code', 'PHB')->value('id');

        // Get ability score IDs for spellcasting abilities
        $intId = DB::table('ability_scores')->where('code', 'INT')->value('id');
        $wisId = DB::table('ability_scores')->where('code', 'WIS')->value('id');
        $chaId = DB::table('ability_scores')->where('code', 'CHA')->value('id');

        // Define classes with page numbers for later source association
        $classes = [
            ['slug' => 'artificer', 'name' => 'Artificer', 'hit_die' => 8, 'description' => 'Masters of invention, artificers use ingenuity and magic to unlock extraordinary capabilities in objects.', 'primary_ability' => 'Intelligence', 'spellcasting_ability_id' => $intId, 'pages' => '0'],
            ['slug' => 'barbarian', 'name' => 'Barbarian', 'hit_die' => 12, 'description' => 'A fierce warrior of primitive background who can enter a battle rage.', 'primary_ability' => 'Strength', 'spellcasting_ability_id' => null, 'pages' => '46'],
            ['slug' => 'bard', 'name' => 'Bard', 'hit_die' => 8, 'description' => 'An inspiring magician whose power echoes the music of creation.', 'primary_ability' => 'Charisma', 'spellcasting_ability_id' => $chaId, 'pages' => '51'],
            ['slug' => 'cleric', 'name' => 'Cleric', 'hit_die' => 8, 'description' => 'A priestly champion who wields divine magic in service of a higher power.', 'primary_ability' => 'Wisdom', 'spellcasting_ability_id' => $wisId, 'pages' => '56'],
            ['slug' => 'druid', 'name' => 'Druid', 'hit_die' => 8, 'description' => 'A priest of the Old Faith, wielding the powers of nature and adopting animal forms.', 'primary_ability' => 'Wisdom', 'spellcasting_ability_id' => $wisId, 'pages' => '64'],
            ['slug' => 'fighter', 'name' => 'Fighter', 'hit_die' => 10, 'description' => 'A master of martial combat, skilled with a variety of weapons and armor.', 'primary_ability' => 'Strength or Dexterity', 'spellcasting_ability_id' => null, 'pages' => '70'],
            ['slug' => 'monk', 'name' => 'Monk', 'hit_die' => 8, 'description' => 'A master of martial arts, harnessing the power of the body in pursuit of physical and spiritual perfection.', 'primary_ability' => 'Dexterity and Wisdom', 'spellcasting_ability_id' => null, 'pages' => '76'],
            ['slug' => 'paladin', 'name' => 'Paladin', 'hit_die' => 10, 'description' => 'A holy warrior bound to a sacred oath.', 'primary_ability' => 'Strength and Charisma', 'spellcasting_ability_id' => $chaId, 'pages' => '82'],
            ['slug' => 'ranger', 'name' => 'Ranger', 'hit_die' => 10, 'description' => 'A warrior who uses martial prowess and nature magic to combat threats on the edges of civilization.', 'primary_ability' => 'Dexterity and Wisdom', 'spellcasting_ability_id' => $wisId, 'pages' => '89'],
            ['slug' => 'rogue', 'name' => 'Rogue', 'hit_die' => 8, 'description' => 'A scoundrel who uses stealth and trickery to overcome obstacles and enemies.', 'primary_ability' => 'Dexterity', 'spellcasting_ability_id' => null, 'pages' => '94'],
            ['slug' => 'sorcerer', 'name' => 'Sorcerer', 'hit_die' => 6, 'description' => 'A spellcaster who draws on inherent magic from a gift or bloodline.', 'primary_ability' => 'Charisma', 'spellcasting_ability_id' => $chaId, 'pages' => '99'],
            ['slug' => 'warlock', 'name' => 'Warlock', 'hit_die' => 8, 'description' => 'A wielder of magic that is derived from a bargain with an extraplanar entity.', 'primary_ability' => 'Charisma', 'spellcasting_ability_id' => $chaId, 'pages' => '105'],
            ['slug' => 'wizard', 'name' => 'Wizard', 'hit_die' => 6, 'description' => 'A scholarly magic-user capable of manipulating the structures of reality.', 'primary_ability' => 'Intelligence', 'spellcasting_ability_id' => $intId, 'pages' => '112'],
        ];

        // Insert classes and create entity_sources
        foreach ($classes as $classData) {
            $pages = $classData['pages'];
            unset($classData['pages']);
            $classData['parent_class_id'] = null;

            // Insert class
            $classId = DB::table('classes')->insertGetId($classData);

            // Create entity_source record
            DB::table('entity_sources')->insert([
                'reference_type' => 'App\Models\CharacterClass',
                'reference_id' => $classId,
                'source_id' => $phbSourceId,
                'pages' => $pages,
            ]);
        }
    }
}
