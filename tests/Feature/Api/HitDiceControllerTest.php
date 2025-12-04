<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HitDiceControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_hit_dice_for_single_class_character(): void
    {
        // Arrange
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $fighterClass->id,
            'level' => 5,
            'hit_dice_spent' => 2,
            'is_primary' => true,
        ]);

        // Act
        $response = $this->getJson("/api/v1/characters/{$character->id}/hit-dice");

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'hit_dice',
                    'total' => ['available', 'max', 'spent'],
                ],
            ])
            ->assertJsonPath('data.hit_dice.d10.available', 3)
            ->assertJsonPath('data.hit_dice.d10.max', 5)
            ->assertJsonPath('data.hit_dice.d10.spent', 2)
            ->assertJsonPath('data.total.available', 3)
            ->assertJsonPath('data.total.max', 5)
            ->assertJsonPath('data.total.spent', 2);
    }

    #[Test]
    public function it_returns_hit_dice_for_multiclass_character(): void
    {
        // Arrange
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $wizardClass = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'hit_die' => 6,
            'parent_class_id' => null,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $fighterClass->id,
            'level' => 5,
            'hit_dice_spent' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $wizardClass->id,
            'level' => 2,
            'hit_dice_spent' => 0,
            'is_primary' => false,
            'order' => 2,
        ]);

        // Act
        $response = $this->getJson("/api/v1/characters/{$character->id}/hit-dice");

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.hit_dice.d10.available', 4)
            ->assertJsonPath('data.hit_dice.d10.max', 5)
            ->assertJsonPath('data.hit_dice.d6.available', 2)
            ->assertJsonPath('data.hit_dice.d6.max', 2)
            ->assertJsonPath('data.total.available', 6)
            ->assertJsonPath('data.total.max', 7);
    }

    #[Test]
    public function it_returns_404_for_nonexistent_character(): void
    {
        $response = $this->getJson('/api/v1/characters/99999/hit-dice');

        $response->assertNotFound();
    }

    #[Test]
    public function it_spends_hit_dice(): void
    {
        // Arrange
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $character = Character::factory()->create();
        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $fighterClass->id,
            'level' => 5,
            'hit_dice_spent' => 0,
            'is_primary' => true,
        ]);

        // Act
        $response = $this->postJson("/api/v1/characters/{$character->id}/hit-dice/spend", [
            'die_type' => 'd10',
            'quantity' => 2,
        ]);

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.hit_dice.d10.available', 3)
            ->assertJsonPath('data.hit_dice.d10.spent', 2);

        $this->assertDatabaseHas('character_classes', [
            'id' => $pivot->id,
            'hit_dice_spent' => 2,
        ]);
    }

    #[Test]
    public function it_validates_die_type_on_spend(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/hit-dice/spend", [
            'die_type' => 'd20', // Invalid
            'quantity' => 1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['die_type']);
    }

    #[Test]
    public function it_validates_quantity_on_spend(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/hit-dice/spend", [
            'die_type' => 'd10',
            'quantity' => 0, // Invalid
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    #[Test]
    public function it_returns_error_when_spending_more_than_available(): void
    {
        // Arrange
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $fighterClass->id,
            'level' => 3,
            'hit_dice_spent' => 2, // Only 1 available
            'is_primary' => true,
        ]);

        // Act
        $response = $this->postJson("/api/v1/characters/{$character->id}/hit-dice/spend", [
            'die_type' => 'd10',
            'quantity' => 3,
        ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Not enough d10 hit dice available. Have 1, need 3.');
    }

    #[Test]
    public function it_recovers_hit_dice_with_specified_quantity(): void
    {
        // Arrange
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $character = Character::factory()->create();
        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $fighterClass->id,
            'level' => 6,
            'hit_dice_spent' => 4,
            'is_primary' => true,
        ]);

        // Act
        $response = $this->postJson("/api/v1/characters/{$character->id}/hit-dice/recover", [
            'quantity' => 3,
        ]);

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.recovered', 3)
            ->assertJsonPath('data.hit_dice.d10.available', 5)
            ->assertJsonPath('data.hit_dice.d10.spent', 1);

        $this->assertDatabaseHas('character_classes', [
            'id' => $pivot->id,
            'hit_dice_spent' => 1,
        ]);
    }

    #[Test]
    public function it_recovers_half_total_when_quantity_not_specified(): void
    {
        // Arrange: Level 8, all spent, should recover 4
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $fighterClass->id,
            'level' => 8,
            'hit_dice_spent' => 8,
            'is_primary' => true,
        ]);

        // Act
        $response = $this->postJson("/api/v1/characters/{$character->id}/hit-dice/recover", []);

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.recovered', 4);
    }

    #[Test]
    public function it_validates_quantity_on_recover(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/hit-dice/recover", [
            'quantity' => 0, // Invalid
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }
}
