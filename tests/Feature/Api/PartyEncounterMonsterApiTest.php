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

        // Add an action using production format: ["DamageType Damage|+bonus|dice"]
        $monster->actions()->create([
            'action_type' => 'action',
            'name' => 'Scimitar',
            'description' => 'Melee Weapon Attack: +4 to hit, reach 5 ft., one target. Hit: 5 (1d6 + 2) slashing damage.',
            'attack_data' => '["Slashing Damage|+4|1d6+2"]',
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
    public function it_includes_damage_dice_in_monster_actions(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create(['name' => 'Goblin']);

        // Production format: ["DamageType Damage|+bonus|dice"]
        $monster->actions()->create([
            'action_type' => 'action',
            'name' => 'Scimitar',
            'description' => 'Melee Weapon Attack: +4 to hit, reach 5 ft., one target.',
            'attack_data' => '["Slashing Damage|+4|1d6+2"]',
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
        $action = $response->json('data.0.monster.actions.0');
        expect($action['name'])->toBe('Scimitar');
        expect($action['damage'])->toBe('1d6+2 slashing');
        expect($action['attack_bonus'])->toBe(4);
    }

    #[Test]
    public function it_returns_null_damage_for_actions_without_attack_data(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create(['name' => 'Dragon']);

        // Non-attack action (breath weapon, etc.)
        $monster->actions()->create([
            'action_type' => 'action',
            'name' => 'Fire Breath',
            'description' => 'The dragon exhales fire in a 15-foot cone.',
            'attack_data' => null,
            'sort_order' => 1,
        ]);

        $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Dragon 1',
            'current_hp' => 100,
            'max_hp' => 100,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/monsters");

        $response->assertOk();
        $action = $response->json('data.0.monster.actions.0');
        expect($action['name'])->toBe('Fire Breath');
        expect($action['damage'])->toBeNull();
        expect($action['attack_bonus'])->toBeNull();
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

    // =====================
    // Legendary Data Tests
    // =====================

    #[Test]
    public function it_includes_legendary_actions_for_legendary_monster(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->legendary(actionsPerRound: 3)->create();

        // Create legendary actions
        $monster->legendaryActions()->create([
            'name' => 'Detect',
            'description' => 'The dragon makes a Wisdom (Perception) check.',
            'action_cost' => 1,
            'is_lair_action' => false,
            'sort_order' => 1,
        ]);
        $monster->legendaryActions()->create([
            'name' => 'Tail Attack',
            'description' => 'The dragon makes a tail attack.',
            'action_cost' => 1,
            'is_lair_action' => false,
            'sort_order' => 2,
        ]);
        $monster->legendaryActions()->create([
            'name' => 'Wing Attack',
            'description' => 'The dragon beats its wings.',
            'action_cost' => 2,
            'is_lair_action' => false,
            'sort_order' => 3,
        ]);

        $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Ancient Red Dragon 1',
            'current_hp' => 546,
            'max_hp' => 546,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/monsters");

        $response->assertOk();
        $monsterData = $response->json('data.0.monster');

        // Should include legendary_actions structure
        expect($monsterData)->toHaveKey('legendary_actions');
        expect($monsterData['legendary_actions']['uses_per_round'])->toBe(3);
        expect($monsterData['legendary_actions']['actions'])->toHaveCount(3);

        // Verify action structure
        $firstAction = $monsterData['legendary_actions']['actions'][0];
        expect($firstAction)->toHaveKeys(['name', 'description', 'action_cost']);
        expect($firstAction['name'])->toBe('Detect');
        expect($firstAction['action_cost'])->toBe(1);
    }

    #[Test]
    public function it_includes_lair_actions_for_monster_with_lair(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->legendary()->create();

        // Create lair actions (is_lair_action = true)
        $monster->legendaryActions()->create([
            'name' => 'Magma Eruption',
            'description' => 'Magma erupts from a point the dragon can see.',
            'action_cost' => 0, // Lair actions typically don't have costs
            'is_lair_action' => true,
            'sort_order' => 1,
        ]);
        $monster->legendaryActions()->create([
            'name' => 'Volcanic Gas',
            'description' => 'Volcanic gases form a cloud.',
            'action_cost' => 0,
            'is_lair_action' => true,
            'sort_order' => 2,
        ]);

        $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Ancient Red Dragon 1',
            'current_hp' => 546,
            'max_hp' => 546,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/monsters");

        $response->assertOk();
        $monsterData = $response->json('data.0.monster');

        // Should include lair_actions array
        expect($monsterData)->toHaveKey('lair_actions');
        expect($monsterData['lair_actions'])->toHaveCount(2);

        // Verify lair action structure
        $firstLairAction = $monsterData['lair_actions'][0];
        expect($firstLairAction)->toHaveKeys(['name', 'description']);
        expect($firstLairAction['name'])->toBe('Magma Eruption');
    }

    #[Test]
    public function it_includes_legendary_resistance_for_monster_with_resistance(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->legendary(actionsPerRound: 3, resistanceUses: 3)->create();

        $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Ancient Red Dragon 1',
            'current_hp' => 546,
            'max_hp' => 546,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/monsters");

        $response->assertOk();
        $monsterData = $response->json('data.0.monster');

        // Should include legendary_resistance structure
        expect($monsterData)->toHaveKey('legendary_resistance');
        expect($monsterData['legendary_resistance']['uses_per_day'])->toBe(3);
    }

    #[Test]
    public function it_returns_null_legendary_data_for_non_legendary_monster(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create(); // Not legendary

        $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Goblin 1',
            'current_hp' => 7,
            'max_hp' => 7,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/monsters");

        $response->assertOk();
        $monsterData = $response->json('data.0.monster');

        // Non-legendary monsters should have null for legendary data
        expect($monsterData['legendary_actions'])->toBeNull();
        expect($monsterData['legendary_resistance'])->toBeNull();
        expect($monsterData['lair_actions'])->toBeNull();
    }

    #[Test]
    public function it_separates_legendary_actions_from_lair_actions(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->legendary()->create();

        // Create both legendary and lair actions
        $monster->legendaryActions()->create([
            'name' => 'Detect',
            'description' => 'The dragon makes a Wisdom (Perception) check.',
            'action_cost' => 1,
            'is_lair_action' => false,
            'sort_order' => 1,
        ]);
        $monster->legendaryActions()->create([
            'name' => 'Magma Eruption',
            'description' => 'Magma erupts from a point.',
            'action_cost' => 0,
            'is_lair_action' => true,
            'sort_order' => 1,
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

        // Legendary actions should only contain non-lair actions
        expect($monsterData['legendary_actions']['actions'])->toHaveCount(1);
        expect($monsterData['legendary_actions']['actions'][0]['name'])->toBe('Detect');

        // Lair actions should only contain lair actions
        expect($monsterData['lair_actions'])->toHaveCount(1);
        expect($monsterData['lair_actions'][0]['name'])->toBe('Magma Eruption');
    }

    // =====================
    // Legendary Usage Tracking Tests
    // =====================

    #[Test]
    public function it_includes_legendary_usage_tracking_fields_in_response(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->legendary()->create();

        $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Dragon 1',
            'current_hp' => 100,
            'max_hp' => 100,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/monsters");

        $response->assertOk();
        $encounterMonster = $response->json('data.0');

        // Should include usage tracking fields
        expect($encounterMonster)->toHaveKey('legendary_actions_used');
        expect($encounterMonster)->toHaveKey('legendary_resistance_used');
        expect($encounterMonster['legendary_actions_used'])->toBe(0);
        expect($encounterMonster['legendary_resistance_used'])->toBe(0);
    }

    #[Test]
    public function it_updates_legendary_actions_used(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->legendary(actionsPerRound: 3)->create();

        $encounterMonster = $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Dragon 1',
            'current_hp' => 100,
            'max_hp' => 100,
        ]);

        $response = $this->actingAs($user)->patchJson(
            "/api/v1/parties/{$party->id}/monsters/{$encounterMonster->id}",
            ['legendary_actions_used' => 2]
        );

        $response->assertOk()
            ->assertJsonPath('data.legendary_actions_used', 2);

        $this->assertDatabaseHas('encounter_monsters', [
            'id' => $encounterMonster->id,
            'legendary_actions_used' => 2,
        ]);
    }

    #[Test]
    public function it_updates_legendary_resistance_used(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->legendary(resistanceUses: 3)->create();

        $encounterMonster = $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Dragon 1',
            'current_hp' => 100,
            'max_hp' => 100,
        ]);

        $response = $this->actingAs($user)->patchJson(
            "/api/v1/parties/{$party->id}/monsters/{$encounterMonster->id}",
            ['legendary_resistance_used' => 1]
        );

        $response->assertOk()
            ->assertJsonPath('data.legendary_resistance_used', 1);

        $this->assertDatabaseHas('encounter_monsters', [
            'id' => $encounterMonster->id,
            'legendary_resistance_used' => 1,
        ]);
    }

    #[Test]
    public function it_validates_legendary_actions_used_is_not_negative(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->legendary()->create();

        $encounterMonster = $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Dragon 1',
            'current_hp' => 100,
            'max_hp' => 100,
        ]);

        $response = $this->actingAs($user)->patchJson(
            "/api/v1/parties/{$party->id}/monsters/{$encounterMonster->id}",
            ['legendary_actions_used' => -1]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['legendary_actions_used']);
    }

    #[Test]
    public function it_validates_legendary_resistance_used_is_not_negative(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->legendary()->create();

        $encounterMonster = $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Dragon 1',
            'current_hp' => 100,
            'max_hp' => 100,
        ]);

        $response = $this->actingAs($user)->patchJson(
            "/api/v1/parties/{$party->id}/monsters/{$encounterMonster->id}",
            ['legendary_resistance_used' => -1]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['legendary_resistance_used']);
    }

    #[Test]
    public function it_resets_legendary_usage_when_updating_to_zero(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->legendary()->create();

        $encounterMonster = $party->encounterMonsters()->create([
            'monster_id' => $monster->id,
            'label' => 'Dragon 1',
            'current_hp' => 100,
            'max_hp' => 100,
            'legendary_actions_used' => 3,
            'legendary_resistance_used' => 2,
        ]);

        $response = $this->actingAs($user)->patchJson(
            "/api/v1/parties/{$party->id}/monsters/{$encounterMonster->id}",
            [
                'legendary_actions_used' => 0,
                'legendary_resistance_used' => 0,
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.legendary_actions_used', 0)
            ->assertJsonPath('data.legendary_resistance_used', 0);
    }
}
