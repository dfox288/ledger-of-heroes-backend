<?php

namespace Tests\Feature\Api;

use App\Models\EncounterPreset;
use App\Models\Monster;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PartyEncounterPresetApiTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // Index Tests
    // =====================

    #[Test]
    public function it_lists_presets_for_party(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $preset = EncounterPreset::factory()->create([
            'party_id' => $party->id,
            'name' => 'Goblin Patrol',
        ]);

        $monster = Monster::factory()->create(['name' => 'Goblin', 'challenge_rating' => '1/4']);
        $preset->monsters()->attach($monster->id, ['quantity' => 4]);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/encounter-presets");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'monsters' => [
                            '*' => [
                                'monster_id',
                                'quantity',
                                'monster_name',
                                'challenge_rating',
                            ],
                        ],
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        expect($response->json('data.0.name'))->toBe('Goblin Patrol');
        expect($response->json('data.0.monsters.0.quantity'))->toBe(4);
        expect($response->json('data.0.monsters.0.monster_name'))->toBe('Goblin');
    }

    #[Test]
    public function it_returns_empty_array_for_party_with_no_presets(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/encounter-presets");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_only_returns_presets_for_specified_party(): void
    {
        $user = User::factory()->create();
        $party1 = Party::factory()->create(['user_id' => $user->id]);
        $party2 = Party::factory()->create(['user_id' => $user->id]);

        EncounterPreset::factory()->create(['party_id' => $party1->id, 'name' => 'Party 1 Preset']);
        EncounterPreset::factory()->create(['party_id' => $party2->id, 'name' => 'Party 2 Preset']);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party1->id}/encounter-presets");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
        expect($response->json('data.0.name'))->toBe('Party 1 Preset');
    }

    // =====================
    // Store Tests
    // =====================

    #[Test]
    public function it_creates_preset_with_monsters(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $goblin = Monster::factory()->create(['name' => 'Goblin', 'challenge_rating' => '1/4']);
        $hobgoblin = Monster::factory()->create(['name' => 'Hobgoblin', 'challenge_rating' => '1/2']);

        $response = $this->actingAs($user)->postJson("/api/v1/parties/{$party->id}/encounter-presets", [
            'name' => 'Goblin Patrol',
            'monsters' => [
                ['monster_id' => $goblin->id, 'quantity' => 4],
                ['monster_id' => $hobgoblin->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Goblin Patrol')
            ->assertJsonCount(2, 'data.monsters');

        $this->assertDatabaseHas('encounter_presets', [
            'party_id' => $party->id,
            'name' => 'Goblin Patrol',
        ]);

        $this->assertDatabaseHas('encounter_preset_monsters', [
            'monster_id' => $goblin->id,
            'quantity' => 4,
        ]);
    }

    #[Test]
    public function it_validates_name_is_required(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/v1/parties/{$party->id}/encounter-presets", [
            'monsters' => [
                ['monster_id' => $monster->id, 'quantity' => 1],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function it_validates_monsters_is_required(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/v1/parties/{$party->id}/encounter-presets", [
            'name' => 'Empty Preset',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['monsters']);
    }

    #[Test]
    public function it_validates_monsters_array_is_not_empty(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/v1/parties/{$party->id}/encounter-presets", [
            'name' => 'Empty Preset',
            'monsters' => [],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['monsters']);
    }

    #[Test]
    public function it_validates_monster_id_exists(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/v1/parties/{$party->id}/encounter-presets", [
            'name' => 'Bad Preset',
            'monsters' => [
                ['monster_id' => 99999, 'quantity' => 1],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['monsters.0.monster_id']);
    }

    #[Test]
    public function it_validates_quantity_is_positive(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/v1/parties/{$party->id}/encounter-presets", [
            'name' => 'Bad Preset',
            'monsters' => [
                ['monster_id' => $monster->id, 'quantity' => 0],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['monsters.0.quantity']);
    }

    #[Test]
    public function it_defaults_quantity_to_1(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/v1/parties/{$party->id}/encounter-presets", [
            'name' => 'Single Monster',
            'monsters' => [
                ['monster_id' => $monster->id],
            ],
        ]);

        $response->assertCreated();
        expect($response->json('data.monsters.0.quantity'))->toBe(1);
    }

    // =====================
    // Update Tests
    // =====================

    #[Test]
    public function it_updates_preset_name(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $preset = EncounterPreset::factory()->create([
            'party_id' => $party->id,
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($user)->patchJson(
            "/api/v1/parties/{$party->id}/encounter-presets/{$preset->id}",
            ['name' => 'New Name']
        );

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('encounter_presets', [
            'id' => $preset->id,
            'name' => 'New Name',
        ]);
    }

    #[Test]
    public function it_returns_404_for_preset_not_in_party(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $otherParty = Party::factory()->create(['user_id' => $user->id]);
        $preset = EncounterPreset::factory()->create(['party_id' => $otherParty->id]);

        $response = $this->actingAs($user)->patchJson(
            "/api/v1/parties/{$party->id}/encounter-presets/{$preset->id}",
            ['name' => 'New Name']
        );

        $response->assertNotFound();
    }

    // =====================
    // Destroy Tests
    // =====================

    #[Test]
    public function it_deletes_preset(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $preset = EncounterPreset::factory()->create(['party_id' => $party->id]);
        $monster = Monster::factory()->create();
        $preset->monsters()->attach($monster->id, ['quantity' => 2]);

        $response = $this->actingAs($user)->deleteJson(
            "/api/v1/parties/{$party->id}/encounter-presets/{$preset->id}"
        );

        $response->assertNoContent();
        $this->assertDatabaseMissing('encounter_presets', ['id' => $preset->id]);
        $this->assertDatabaseMissing('encounter_preset_monsters', ['encounter_preset_id' => $preset->id]);
    }

    #[Test]
    public function it_returns_404_when_deleting_preset_not_in_party(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $otherParty = Party::factory()->create(['user_id' => $user->id]);
        $preset = EncounterPreset::factory()->create(['party_id' => $otherParty->id]);

        $response = $this->actingAs($user)->deleteJson(
            "/api/v1/parties/{$party->id}/encounter-presets/{$preset->id}"
        );

        $response->assertNotFound();
    }

    // =====================
    // Load Tests
    // =====================

    #[Test]
    public function it_loads_preset_into_encounter(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $goblin = Monster::factory()->create(['name' => 'Goblin', 'hit_points_average' => 7]);
        $hobgoblin = Monster::factory()->create(['name' => 'Hobgoblin', 'hit_points_average' => 11]);

        $preset = EncounterPreset::factory()->create(['party_id' => $party->id]);
        $preset->monsters()->attach($goblin->id, ['quantity' => 2]);
        $preset->monsters()->attach($hobgoblin->id, ['quantity' => 1]);

        $response = $this->actingAs($user)->postJson(
            "/api/v1/parties/{$party->id}/encounter-presets/{$preset->id}/load"
        );

        $response->assertCreated()
            ->assertJsonCount(3, 'data');

        // Check encounter monsters were created
        $this->assertDatabaseHas('encounter_monsters', [
            'party_id' => $party->id,
            'monster_id' => $goblin->id,
            'label' => 'Goblin 1',
        ]);
        $this->assertDatabaseHas('encounter_monsters', [
            'party_id' => $party->id,
            'monster_id' => $goblin->id,
            'label' => 'Goblin 2',
        ]);
        $this->assertDatabaseHas('encounter_monsters', [
            'party_id' => $party->id,
            'monster_id' => $hobgoblin->id,
            'label' => 'Hobgoblin 1',
        ]);
    }

    #[Test]
    public function it_continues_labeling_when_loading_preset(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $goblin = Monster::factory()->create(['name' => 'Goblin', 'hit_points_average' => 7]);

        // Add existing goblins to encounter
        $party->encounterMonsters()->create([
            'monster_id' => $goblin->id,
            'label' => 'Goblin 1',
            'current_hp' => 7,
            'max_hp' => 7,
        ]);
        $party->encounterMonsters()->create([
            'monster_id' => $goblin->id,
            'label' => 'Goblin 2',
            'current_hp' => 7,
            'max_hp' => 7,
        ]);

        $preset = EncounterPreset::factory()->create(['party_id' => $party->id]);
        $preset->monsters()->attach($goblin->id, ['quantity' => 2]);

        $response = $this->actingAs($user)->postJson(
            "/api/v1/parties/{$party->id}/encounter-presets/{$preset->id}/load"
        );

        $response->assertCreated();

        // Should continue from Goblin 3
        expect($response->json('data.0.label'))->toBe('Goblin 3');
        expect($response->json('data.1.label'))->toBe('Goblin 4');
    }

    #[Test]
    public function it_skips_deleted_monsters_when_loading_preset(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $goblin = Monster::factory()->create(['name' => 'Goblin', 'hit_points_average' => 7]);
        $deletedMonster = Monster::factory()->create(['name' => 'Deleted Monster']);

        $preset = EncounterPreset::factory()->create(['party_id' => $party->id]);
        $preset->monsters()->attach($goblin->id, ['quantity' => 2]);
        $preset->monsters()->attach($deletedMonster->id, ['quantity' => 1]);

        // Delete the monster after attaching to preset
        $deletedMonster->delete();

        $response = $this->actingAs($user)->postJson(
            "/api/v1/parties/{$party->id}/encounter-presets/{$preset->id}/load"
        );

        $response->assertCreated()
            ->assertJsonCount(2, 'data'); // Only goblins, not the deleted monster
    }

    #[Test]
    public function it_returns_404_when_loading_preset_not_in_party(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $otherParty = Party::factory()->create(['user_id' => $user->id]);
        $preset = EncounterPreset::factory()->create(['party_id' => $otherParty->id]);

        $response = $this->actingAs($user)->postJson(
            "/api/v1/parties/{$party->id}/encounter-presets/{$preset->id}/load"
        );

        $response->assertNotFound();
    }

    #[Test]
    public function it_sets_correct_hp_when_loading_preset(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $monster = Monster::factory()->create(['name' => 'Ogre', 'hit_points_average' => 59]);

        $preset = EncounterPreset::factory()->create(['party_id' => $party->id]);
        $preset->monsters()->attach($monster->id, ['quantity' => 1]);

        $response = $this->actingAs($user)->postJson(
            "/api/v1/parties/{$party->id}/encounter-presets/{$preset->id}/load"
        );

        $response->assertCreated();
        expect($response->json('data.0.current_hp'))->toBe(59);
        expect($response->json('data.0.max_hp'))->toBe(59);
    }
}
