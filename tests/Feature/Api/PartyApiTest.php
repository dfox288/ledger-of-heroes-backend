<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PartyApiTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // Index Tests
    // =====================

    #[Test]
    public function it_lists_parties_for_the_current_user(): void
    {
        $user = User::factory()->create();
        Party::factory()->count(3)->create(['user_id' => $user->id]);

        // Create party for different user (should not appear)
        Party::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/parties');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'description', 'character_count', 'created_at'],
                ],
            ]);
    }

    #[Test]
    public function it_returns_empty_array_when_user_has_no_parties(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/parties');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_includes_character_count_in_party_list(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $characters = Character::factory()->count(3)->create();
        $party->characters()->attach($characters);

        $response = $this->actingAs($user)->getJson('/api/v1/parties');

        $response->assertOk()
            ->assertJsonPath('data.0.character_count', 3);
    }

    // =====================
    // Store Tests
    // =====================

    #[Test]
    public function it_creates_a_party(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/parties', [
            'name' => 'Dragon Heist Campaign',
            'description' => 'A group of adventurers in Waterdeep',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Dragon Heist Campaign')
            ->assertJsonPath('data.description', 'A group of adventurers in Waterdeep');

        $this->assertDatabaseHas('parties', [
            'name' => 'Dragon Heist Campaign',
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_creates_a_party_without_description(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/parties', [
            'name' => 'My Party',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.description', null);
    }

    #[Test]
    public function it_validates_name_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/parties', [
            'description' => 'Some description',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // =====================
    // Show Tests
    // =====================

    #[Test]
    public function it_shows_a_party_with_characters(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $characters = Character::factory()->count(2)->create();
        $party->characters()->attach($characters);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $party->id)
            ->assertJsonPath('data.name', $party->name)
            ->assertJsonCount(2, 'data.characters');
    }

    #[Test]
    public function it_returns_404_for_nonexistent_party(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/parties/999');

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_other_users_party(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}");

        $response->assertNotFound();
    }

    // =====================
    // Update Tests
    // =====================

    #[Test]
    public function it_updates_a_party(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->putJson("/api/v1/parties/{$party->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.description', 'Updated description');
    }

    #[Test]
    public function it_cannot_update_other_users_party(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->putJson("/api/v1/parties/{$party->id}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertNotFound();
    }

    // =====================
    // Destroy Tests
    // =====================

    #[Test]
    public function it_deletes_a_party(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/parties/{$party->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('parties', ['id' => $party->id]);
    }

    #[Test]
    public function it_cannot_delete_other_users_party(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/parties/{$party->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('parties', ['id' => $party->id]);
    }

    // =====================
    // Add Character Tests
    // =====================

    #[Test]
    public function it_adds_a_character_to_a_party(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $character = Character::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/v1/parties/{$party->id}/characters", [
            'character_id' => $character->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('party_characters', [
            'party_id' => $party->id,
            'character_id' => $character->id,
        ]);
    }

    #[Test]
    public function it_validates_character_id_is_required(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/v1/parties/{$party->id}/characters", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['character_id']);
    }

    #[Test]
    public function it_validates_character_exists(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/v1/parties/{$party->id}/characters", [
            'character_id' => 999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['character_id']);
    }

    #[Test]
    public function it_prevents_duplicate_character_in_party(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $character = Character::factory()->create();
        $party->characters()->attach($character);

        $response = $this->actingAs($user)->postJson("/api/v1/parties/{$party->id}/characters", [
            'character_id' => $character->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['character_id']);
    }

    // =====================
    // Remove Character Tests
    // =====================

    #[Test]
    public function it_removes_a_character_from_a_party(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $character = Character::factory()->create();
        $party->characters()->attach($character);

        $response = $this->actingAs($user)->deleteJson("/api/v1/parties/{$party->id}/characters/{$character->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('party_characters', [
            'party_id' => $party->id,
            'character_id' => $character->id,
        ]);
    }

    #[Test]
    public function it_returns_404_when_removing_character_not_in_party(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $character = Character::factory()->create();

        $response = $this->actingAs($user)->deleteJson("/api/v1/parties/{$party->id}/characters/{$character->id}");

        $response->assertNotFound();
    }
}
