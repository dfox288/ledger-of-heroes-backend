<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\Proficiency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterMulticlassApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAbilityScores();
    }

    #[Test]
    public function it_lists_character_classes(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null, 'hit_die' => 10]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null, 'hit_die' => 6]);

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

        $response = $this->getJson("/api/v1/characters/{$character->id}/classes");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.class.name', 'Fighter')
            ->assertJsonPath('data.0.level', 5)
            ->assertJsonPath('data.0.is_primary', true)
            ->assertJsonPath('data.1.class.name', 'Wizard')
            ->assertJsonPath('data.1.level', 3)
            ->assertJsonPath('data.1.is_primary', false);
    }

    #[Test]
    public function it_adds_a_class_to_character(): void
    {
        $character = Character::factory()->create([
            'strength' => 14,
            'intelligence' => 14,
        ]);
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);

        // Add primary class first
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/classes", [
            'class_id' => $wizard->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.class.name', 'Wizard')
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.is_primary', false);

        $this->assertDatabaseHas('character_classes', [
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 1,
        ]);
    }

    #[Test]
    public function it_validates_prerequisites_when_adding_class(): void
    {
        $character = Character::factory()->create(['charisma' => 8]);
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $bard = CharacterClass::factory()->create(['name' => 'Bard', 'parent_class_id' => null]);
        $cha = AbilityScore::where('code', 'CHA')->first();
        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $bard->id,
            'proficiency_type' => 'multiclass_requirement',
            'proficiency_name' => 'Charisma 13',
            'ability_score_id' => $cha->id,
            'quantity' => 13,
            'is_choice' => false,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/classes", [
            'class_id' => $bard->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Multiclass prerequisites not met');
    }

    #[Test]
    public function it_allows_force_bypass_of_prerequisites(): void
    {
        $character = Character::factory()->create(['charisma' => 8]);
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $bard = CharacterClass::factory()->create(['name' => 'Bard', 'parent_class_id' => null]);
        $cha = AbilityScore::where('code', 'CHA')->first();
        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $bard->id,
            'proficiency_type' => 'multiclass_requirement',
            'proficiency_name' => 'Charisma 13',
            'ability_score_id' => $cha->id,
            'quantity' => 13,
            'is_choice' => false,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/classes", [
            'class_id' => $bard->id,
            'force' => true,
        ]);

        $response->assertCreated();
    }

    #[Test]
    public function it_prevents_duplicate_classes(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/classes", [
            'class_id' => $fighter->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Character already has levels in Fighter');
    }

    #[Test]
    public function it_removes_a_class_from_character(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);

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

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/classes/{$wizard->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('character_classes', [
            'character_id' => $character->id,
            'class_id' => $wizard->id,
        ]);
    }

    #[Test]
    public function it_prevents_removing_last_class(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/classes/{$fighter->id}");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot remove the only class');
    }

    #[Test]
    public function it_levels_up_a_specific_class(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'hit_die' => 10, 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'hit_die' => 6, 'parent_class_id' => null]);

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

        $response = $this->postJson("/api/v1/characters/{$character->id}/classes/{$wizard->id}/level-up");

        $response->assertOk()
            ->assertJsonPath('data.level', 4);

        $this->assertDatabaseHas('character_classes', [
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 4,
        ]);
    }

    #[Test]
    public function it_prevents_level_up_beyond_20_total(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 15,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 5,
            'is_primary' => false,
            'order' => 2,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/classes/{$wizard->id}/level-up");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Character has reached maximum level (20)');
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
}
