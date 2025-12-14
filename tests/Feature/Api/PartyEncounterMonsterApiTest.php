<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PartyEncounterMonsterApiTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // Index Tests
    // =====================

    #[Test]
    public function it_lists_monsters_in_party_encounter(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create(['name' => 'Goblin', 'hit_points_average' => 7]);

        // Add monster to encounter
        $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Goblin 1',
            'current_hp' => 7,
            'max_hp' => 7,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/monsters");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'monster_id',
                        'label',
                        'current_hp',
                        'max_hp',
                        'monster' => [
                            'name',
                            'slug',
                            'armor_class',
                            'hit_points_average',
                            'speed_walk',
                            'challenge_rating',
                            'actions',
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_returns_empty_array_for_party_with_no_encounter_monsters(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/monsters");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_includes_monster_actions_in_response(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create(['name' => 'Goblin']);

        // Add an action to the monster
        $monster->actions()->create([
            'action_type' => 'action',
            'name' => 'Scimitar',
            'description' => 'Melee Weapon Attack: +4 to hit, reach 5 ft., one target. Hit: 5 (1d6 + 2) slashing damage.',
            'attack_data' => json_encode(['attack_bonus' => 4, 'damage' => '1d6+2']),
            'sort_order' => 1,
        ]);

        $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Goblin 1',
            'current_hp' => 7,
            'max_hp' => 7,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/monsters");

        $response->assertOk();
        expect($response->json('data.0.monster.actions'))->toHaveCount(1);
        expect($response->json('data.0.monster.actions.0.name'))->toBe('Scimitar');
    }

    #[Test]
    public function it_excludes_reactions_from_monster_actions(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create(['name' => 'Goblin']);

        // Add a regular action
        $monster->actions()->create([
            'action_type' => 'action',
            'name' => 'Scimitar',
            'description' => 'Melee Weapon Attack',
            'sort_order' => 1,
        ]);

        // Add a reaction (should be excluded)
        $monster->actions()->create([
            'action_type' => 'reaction',
            'name' => 'Parry',
            'description' => 'The goblin adds 2 to its AC against one melee attack.',
            'sort_order' => 2,
        ]);

        $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Goblin 1',
            'current_hp' => 7,
            'max_hp' => 7,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/monsters");

        $response->assertOk();
        // Should only include the action, not the reaction
        expect($response->json('data.0.monster.actions'))->toHaveCount(1);
        expect($response->json('data.0.monster.actions.0.name'))->toBe('Scimitar');
    }

    // =====================
    // Store Tests
    // =====================

    #[Test]
    public function it_adds_monster_to_encounter(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create(['name' => 'Goblin', 'hit_points_average' => 7]);

        $response = $this->actingAs($user)->postJson("/api/v1/parties/{$party->id}/monsters", [
            'monster_id' => $monster->id,
        ]);

        $response->assertCreated()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Goblin 1')
            ->assertJsonPath('data.0.current_hp', 7)
            ->assertJsonPath('data.0.max_hp', 7);

        $this->assertDatabaseHas('encounter_monsters', [
            'party_id' => $party->id,
            'monster_id' => $monster->id,
            'label' => 'Goblin 1',
        ]);
    }

    #[Test]
    public function it_adds_multiple_monsters_with_quantity(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create(['name' => 'Goblin', 'hit_points_average' => 7]);

        $response = $this->actingAs($user)->postJson("/api/v1/parties/{$party->id}/monsters", [
            'monster_id' => $monster->id,
            'quantity' => 3,
        ]);

        $response->assertCreated()
            ->assertJsonCount(3, 'data');

        // Check auto-labeling
        expect($response->json('data.0.label'))->toBe('Goblin 1');
        expect($response->json('data.1.label'))->toBe('Goblin 2');
        expect($response->json('data.2.label'))->toBe('Goblin 3');
    }

    #[Test]
    public function it_continues_numbering_for_existing_monsters_of_same_type(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create(['name' => 'Goblin', 'hit_points_average' => 7]);

        // Add existing goblins
        $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Goblin 1',
            'current_hp' => 7,
            'max_hp' => 7,
        ]);
        $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Goblin 2',
            'current_hp' => 7,
            'max_hp' => 7,
        ]);

        // Add more goblins
        $response = $this->actingAs($user)->postJson("/api/v1/parties/{$party->id}/monsters", [
            'monster_id' => $monster->id,
            'quantity' => 2,
        ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'data');

        // Should continue from Goblin 3
        expect($response->json('data.0.label'))->toBe('Goblin 3');
        expect($response->json('data.1.label'))->toBe('Goblin 4');
    }

    #[Test]
    public function it_numbers_different_monster_types_independently(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $goblin = Monster::factory()->create(['name' => 'Goblin', 'hit_points_average' => 7]);
        $orc = Monster::factory()->create(['name' => 'Orc', 'hit_points_average' => 15]);

        // Add goblins
        $party->encounterMonsters()->create([
            'monster_id' => $goblin->id,
            'label' => 'Goblin 1',
            'current_hp' => 7,
            'max_hp' => 7,
        ]);

        // Add orcs
        $response = $this->actingAs($user)->postJson("/api/v1/parties/{$party->id}/monsters", [
            'monster_id' => $orc->id,
            'quantity' => 2,
        ]);

        $response->assertCreated();

        // Orcs should start at 1, not continue goblin numbering
        expect($response->json('data.0.label'))->toBe('Orc 1');
        expect($response->json('data.1.label'))->toBe('Orc 2');
    }

    #[Test]
    public function it_validates_monster_id_is_required(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/v1/parties/{$party->id}/monsters", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['monster_id']);
    }

    #[Test]
    public function it_validates_monster_exists(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/v1/parties/{$party->id}/monsters", [
            'monster_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['monster_id']);
    }

    #[Test]
    public function it_validates_quantity_is_positive(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/v1/parties/{$party->id}/monsters", [
            'monster_id' => $monster->id,
            'quantity' => 0,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    #[Test]
    public function it_validates_quantity_max_is_20(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/v1/parties/{$party->id}/monsters", [
            'monster_id' => $monster->id,
            'quantity' => 21,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    // =====================
    // Update Tests
    // =====================

    #[Test]
    public function it_updates_encounter_monster_hp(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create(['name' => 'Goblin', 'hit_points_average' => 7]);

        $encounterMonster = $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Goblin 1',
            'current_hp' => 7,
            'max_hp' => 7,
        ]);

        $response = $this->actingAs($user)->patchJson(
            "/api/v1/parties/{$party->id}/monsters/{$encounterMonster->id}",
            ['current_hp' => 3]
        );

        $response->assertOk()
            ->assertJsonPath('data.current_hp', 3)
            ->assertJsonPath('data.max_hp', 7);

        $this->assertDatabaseHas('encounter_monsters', [
            'id' => $encounterMonster->id,
            'current_hp' => 3,
        ]);
    }

    #[Test]
    public function it_updates_encounter_monster_label(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create(['name' => 'Goblin']);

        $encounterMonster = $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Goblin 1',
            'current_hp' => 7,
            'max_hp' => 7,
        ]);

        $response = $this->actingAs($user)->patchJson(
            "/api/v1/parties/{$party->id}/monsters/{$encounterMonster->id}",
            ['label' => 'Goblin Boss']
        );

        $response->assertOk()
            ->assertJsonPath('data.label', 'Goblin Boss');
    }

    #[Test]
    public function it_validates_current_hp_is_not_negative(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create();

        $encounterMonster = $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Goblin 1',
            'current_hp' => 7,
            'max_hp' => 7,
        ]);

        $response = $this->actingAs($user)->patchJson(
            "/api/v1/parties/{$party->id}/monsters/{$encounterMonster->id}",
            ['current_hp' => -5]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['current_hp']);
    }

    #[Test]
    public function it_returns_404_for_monster_not_in_party(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $otherParty = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create();

        $encounterMonster = $otherParty->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Goblin 1',
            'current_hp' => 7,
            'max_hp' => 7,
        ]);

        $response = $this->actingAs($user)->patchJson(
            "/api/v1/parties/{$party->id}/monsters/{$encounterMonster->id}",
            ['current_hp' => 3]
        );

        $response->assertNotFound();
    }

    // =====================
    // Destroy Single Tests
    // =====================

    #[Test]
    public function it_removes_monster_from_encounter(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create();

        $encounterMonster = $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Goblin 1',
            'current_hp' => 7,
            'max_hp' => 7,
        ]);

        $response = $this->actingAs($user)->deleteJson(
            "/api/v1/parties/{$party->id}/monsters/{$encounterMonster->id}"
        );

        $response->assertNoContent();
        $this->assertDatabaseMissing('encounter_monsters', ['id' => $encounterMonster->id]);
    }

    #[Test]
    public function it_returns_404_when_removing_monster_not_in_party(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $otherParty = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create();

        $encounterMonster = $otherParty->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Goblin 1',
            'current_hp' => 7,
            'max_hp' => 7,
        ]);

        $response = $this->actingAs($user)->deleteJson(
            "/api/v1/parties/{$party->id}/monsters/{$encounterMonster->id}"
        );

        $response->assertNotFound();
    }

    // =====================
    // Clear All Tests
    // =====================

    #[Test]
    public function it_clears_all_monsters_from_encounter(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create();

        $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Goblin 1',
            'current_hp' => 7,
            'max_hp' => 7,
        ]);
        $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Goblin 2',
            'current_hp' => 7,
            'max_hp' => 7,
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/parties/{$party->id}/monsters");

        $response->assertNoContent();
        $this->assertDatabaseMissing('encounter_monsters', ['party_id' => $party->id]);
    }

    #[Test]
    public function it_clears_only_specified_party_monsters(): void
    {
        $user = User::factory()->create();
        $party1 = Party::factory()->create(['user_id' => $user->id]);
        $party2 = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create();

        $party1->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Goblin 1',
            'current_hp' => 7,
            'max_hp' => 7,
        ]);
        $party2->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Goblin 1',
            'current_hp' => 7,
            'max_hp' => 7,
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/parties/{$party1->id}/monsters");

        $response->assertNoContent();
        $this->assertDatabaseMissing('encounter_monsters', ['party_id' => $party1->id]);
        $this->assertDatabaseHas('encounter_monsters', ['party_id' => $party2->id]);
    }

    // =====================
    // Response Structure Tests
    // =====================

    #[Test]
    public function it_returns_monster_speed_data(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create([
            'speed_walk' => 30,
            'speed_fly' => 60,
            'speed_swim' => null,
        ]);

        $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Dragon 1',
            'current_hp' => 100,
            'max_hp' => 100,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/monsters");

        $response->assertOk();

        $monsterData = $response->json('data.0.monster');
        expect($monsterData['speed_walk'])->toBe(30);
        expect($monsterData['speed_fly'])->toBe(60);
    }
}
