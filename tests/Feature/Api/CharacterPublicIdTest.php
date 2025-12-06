<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-db')]
class CharacterPublicIdTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // Route Model Binding Tests
    // =====================

    #[Test]
    public function it_resolves_character_by_public_id_in_url(): void
    {
        $character = Character::factory()->withPublicId('shadow-warden-q3x9')->create();

        $response = $this->getJson('/api/v1/characters/shadow-warden-q3x9');

        $response->assertOk()
            ->assertJsonPath('data.public_id', 'shadow-warden-q3x9')
            ->assertJsonPath('data.id', $character->id);
    }

    #[Test]
    public function it_returns_404_for_nonexistent_public_id(): void
    {
        $response = $this->getJson('/api/v1/characters/nonexistent-slug-xxxx');

        $response->assertNotFound();
    }

    #[Test]
    public function it_updates_character_via_public_id(): void
    {
        Character::factory()->withPublicId('brave-knight-abc1')->create([
            'name' => 'Original Name',
        ]);

        $response = $this->patchJson('/api/v1/characters/brave-knight-abc1', [
            'name' => 'Updated Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('characters', [
            'public_id' => 'brave-knight-abc1',
            'name' => 'Updated Name',
        ]);
    }

    #[Test]
    public function it_deletes_character_via_public_id(): void
    {
        Character::factory()->withPublicId('fallen-sage-xyz9')->create();

        $response = $this->deleteJson('/api/v1/characters/fallen-sage-xyz9');

        $response->assertNoContent();
        $this->assertDatabaseMissing('characters', ['public_id' => 'fallen-sage-xyz9']);
    }

    // =====================
    // Store Tests (Create with public_id)
    // =====================

    #[Test]
    public function it_creates_character_with_client_provided_public_id(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'arcane-phoenix-m7k2',
            'name' => 'Test Character',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.public_id', 'arcane-phoenix-m7k2')
            ->assertJsonPath('data.name', 'Test Character');

        $this->assertDatabaseHas('characters', [
            'public_id' => 'arcane-phoenix-m7k2',
            'name' => 'Test Character',
        ]);
    }

    #[Test]
    public function it_requires_public_id_for_character_creation(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'name' => 'Test Character',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['public_id']);
    }

    #[Test]
    public function it_rejects_duplicate_public_id(): void
    {
        Character::factory()->withPublicId('unique-slug-ab12')->create();

        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'unique-slug-ab12',
            'name' => 'Another Character',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['public_id'])
            ->assertJsonPath('errors.public_id.0', 'This public ID is already in use. Please generate a new one.');
    }

    #[Test]
    public function it_validates_public_id_format(): void
    {
        // Invalid format: missing suffix
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'invalid-format',
            'name' => 'Test',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['public_id']);
    }

    #[Test]
    public function it_validates_public_id_format_with_uppercase_in_words(): void
    {
        // Invalid format: uppercase in adjective/noun
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'Invalid-Format-ab12',
            'name' => 'Test',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['public_id']);
    }

    #[Test]
    public function it_accepts_mixed_case_in_suffix(): void
    {
        // Valid: lowercase words, mixed case suffix
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'mystic-dragon-AbC1',
            'name' => 'Test Character',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.public_id', 'mystic-dragon-AbC1');
    }

    #[Test]
    public function it_rejects_public_id_exceeding_max_length(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'this-is-a-very-long-public-id-that-exceeds-thirty-characters',
            'name' => 'Test',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['public_id']);
    }

    // =====================
    // Resource Response Tests
    // =====================

    #[Test]
    public function it_includes_public_id_in_character_list(): void
    {
        Character::factory()->withPublicId('test-hero-1234')->create();
        Character::factory()->withPublicId('test-mage-5678')->create();

        $response = $this->getJson('/api/v1/characters');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'public_id', 'name'],
                ],
            ]);
    }

    #[Test]
    public function it_includes_public_id_in_character_show(): void
    {
        Character::factory()->withPublicId('iron-sage-ueuw')->create();

        $response = $this->getJson('/api/v1/characters/iron-sage-ueuw');

        $response->assertOk()
            ->assertJsonPath('data.public_id', 'iron-sage-ueuw');
    }

    // =====================
    // Factory Tests
    // =====================

    #[Test]
    public function factory_generates_valid_public_id_format(): void
    {
        $character = Character::factory()->create();

        $this->assertNotNull($character->public_id);
        $this->assertMatchesRegularExpression('/^[a-z]+-[a-z]+-[A-Za-z0-9]{4}$/', $character->public_id);
    }

    #[Test]
    public function factory_generates_unique_public_ids(): void
    {
        $characters = Character::factory()->count(10)->create();

        $publicIds = $characters->pluck('public_id')->toArray();

        // All should be unique
        $this->assertCount(10, array_unique($publicIds));
    }
}
