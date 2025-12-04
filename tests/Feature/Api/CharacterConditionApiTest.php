<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterCondition;
use App\Models\Condition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterConditionApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_empty_array_when_character_has_no_conditions(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/conditions");

        $response->assertOk()
            ->assertJson(['data' => []]);
    }

    #[Test]
    public function it_lists_all_conditions_for_a_character(): void
    {
        $character = Character::factory()->create();
        $conditions = Condition::factory()->count(2)->create();

        foreach ($conditions as $condition) {
            CharacterCondition::factory()->create([
                'character_id' => $character->id,
                'condition_id' => $condition->id,
            ]);
        }

        $response = $this->getJson("/api/v1/characters/{$character->id}/conditions");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_adds_a_condition_to_a_character(): void
    {
        $character = Character::factory()->create();
        $condition = Condition::factory()->create(['name' => 'Test Poisoned', 'slug' => 'test-poisoned-add']);

        $response = $this->postJson("/api/v1/characters/{$character->id}/conditions", [
            'condition_id' => $condition->id,
            'source' => 'Spider bite',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.condition.name', 'Test Poisoned')
            ->assertJsonPath('data.source', 'Spider bite')
            ->assertJsonPath('data.is_exhaustion', false);

        $this->assertDatabaseHas('character_conditions', [
            'character_id' => $character->id,
            'condition_id' => $condition->id,
            'source' => 'Spider bite',
        ]);
    }

    #[Test]
    public function it_upserts_when_adding_existing_condition(): void
    {
        $character = Character::factory()->create();
        $condition = Condition::factory()->create();

        CharacterCondition::factory()->create([
            'character_id' => $character->id,
            'condition_id' => $condition->id,
            'source' => 'Original source',
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/conditions", [
            'condition_id' => $condition->id,
            'source' => 'Updated source',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.source', 'Updated source');

        $this->assertDatabaseCount('character_conditions', 1);
    }

    #[Test]
    public function it_defaults_exhaustion_level_to_1(): void
    {
        $character = Character::factory()->create();
        $exhaustion = Condition::firstOrCreate(
            ['slug' => 'exhaustion'],
            ['name' => 'Exhaustion', 'description' => 'Exhaustion condition']
        );

        $response = $this->postJson("/api/v1/characters/{$character->id}/conditions", [
            'condition_id' => $exhaustion->id,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.is_exhaustion', true);
    }

    #[Test]
    public function it_validates_exhaustion_level_range(): void
    {
        $character = Character::factory()->create();
        $exhaustion = Condition::firstOrCreate(
            ['slug' => 'exhaustion'],
            ['name' => 'Exhaustion', 'description' => 'Exhaustion condition']
        );

        $response = $this->postJson("/api/v1/characters/{$character->id}/conditions", [
            'condition_id' => $exhaustion->id,
            'level' => 7,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('level');
    }

    #[Test]
    public function it_warns_at_exhaustion_level_6(): void
    {
        $character = Character::factory()->create();
        $exhaustion = Condition::firstOrCreate(
            ['slug' => 'exhaustion'],
            ['name' => 'Exhaustion', 'description' => 'Exhaustion condition']
        );

        $response = $this->postJson("/api/v1/characters/{$character->id}/conditions", [
            'condition_id' => $exhaustion->id,
            'level' => 6,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.exhaustion_warning', 'Level 6 exhaustion results in death');
    }

    #[Test]
    public function it_ignores_level_for_non_exhaustion_conditions(): void
    {
        $character = Character::factory()->create();
        $poisoned = Condition::factory()->create(['slug' => 'test-poisoned-ignore-level']);

        $response = $this->postJson("/api/v1/characters/{$character->id}/conditions", [
            'condition_id' => $poisoned->id,
            'level' => 3,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.level', null);
    }

    #[Test]
    public function it_removes_a_condition_by_id(): void
    {
        $character = Character::factory()->create();
        $condition = Condition::factory()->create(['slug' => 'test-remove-by-id']);

        CharacterCondition::create([
            'character_id' => $character->id,
            'condition_id' => $condition->id,
        ]);

        $this->assertDatabaseHas('character_conditions', [
            'character_id' => $character->id,
            'condition_id' => $condition->id,
        ]);

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/conditions/{$condition->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('character_conditions', [
            'character_id' => $character->id,
            'condition_id' => $condition->id,
        ]);
    }

    #[Test]
    public function it_removes_a_condition_by_slug(): void
    {
        $character = Character::factory()->create();
        $condition = Condition::factory()->create(['slug' => 'test-remove-by-slug']);

        CharacterCondition::create([
            'character_id' => $character->id,
            'condition_id' => $condition->id,
        ]);

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/conditions/test-remove-by-slug");

        $response->assertNoContent();
    }

    #[Test]
    public function it_returns_404_when_removing_condition_character_does_not_have(): void
    {
        $character = Character::factory()->create();
        $condition = Condition::factory()->create();

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/conditions/{$condition->id}");

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_422_for_invalid_condition_id(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/conditions", [
            'condition_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('condition_id');
    }

    #[Test]
    public function it_returns_404_for_nonexistent_character(): void
    {
        $response = $this->getJson('/api/v1/characters/99999/conditions');

        $response->assertNotFound();
    }

    #[Test]
    public function it_updates_exhaustion_level_on_existing_condition(): void
    {
        $character = Character::factory()->create();
        $exhaustion = Condition::firstOrCreate(
            ['slug' => 'exhaustion'],
            ['name' => 'Exhaustion', 'description' => 'Exhaustion condition']
        );

        CharacterCondition::factory()->create([
            'character_id' => $character->id,
            'condition_id' => $exhaustion->id,
            'level' => 1,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/conditions", [
            'condition_id' => $exhaustion->id,
            'level' => 3,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.level', 3);

        $this->assertDatabaseCount('character_conditions', 1);
        $this->assertDatabaseHas('character_conditions', [
            'character_id' => $character->id,
            'condition_id' => $exhaustion->id,
            'level' => 3,
        ]);
    }
}
