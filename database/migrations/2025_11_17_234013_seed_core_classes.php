<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get PHB source ID
        $phbSourceId = DB::table('sources')->where('code', 'PHB')->value('id');

        // Get ability score IDs for spellcasting abilities
        $intId = DB::table('ability_scores')->where('code', 'INT')->value('id');
        $wisId = DB::table('ability_scores')->where('code', 'WIS')->value('id');
        $chaId = DB::table('ability_scores')->where('code', 'CHA')->value('id');

        // Seed 13 core D&D classes
        DB::table('classes')->insert([
            [
                'name' => 'Artificer',
                'parent_class_id' => null,
                'hit_die' => 8,
                'description' => 'Masters of invention, artificers use ingenuity and magic to unlock extraordinary capabilities in objects.',
                'primary_ability' => 'Intelligence',
                'spellcasting_ability_id' => $intId,
                'source_id' => $phbSourceId,
                'source_pages' => '0',
            ],
            [
                'name' => 'Barbarian',
                'parent_class_id' => null,
                'hit_die' => 12,
                'description' => 'A fierce warrior of primitive background who can enter a battle rage.',
                'primary_ability' => 'Strength',
                'spellcasting_ability_id' => null,
                'source_id' => $phbSourceId,
                'source_pages' => '46',
            ],
            [
                'name' => 'Bard',
                'parent_class_id' => null,
                'hit_die' => 8,
                'description' => 'An inspiring magician whose power echoes the music of creation.',
                'primary_ability' => 'Charisma',
                'spellcasting_ability_id' => $chaId,
                'source_id' => $phbSourceId,
                'source_pages' => '51',
            ],
            [
                'name' => 'Cleric',
                'parent_class_id' => null,
                'hit_die' => 8,
                'description' => 'A priestly champion who wields divine magic in service of a higher power.',
                'primary_ability' => 'Wisdom',
                'spellcasting_ability_id' => $wisId,
                'source_id' => $phbSourceId,
                'source_pages' => '56',
            ],
            [
                'name' => 'Druid',
                'parent_class_id' => null,
                'hit_die' => 8,
                'description' => 'A priest of the Old Faith, wielding the powers of nature and adopting animal forms.',
                'primary_ability' => 'Wisdom',
                'spellcasting_ability_id' => $wisId,
                'source_id' => $phbSourceId,
                'source_pages' => '64',
            ],
            [
                'name' => 'Fighter',
                'parent_class_id' => null,
                'hit_die' => 10,
                'description' => 'A master of martial combat, skilled with a variety of weapons and armor.',
                'primary_ability' => 'Strength or Dexterity',
                'spellcasting_ability_id' => null,
                'source_id' => $phbSourceId,
                'source_pages' => '70',
            ],
            [
                'name' => 'Monk',
                'parent_class_id' => null,
                'hit_die' => 8,
                'description' => 'A master of martial arts, harnessing the power of the body in pursuit of physical and spiritual perfection.',
                'primary_ability' => 'Dexterity and Wisdom',
                'spellcasting_ability_id' => null,
                'source_id' => $phbSourceId,
                'source_pages' => '76',
            ],
            [
                'name' => 'Paladin',
                'parent_class_id' => null,
                'hit_die' => 10,
                'description' => 'A holy warrior bound to a sacred oath.',
                'primary_ability' => 'Strength and Charisma',
                'spellcasting_ability_id' => $chaId,
                'source_id' => $phbSourceId,
                'source_pages' => '82',
            ],
            [
                'name' => 'Ranger',
                'parent_class_id' => null,
                'hit_die' => 10,
                'description' => 'A warrior who uses martial prowess and nature magic to combat threats on the edges of civilization.',
                'primary_ability' => 'Dexterity and Wisdom',
                'spellcasting_ability_id' => $wisId,
                'source_id' => $phbSourceId,
                'source_pages' => '89',
            ],
            [
                'name' => 'Rogue',
                'parent_class_id' => null,
                'hit_die' => 8,
                'description' => 'A scoundrel who uses stealth and trickery to overcome obstacles and enemies.',
                'primary_ability' => 'Dexterity',
                'spellcasting_ability_id' => null,
                'source_id' => $phbSourceId,
                'source_pages' => '94',
            ],
            [
                'name' => 'Sorcerer',
                'parent_class_id' => null,
                'hit_die' => 6,
                'description' => 'A spellcaster who draws on inherent magic from a gift or bloodline.',
                'primary_ability' => 'Charisma',
                'spellcasting_ability_id' => $chaId,
                'source_id' => $phbSourceId,
                'source_pages' => '99',
            ],
            [
                'name' => 'Warlock',
                'parent_class_id' => null,
                'hit_die' => 8,
                'description' => 'A wielder of magic that is derived from a bargain with an extraplanar entity.',
                'primary_ability' => 'Charisma',
                'spellcasting_ability_id' => $chaId,
                'source_id' => $phbSourceId,
                'source_pages' => '105',
            ],
            [
                'name' => 'Wizard',
                'parent_class_id' => null,
                'hit_die' => 6,
                'description' => 'A scholarly magic-user capable of manipulating the structures of reality.',
                'primary_ability' => 'Intelligence',
                'spellcasting_ability_id' => $intId,
                'source_id' => $phbSourceId,
                'source_pages' => '112',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove all base classes (subclasses will be cascade deleted)
        DB::table('classes')->whereNull('parent_class_id')->delete();
    }
};
