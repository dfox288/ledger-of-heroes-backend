<?php

namespace Tests\Feature\Api;

use App\Enums\ResetTiming;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterFeature;
use App\Models\ClassFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatureUseApiTest extends TestCase
{
    use RefreshDatabase;

    private CharacterClass $fighterClass;

    private ClassFeature $actionSurge;

    private Character $character;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();
    }

    private function createFixtures(): void
    {
        $this->fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'phb:fighter',
        ]);

        $this->actionSurge = ClassFeature::factory()
            ->forClass($this->fighterClass)
            ->create([
                'feature_name' => 'Action Surge',
                'level' => 2,
                'resets_on' => ResetTiming::SHORT_REST,
            ]);

        $this->character = Character::factory()
            ->withClass($this->fighterClass, level: 2)
            ->create();
    }

    // =============================
    // POST /characters/{id}/features/{featureId}/use
    // =============================

    #[Test]
    public function it_uses_a_feature(): void
    {
        $characterFeature = CharacterFeature::create([
            'character_id' => $this->character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $this->actionSurge->id,
            'feature_slug' => 'action-surge',
            'source' => 'class',
            'level_acquired' => 2,
            'max_uses' => 1,
            'uses_remaining' => 1,
        ]);

        $response = $this->postJson(
            "/api/v1/characters/{$this->character->id}/features/{$characterFeature->id}/use"
        );

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'uses_remaining',
                'max_uses',
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('uses_remaining', 0)
            ->assertJsonPath('max_uses', 1);

        expect($characterFeature->fresh()->uses_remaining)->toBe(0);
    }

    #[Test]
    public function it_returns_422_when_feature_exhausted(): void
    {
        $characterFeature = CharacterFeature::create([
            'character_id' => $this->character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $this->actionSurge->id,
            'feature_slug' => 'action-surge',
            'source' => 'class',
            'level_acquired' => 2,
            'max_uses' => 1,
            'uses_remaining' => 0,
        ]);

        $response = $this->postJson(
            "/api/v1/characters/{$this->character->id}/features/{$characterFeature->id}/use"
        );

        $response->assertStatus(422)
            ->assertJsonPath('message', 'No uses remaining for this feature');
    }

    #[Test]
    public function it_returns_404_for_invalid_feature(): void
    {
        $response = $this->postJson(
            "/api/v1/characters/{$this->character->id}/features/99999/use"
        );

        $response->assertStatus(404);
    }

    #[Test]
    public function it_returns_404_for_feature_belonging_to_different_character(): void
    {
        $otherCharacter = Character::factory()->create();

        $characterFeature = CharacterFeature::create([
            'character_id' => $otherCharacter->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $this->actionSurge->id,
            'feature_slug' => 'action-surge',
            'source' => 'class',
            'level_acquired' => 2,
            'max_uses' => 1,
            'uses_remaining' => 1,
        ]);

        $response = $this->postJson(
            "/api/v1/characters/{$this->character->id}/features/{$characterFeature->id}/use"
        );

        $response->assertStatus(404);
    }

    #[Test]
    public function it_returns_404_for_invalid_character(): void
    {
        $response = $this->postJson('/api/v1/characters/99999/features/1/use');

        $response->assertStatus(404);
    }

    // =============================
    // POST /characters/{id}/features/{featureId}/reset
    // =============================

    #[Test]
    public function it_resets_a_feature(): void
    {
        $characterFeature = CharacterFeature::create([
            'character_id' => $this->character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $this->actionSurge->id,
            'feature_slug' => 'action-surge',
            'source' => 'class',
            'level_acquired' => 2,
            'max_uses' => 1,
            'uses_remaining' => 0,
        ]);

        $response = $this->postJson(
            "/api/v1/characters/{$this->character->id}/features/{$characterFeature->id}/reset"
        );

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'uses_remaining',
                'max_uses',
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('uses_remaining', 1)
            ->assertJsonPath('max_uses', 1);

        expect($characterFeature->fresh()->uses_remaining)->toBe(1);
    }

    #[Test]
    public function it_returns_404_when_resetting_invalid_feature(): void
    {
        $response = $this->postJson(
            "/api/v1/characters/{$this->character->id}/features/99999/reset"
        );

        $response->assertStatus(404);
    }

    // =============================
    // GET /characters/{id}/feature-uses (optional, listed in issue)
    // =============================

    #[Test]
    public function it_gets_all_feature_uses_for_character(): void
    {
        CharacterFeature::create([
            'character_id' => $this->character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $this->actionSurge->id,
            'feature_slug' => 'action-surge',
            'source' => 'class',
            'level_acquired' => 2,
            'max_uses' => 1,
            'uses_remaining' => 0,
        ]);

        $response = $this->getJson(
            "/api/v1/characters/{$this->character->id}/feature-uses"
        );

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'feature_name',
                        'feature_slug',
                        'source',
                        'uses_remaining',
                        'max_uses',
                        'resets_on',
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.feature_name', 'Action Surge')
            ->assertJsonPath('data.0.uses_remaining', 0)
            ->assertJsonPath('data.0.max_uses', 1)
            ->assertJsonPath('data.0.resets_on', 'short_rest');
    }

    #[Test]
    public function it_returns_empty_array_for_character_with_no_limited_features(): void
    {
        $response = $this->getJson(
            "/api/v1/characters/{$this->character->id}/feature-uses"
        );

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
