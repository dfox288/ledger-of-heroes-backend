<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\ClassCounter;
use App\Models\ClassFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterCounterApiTest extends TestCase
{
    use RefreshDatabase;

    private CharacterClass $barbarian;

    private ClassFeature $rageFeature;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test Barbarian class with Rage counter
        $this->barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'test:barbarian',
        ]);

        $this->rageFeature = ClassFeature::factory()->create([
            'class_id' => $this->barbarian->id,
            'feature_name' => 'Rage',
            'level' => 1,
            'resets_on' => 'long_rest',
        ]);

        ClassCounter::create([
            'class_id' => $this->barbarian->id,
            'level' => 1,
            'counter_name' => 'Rage',
            'counter_value' => 2,
            'reset_timing' => 'L',
        ]);

        ClassCounter::create([
            'class_id' => $this->barbarian->id,
            'level' => 3,
            'counter_name' => 'Rage',
            'counter_value' => 3,
            'reset_timing' => 'L',
        ]);
    }

    // =====================
    // GET /characters/{id}/counters
    // =====================

    public function test_it_lists_counters_for_character(): void
    {
        $character = Character::factory()
            ->withClass($this->barbarian)
            ->level(3)
            ->withFeatures()
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/counters");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'slug',
                        'name',
                        'current',
                        'max',
                        'reset_on',
                        'source',
                        'source_type',
                        'unlimited',
                    ],
                ],
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Rage', $response->json('data.0.name'));
        $this->assertEquals(3, $response->json('data.0.max'));
    }

    public function test_it_returns_empty_array_for_character_without_counters(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Commoner']);
        $character = Character::factory()
            ->withClass($class)
            ->level(1)
            ->withFeatures()
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/counters");

        $response->assertOk()
            ->assertJson(['data' => []]);
    }

    public function test_it_returns_404_for_nonexistent_character(): void
    {
        $response = $this->getJson('/api/v1/characters/99999/counters');

        $response->assertNotFound();
    }

    // =====================
    // PATCH /characters/{id}/counters/{slug} - Absolute Value
    // =====================

    public function test_it_sets_counter_spent_to_absolute_value(): void
    {
        $character = Character::factory()
            ->withClass($this->barbarian)
            ->level(3)
            ->withFeatures()
            ->create();

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/counters/test:barbarian:rage",
            ['spent' => 2]
        );

        $response->assertOk()
            ->assertJsonPath('data.name', 'Rage')
            ->assertJsonPath('data.current', 1) // 3 - 2 = 1 remaining
            ->assertJsonPath('data.max', 3);
    }

    public function test_it_rejects_spent_exceeding_max(): void
    {
        $character = Character::factory()
            ->withClass($this->barbarian)
            ->level(3)
            ->withFeatures()
            ->create();

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/counters/test:barbarian:rage",
            ['spent' => 10]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['spent']);
    }

    public function test_it_rejects_negative_spent_value(): void
    {
        $character = Character::factory()
            ->withClass($this->barbarian)
            ->level(3)
            ->withFeatures()
            ->create();

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/counters/test:barbarian:rage",
            ['spent' => -1]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['spent']);
    }

    // =====================
    // PATCH /characters/{id}/counters/{slug} - Actions
    // =====================

    public function test_it_uses_counter_via_action(): void
    {
        $character = Character::factory()
            ->withClass($this->barbarian)
            ->level(3)
            ->withFeatures()
            ->create();

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/counters/test:barbarian:rage",
            ['action' => 'use']
        );

        $response->assertOk()
            ->assertJsonPath('data.current', 2); // 3 - 1 = 2
    }

    public function test_it_restores_counter_via_action(): void
    {
        $character = Character::factory()
            ->withClass($this->barbarian)
            ->level(3)
            ->withFeatures()
            ->create();

        // First use one
        $this->patchJson(
            "/api/v1/characters/{$character->id}/counters/test:barbarian:rage",
            ['action' => 'use']
        );

        // Then restore
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/counters/test:barbarian:rage",
            ['action' => 'restore']
        );

        $response->assertOk()
            ->assertJsonPath('data.current', 3); // Back to max
    }

    public function test_it_resets_counter_via_action(): void
    {
        $character = Character::factory()
            ->withClass($this->barbarian)
            ->level(3)
            ->withFeatures()
            ->create();

        // Use two
        $this->patchJson(
            "/api/v1/characters/{$character->id}/counters/test:barbarian:rage",
            ['spent' => 2]
        );

        // Reset
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/counters/test:barbarian:rage",
            ['action' => 'reset']
        );

        $response->assertOk()
            ->assertJsonPath('data.current', 3); // Back to max
    }

    public function test_it_rejects_use_when_no_uses_remaining(): void
    {
        $character = Character::factory()
            ->withClass($this->barbarian)
            ->level(3)
            ->withFeatures()
            ->create();

        // Use all 3
        $this->patchJson(
            "/api/v1/characters/{$character->id}/counters/test:barbarian:rage",
            ['spent' => 3]
        );

        // Try to use one more
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/counters/test:barbarian:rage",
            ['action' => 'use']
        );

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'No uses remaining for this counter.');
    }

    public function test_it_rejects_restore_when_at_max(): void
    {
        $character = Character::factory()
            ->withClass($this->barbarian)
            ->level(3)
            ->withFeatures()
            ->create();

        // Try to restore when already at max
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/counters/test:barbarian:rage",
            ['action' => 'restore']
        );

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Counter is already at maximum.');
    }

    public function test_it_rejects_invalid_action(): void
    {
        $character = Character::factory()
            ->withClass($this->barbarian)
            ->level(3)
            ->withFeatures()
            ->create();

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/counters/test:barbarian:rage",
            ['action' => 'invalid']
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['action']);
    }

    // =====================
    // Edge Cases
    // =====================

    public function test_it_returns_404_for_nonexistent_counter_slug(): void
    {
        $character = Character::factory()
            ->withClass($this->barbarian)
            ->level(3)
            ->withFeatures()
            ->create();

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/counters/nonexistent:counter",
            ['action' => 'use']
        );

        $response->assertNotFound();
    }

    public function test_it_requires_either_spent_or_action(): void
    {
        $character = Character::factory()
            ->withClass($this->barbarian)
            ->level(3)
            ->withFeatures()
            ->create();

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/counters/test:barbarian:rage",
            []
        );

        $response->assertUnprocessable();
    }

    public function test_it_rejects_both_spent_and_action(): void
    {
        $character = Character::factory()
            ->withClass($this->barbarian)
            ->level(3)
            ->withFeatures()
            ->create();

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/counters/test:barbarian:rage",
            ['spent' => 1, 'action' => 'use']
        );

        $response->assertUnprocessable();
    }

    // =====================
    // Character Resource Integration
    // =====================

    public function test_it_includes_counters_in_character_response(): void
    {
        $character = Character::factory()
            ->withClass($this->barbarian)
            ->level(3)
            ->withFeatures()
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'counters' => [
                        '*' => ['id', 'slug', 'name', 'current', 'max', 'reset_on'],
                    ],
                ],
            ]);

        $this->assertEquals('Rage', $response->json('data.counters.0.name'));
    }
}
