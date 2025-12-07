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
            'class_slug' => $fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $wizard->full_slug,
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
            'class_slug' => $fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/classes", [
            'class' => $wizard->full_slug,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.class.name', 'Wizard')
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.is_primary', false);

        $this->assertDatabaseHas('character_classes', [
            'character_id' => $character->id,
            'class_slug' => $wizard->full_slug,
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
            'class_slug' => $fighter->full_slug,
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
            'class' => $bard->full_slug,
        ]);

        $response->assertUnprocessable()
            ->assertSee('does not meet multiclass prerequisites');
    }

    #[Test]
    public function it_allows_force_bypass_of_prerequisites(): void
    {
        $character = Character::factory()->create(['charisma' => 8]);
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->full_slug,
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
            'class' => $bard->full_slug,
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
            'class_slug' => $fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/classes", [
            'class' => $fighter->full_slug,
        ]);

        $response->assertUnprocessable()
            ->assertSee('already has levels in Fighter');
    }

    #[Test]
    public function it_removes_a_class_from_character(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $wizard->full_slug,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/classes/{$wizard->full_slug}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('character_classes', [
            'character_id' => $character->id,
            'class_slug' => $wizard->full_slug,
        ]);
    }

    #[Test]
    public function it_prevents_removing_last_class(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/classes/{$fighter->full_slug}");

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
            'class_slug' => $fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $wizard->full_slug,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/classes/{$wizard->slug}/level-up");

        $response->assertOk()
            ->assertJsonPath('data.level', 4);

        $this->assertDatabaseHas('character_classes', [
            'character_id' => $character->id,
            'class_slug' => $wizard->full_slug,
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
            'class_slug' => $fighter->full_slug,
            'level' => 15,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $wizard->full_slug,
            'level' => 5,
            'is_primary' => false,
            'order' => 2,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/classes/{$wizard->slug}/level-up");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Character has reached maximum level (20)');
    }

    #[Test]
    public function it_sets_subclass_for_character_class(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null, 'hit_die' => 10]);
        $champion = CharacterClass::factory()->create([
            'name' => 'Champion',
            'parent_class_id' => $fighter->id,
            'hit_die' => 10,
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->full_slug,
            'level' => 3, // Level 3 required for subclass
            'is_primary' => true,
            'order' => 1,
        ]);

        $response = $this->putJson("/api/v1/characters/{$character->id}/classes/{$fighter->slug}/subclass", [
            'subclass' => $champion->full_slug,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.subclass.name', 'Champion');

        $this->assertDatabaseHas('character_classes', [
            'character_id' => $character->id,
            'class_slug' => $fighter->full_slug,
            'subclass_slug' => $champion->full_slug,
        ]);
    }

    #[Test]
    public function it_prevents_setting_subclass_from_wrong_class(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);
        $abjuration = CharacterClass::factory()->create([
            'name' => 'School of Abjuration',
            'parent_class_id' => $wizard->id,
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->full_slug,
            'level' => 3,
            'is_primary' => true,
            'order' => 1,
        ]);

        $response = $this->putJson("/api/v1/characters/{$character->id}/classes/{$fighter->slug}/subclass", [
            'subclass' => $abjuration->full_slug,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', "Subclass 'School of Abjuration' does not belong to class 'Fighter'.");
    }

    #[Test]
    public function it_prevents_setting_subclass_before_required_level(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null, 'hit_die' => 10]);
        $champion = CharacterClass::factory()->create([
            'name' => 'Champion',
            'parent_class_id' => $fighter->id,
            'hit_die' => 10,
        ]);

        // Create subclass feature at level 3 to set the subclass_level
        \App\Models\ClassFeature::factory()->create([
            'class_id' => $champion->id,
            'level' => 3,
            'feature_name' => 'Improved Critical',
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->full_slug,
            'level' => 2, // Below required level 3
            'is_primary' => true,
            'order' => 1,
        ]);

        $response = $this->putJson("/api/v1/characters/{$character->id}/classes/{$fighter->slug}/subclass", [
            'subclass' => $champion->full_slug,
        ]);

        $response->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors', 'current_level', 'required_level'])
            ->assertJsonPath('current_level', 2)
            ->assertJsonPath('required_level', 3);
    }

    #[Test]
    public function it_allows_subclass_at_level_1_for_cleric_style_classes(): void
    {
        $character = Character::factory()->create();
        $cleric = CharacterClass::factory()->create(['name' => 'Cleric', 'parent_class_id' => null, 'hit_die' => 8]);
        $lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'parent_class_id' => $cleric->id,
            'hit_die' => 8,
        ]);

        // Create subclass feature at level 1
        \App\Models\ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'level' => 1,
            'feature_name' => 'Disciple of Life',
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $cleric->full_slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        $response = $this->putJson("/api/v1/characters/{$character->id}/classes/{$cleric->slug}/subclass", [
            'subclass' => $lifeDomain->full_slug,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.subclass.name', 'Life Domain');
    }

    #[Test]
    public function it_returns_404_when_setting_subclass_for_class_not_on_character(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);
        $abjuration = CharacterClass::factory()->create([
            'name' => 'School of Abjuration',
            'parent_class_id' => $wizard->id,
        ]);

        // Character only has Fighter, not Wizard
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->full_slug,
            'level' => 3,
            'is_primary' => true,
            'order' => 1,
        ]);

        $response = $this->putJson("/api/v1/characters/{$character->id}/classes/{$wizard->slug}/subclass", [
            'subclass' => $abjuration->full_slug,
        ]);

        $response->assertNotFound()
            ->assertJsonPath('message', 'Class not found on character');
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
