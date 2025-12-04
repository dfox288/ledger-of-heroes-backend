<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\ClassLevelProgression;
use Database\Seeders\MulticlassSpellSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterResourceMulticlassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MulticlassSpellSlotSeeder::class);
        $this->seedAbilityScores();
    }

    #[Test]
    public function it_includes_classes_array_in_response(): void
    {
        $character = Character::factory()->create(['name' => 'Multiclass Hero']);
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);
        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'wizard',
            'hit_die' => 6,
            'parent_class_id' => null,
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Multiclass Hero')
            ->assertJsonPath('data.total_level', 8)
            ->assertJsonPath('data.is_multiclass', true)
            ->assertJsonCount(2, 'data.classes')
            ->assertJsonPath('data.classes.0.class.name', 'Fighter')
            ->assertJsonPath('data.classes.0.level', 5)
            ->assertJsonPath('data.classes.1.class.name', 'Wizard')
            ->assertJsonPath('data.classes.1.level', 3);
    }

    #[Test]
    public function it_includes_spell_slots_for_multiclass_caster(): void
    {
        $character = Character::factory()->create();
        $cleric = $this->createFullCaster('Cleric');
        $wizard = $this->createFullCaster('Wizard');

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $cleric->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        // Caster level 8 = slots 4/3/3/2
        $response->assertOk()
            ->assertJsonPath('data.spell_slots.standard.1st', 4)
            ->assertJsonPath('data.spell_slots.standard.2nd', 3)
            ->assertJsonPath('data.spell_slots.standard.3rd', 3)
            ->assertJsonPath('data.spell_slots.standard.4th', 2);
    }

    private function seedAbilityScores(): void
    {
        $abilities = [
            ['id' => 1, 'code' => 'STR', 'name' => 'Strength'],
            ['id' => 2, 'code' => 'DEX', 'name' => 'Dexterity'],
            ['id' => 3, 'code' => 'CON', 'name' => 'Constitution'],
            ['id' => 4, 'code' => 'INT', 'name' => 'Intelligence'],
            ['id' => 5, 'code' => 'WIS', 'name' => 'Wisdom'],
            ['id' => 6, 'code' => 'CHA', 'name' => 'Charisma'],
        ];

        foreach ($abilities as $ability) {
            AbilityScore::updateOrCreate(['id' => $ability['id']], $ability);
        }
    }

    private function createFullCaster(string $name): CharacterClass
    {
        $class = CharacterClass::factory()->create([
            'name' => $name,
            'slug' => strtolower($name),
            'parent_class_id' => null,
            'spellcasting_ability_id' => 4, // INT
        ]);

        // Create level progression with 9th level spell slots (full caster)
        ClassLevelProgression::create([
            'class_id' => $class->id,
            'level' => 20,
            'proficiency_bonus' => 6,
            'spell_slots_1st' => 4,
            'spell_slots_2nd' => 3,
            'spell_slots_3rd' => 3,
            'spell_slots_4th' => 3,
            'spell_slots_5th' => 3,
            'spell_slots_6th' => 2,
            'spell_slots_7th' => 2,
            'spell_slots_8th' => 1,
            'spell_slots_9th' => 1,
        ]);

        return $class;
    }
}
