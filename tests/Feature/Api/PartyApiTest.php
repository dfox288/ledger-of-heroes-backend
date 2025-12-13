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

    // =====================
    // Stats Endpoint Tests
    // =====================

    #[Test]
    public function it_returns_party_stats_with_character_data(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        // Create a character with full stats
        $character = Character::factory()
            ->withAbilityScores([
                'strength' => 16,
                'dexterity' => 14,
                'constitution' => 14,
                'intelligence' => 10,
                'wisdom' => 12,
                'charisma' => 8,
            ])
            ->withHitPoints(45, 38)
            ->withArmorClassOverride(18)
            ->level(5)
            ->create();

        $party->characters()->attach($character);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'party' => ['id', 'name'],
                    'characters' => [
                        '*' => [
                            'id',
                            'public_id',
                            'name',
                            'level',
                            'class_name',
                            'hit_points' => ['current', 'max', 'temp'],
                            'armor_class',
                            'proficiency_bonus',
                            'senses' => ['passive_perception', 'passive_investigation', 'passive_insight', 'darkvision'],
                            'saving_throws' => ['STR', 'DEX', 'CON', 'INT', 'WIS', 'CHA'],
                            'conditions',
                            'spell_slots',
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_returns_correct_stat_values(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        // Create character with known ability scores for calculation verification
        // WIS 14 = +2 modifier, level 5 = +3 proficiency
        $character = Character::factory()
            ->withAbilityScores([
                'strength' => 10,     // +0 modifier
                'dexterity' => 14,    // +2 modifier
                'constitution' => 12, // +1 modifier
                'intelligence' => 8,  // -1 modifier
                'wisdom' => 14,       // +2 modifier
                'charisma' => 10,     // +0 modifier
            ])
            ->withHitPoints(35, 28)
            ->withArmorClassOverride(15)
            ->level(5)
            ->create();

        $party->characters()->attach($character);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk();

        $stats = $response->json('data.characters.0');

        // Verify HP values
        expect($stats['hit_points']['current'])->toBe(28);
        expect($stats['hit_points']['max'])->toBe(35);

        // Verify AC
        expect($stats['armor_class'])->toBe(15);

        // Level 5 = proficiency bonus +3
        expect($stats['proficiency_bonus'])->toBe(3);

        // Passive Perception = 10 + WIS modifier (no proficiency assumed)
        // WIS 14 = +2, so passive = 12
        expect($stats['senses']['passive_perception'])->toBe(12);

        // Passive Investigation = 10 + INT modifier
        // INT 8 = -1, so passive = 9
        expect($stats['senses']['passive_investigation'])->toBe(9);

        // Passive Insight = 10 + WIS modifier
        // WIS 14 = +2, so passive = 12
        expect($stats['senses']['passive_insight'])->toBe(12);
    }

    #[Test]
    public function it_returns_saving_throw_modifiers(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $character = Character::factory()
            ->withAbilityScores([
                'strength' => 16,     // +3 modifier
                'dexterity' => 14,    // +2 modifier
                'constitution' => 12, // +1 modifier
                'intelligence' => 10, // +0 modifier
                'wisdom' => 8,        // -1 modifier
                'charisma' => 14,     // +2 modifier
            ])
            ->level(1)
            ->create();

        $party->characters()->attach($character);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk();

        $saves = $response->json('data.characters.0.saving_throws');

        // Without proficiency, saving throws = ability modifier only
        expect($saves['STR'])->toBe(3);
        expect($saves['DEX'])->toBe(2);
        expect($saves['CON'])->toBe(1);
        expect($saves['INT'])->toBe(0);
        expect($saves['WIS'])->toBe(-1);
        expect($saves['CHA'])->toBe(2);
    }

    #[Test]
    public function it_includes_character_conditions(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $character = Character::factory()->create();

        // Add a condition to the character
        \App\Models\CharacterCondition::factory()->create([
            'character_id' => $character->id,
            'condition_slug' => 'poisoned',
            'level' => null,
        ]);

        $party->characters()->attach($character);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk();
        expect($response->json('data.characters.0.conditions'))->toHaveCount(1);
        expect($response->json('data.characters.0.conditions.0.slug'))->toBe('poisoned');
    }

    #[Test]
    public function it_returns_empty_array_for_party_with_no_characters(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.characters', []);
    }

    #[Test]
    public function it_returns_404_for_other_users_party_stats(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_stats_for_multiple_characters(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $characters = Character::factory()->count(3)->create();
        $party->characters()->attach($characters);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk()
            ->assertJsonCount(3, 'data.characters');
    }

    // =====================
    // Phase 1: Combat Quick Reference
    // =====================

    #[Test]
    public function it_returns_combat_initiative_modifier(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        // DEX 16 = +3 modifier
        $character = Character::factory()
            ->withAbilityScores([
                'strength' => 10,
                'dexterity' => 16,
                'constitution' => 10,
                'intelligence' => 10,
                'wisdom' => 10,
                'charisma' => 10,
            ])
            ->create();

        $party->characters()->attach($character);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk();
        expect($response->json('data.characters.0.combat.initiative_modifier'))->toBe(3);
    }

    #[Test]
    public function it_returns_combat_speeds(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        // Create a race with multiple speeds
        $race = \App\Models\Race::factory()->create([
            'speed' => 30,
            'fly_speed' => 50,
            'swim_speed' => null,
            'climb_speed' => 20,
        ]);

        $character = Character::factory()->create(['race_slug' => $race->slug]);
        $party->characters()->attach($character);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk();

        $speeds = $response->json('data.characters.0.combat.speeds');
        expect($speeds['walk'])->toBe(30);
        expect($speeds['fly'])->toBe(50);
        expect($speeds['swim'])->toBeNull();
        expect($speeds['climb'])->toBe(20);
    }

    #[Test]
    public function it_returns_combat_death_saves(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $character = Character::factory()->create([
            'death_save_successes' => 2,
            'death_save_failures' => 1,
        ]);

        $party->characters()->attach($character);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk();

        $deathSaves = $response->json('data.characters.0.combat.death_saves');
        expect($deathSaves['successes'])->toBe(2);
        expect($deathSaves['failures'])->toBe(1);
    }

    #[Test]
    public function it_returns_combat_concentration_status(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $character = Character::factory()->create();
        $party->characters()->attach($character);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk();

        // Concentration tracking is a placeholder for future implementation
        $concentration = $response->json('data.characters.0.combat.concentration');
        expect($concentration['active'])->toBeFalse();
        expect($concentration['spell'])->toBeNull();
    }

    // =====================
    // Phase 2: Senses & Capabilities
    // =====================

    #[Test]
    public function it_returns_senses_with_darkvision(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        // Create a race with darkvision
        $race = \App\Models\Race::factory()->create();
        $sense = \App\Models\Sense::firstOrCreate(
            ['slug' => 'core:darkvision'],
            ['name' => 'Darkvision']
        );
        \App\Models\EntitySense::create([
            'reference_type' => \App\Models\Race::class,
            'reference_id' => $race->id,
            'sense_id' => $sense->id,
            'range_feet' => 60,
        ]);

        $character = Character::factory()
            ->withAbilityScores([
                'strength' => 10,
                'dexterity' => 10,
                'constitution' => 10,
                'intelligence' => 10,
                'wisdom' => 14, // +2 for perception
                'charisma' => 10,
            ])
            ->create(['race_slug' => $race->slug]);

        $party->characters()->attach($character);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk();

        $senses = $response->json('data.characters.0.senses');
        expect($senses['darkvision'])->toBe(60);
        // Passive perception should still work (moved from top level)
        expect($senses['passive_perception'])->toBe(12);
    }

    #[Test]
    public function it_returns_capabilities_languages(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $character = Character::factory()->create();

        // Add languages with unique names for test isolation
        $uniqueId = uniqid();
        $common = \App\Models\Language::create([
            'slug' => 'test:common-'.$uniqueId,
            'name' => 'Common '.$uniqueId,
        ]);
        $elvish = \App\Models\Language::create([
            'slug' => 'test:elvish-'.$uniqueId,
            'name' => 'Elvish '.$uniqueId,
        ]);

        \App\Models\CharacterLanguage::create([
            'character_id' => $character->id,
            'language_slug' => $common->slug,
            'source' => 'race',
        ]);
        \App\Models\CharacterLanguage::create([
            'character_id' => $character->id,
            'language_slug' => $elvish->slug,
            'source' => 'race',
        ]);

        $party->characters()->attach($character);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk();

        $languages = $response->json('data.characters.0.capabilities.languages');
        expect($languages)->toContain($common->name);
        expect($languages)->toContain($elvish->name);
        expect($languages)->toHaveCount(2);
    }

    #[Test]
    public function it_returns_capabilities_size(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $size = \App\Models\Size::firstOrCreate(
            ['code' => 'M'],
            ['name' => 'Medium']
        );
        $race = \App\Models\Race::factory()->create(['size_id' => $size->id]);

        $character = Character::factory()->create(['race_slug' => $race->slug]);
        $party->characters()->attach($character);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk();
        expect($response->json('data.characters.0.capabilities.size'))->toBe('Medium');
    }

    #[Test]
    public function it_returns_capabilities_tool_proficiencies(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $character = Character::factory()->create();

        // Create tool proficiencies
        $smithsTools = \App\Models\ProficiencyType::firstOrCreate(
            ['slug' => 'core:smiths-tools'],
            ['name' => "Smith's Tools", 'category' => 'tool']
        );
        $thievesTools = \App\Models\ProficiencyType::firstOrCreate(
            ['slug' => 'core:thieves-tools'],
            ['name' => "Thieves' Tools", 'category' => 'tool']
        );

        \App\Models\CharacterProficiency::create([
            'character_id' => $character->id,
            'proficiency_type_slug' => $smithsTools->slug,
            'source' => 'background',
        ]);
        \App\Models\CharacterProficiency::create([
            'character_id' => $character->id,
            'proficiency_type_slug' => $thievesTools->slug,
            'source' => 'class',
        ]);

        $party->characters()->attach($character);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk();

        $tools = $response->json('data.characters.0.capabilities.tool_proficiencies');
        expect($tools)->toContain("Smith's Tools");
        expect($tools)->toContain("Thieves' Tools");
        expect($tools)->toHaveCount(2);
    }

    // =====================
    // Phase 3: Party Summary Aggregations
    // =====================

    #[Test]
    public function it_returns_party_summary_all_languages(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $char1 = Character::factory()->create();
        $char2 = Character::factory()->create();

        $uniqueId = uniqid();
        $common = \App\Models\Language::create(['slug' => 'test:common-agg-'.$uniqueId, 'name' => 'Common '.$uniqueId]);
        $elvish = \App\Models\Language::create(['slug' => 'test:elvish-agg-'.$uniqueId, 'name' => 'Elvish '.$uniqueId]);
        $dwarvish = \App\Models\Language::create(['slug' => 'test:dwarvish-agg-'.$uniqueId, 'name' => 'Dwarvish '.$uniqueId]);

        // Char1 knows Common and Elvish
        \App\Models\CharacterLanguage::create(['character_id' => $char1->id, 'language_slug' => $common->slug, 'source' => 'race']);
        \App\Models\CharacterLanguage::create(['character_id' => $char1->id, 'language_slug' => $elvish->slug, 'source' => 'race']);

        // Char2 knows Common and Dwarvish
        \App\Models\CharacterLanguage::create(['character_id' => $char2->id, 'language_slug' => $common->slug, 'source' => 'race']);
        \App\Models\CharacterLanguage::create(['character_id' => $char2->id, 'language_slug' => $dwarvish->slug, 'source' => 'race']);

        $party->characters()->attach([$char1->id, $char2->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk();

        $allLanguages = $response->json('data.party_summary.all_languages');
        expect($allLanguages)->toContain($common->name);
        expect($allLanguages)->toContain($elvish->name);
        expect($allLanguages)->toContain($dwarvish->name);
        // Common appears in both but should be deduplicated
        expect(count($allLanguages))->toBe(3);
    }

    #[Test]
    public function it_returns_party_summary_darkvision_info(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        // Create race with darkvision
        $raceWithDarkvision = \App\Models\Race::factory()->create();
        $sense = \App\Models\Sense::firstOrCreate(['slug' => 'core:darkvision'], ['name' => 'Darkvision']);
        \App\Models\EntitySense::create([
            'reference_type' => \App\Models\Race::class,
            'reference_id' => $raceWithDarkvision->id,
            'sense_id' => $sense->id,
            'range_feet' => 60,
        ]);

        // Create race without darkvision
        $raceWithoutDarkvision = \App\Models\Race::factory()->create();

        $char1 = Character::factory()->create(['name' => 'Elf', 'race_slug' => $raceWithDarkvision->slug]);
        $char2 = Character::factory()->create(['name' => 'Human', 'race_slug' => $raceWithoutDarkvision->slug]);
        $char3 = Character::factory()->create(['name' => 'Dwarf', 'race_slug' => $raceWithDarkvision->slug]);

        $party->characters()->attach([$char1->id, $char2->id, $char3->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk();

        $summary = $response->json('data.party_summary');
        expect($summary['darkvision_count'])->toBe(2);
        expect($summary['no_darkvision'])->toContain('Human');
        expect($summary['no_darkvision'])->toHaveCount(1);
    }

    #[Test]
    public function it_returns_party_summary_healer_info(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        // Create a cleric class using factory
        $clericClass = \App\Models\CharacterClass::factory()->create([
            'slug' => 'test:cleric-healer-'.uniqid(),
            'name' => 'Cleric',
        ]);

        // Create a fighter class using factory
        $fighterClass = \App\Models\CharacterClass::factory()->create([
            'slug' => 'test:fighter-healer-'.uniqid(),
            'name' => 'Fighter',
        ]);

        $healer = Character::factory()->create(['name' => 'Mira']);
        \App\Models\CharacterClassPivot::create([
            'character_id' => $healer->id,
            'class_slug' => $clericClass->slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $fighter = Character::factory()->create(['name' => 'Aldric']);
        \App\Models\CharacterClassPivot::create([
            'character_id' => $fighter->id,
            'class_slug' => $fighterClass->slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $party->characters()->attach([$healer->id, $fighter->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk();

        $summary = $response->json('data.party_summary');
        expect($summary['has_healer'])->toBeTrue();
        expect($summary['healers'])->toContain('Mira (Cleric)');
    }

    #[Test]
    public function it_returns_party_summary_utility_spells(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $character = Character::factory()->create();

        // Create spells using factory
        $detectMagic = \App\Models\Spell::factory()->create([
            'slug' => 'test:detect-magic',
            'name' => 'Detect Magic',
            'level' => 1,
        ]);
        $counterspell = \App\Models\Spell::factory()->create([
            'slug' => 'test:counterspell',
            'name' => 'Counterspell',
            'level' => 3,
        ]);

        // Add spells to character
        \App\Models\CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $detectMagic->slug,
            'source' => 'class',
        ]);
        \App\Models\CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $counterspell->slug,
            'source' => 'class',
        ]);

        $party->characters()->attach($character);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk();

        $summary = $response->json('data.party_summary');
        expect($summary['has_detect_magic'])->toBeTrue();
        expect($summary['has_counterspell'])->toBeTrue();
        expect($summary['has_dispel_magic'])->toBeFalse();
    }

    // =====================
    // Phase 4: Equipment
    // =====================

    #[Test]
    public function it_returns_equipment_armor(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $character = Character::factory()->create();

        // Create heavy armor type and item using factory
        $heavyArmorType = \App\Models\ItemType::firstOrCreate(
            ['code' => 'HA'],
            ['name' => 'Heavy Armor']
        );
        $plateArmor = \App\Models\Item::factory()->create([
            'slug' => 'test:plate-'.uniqid(),
            'name' => 'Plate',
            'item_type_id' => $heavyArmorType->id,
        ]);

        \App\Models\CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => $plateArmor->slug,
            'quantity' => 1,
            'equipped' => true,
        ]);

        $party->characters()->attach($character);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk();

        $armor = $response->json('data.characters.0.equipment.armor');
        expect($armor['name'])->toBe('Plate');
        expect($armor['type'])->toBe('heavy');
        expect($armor['stealth_disadvantage'])->toBeTrue();
    }

    #[Test]
    public function it_returns_equipment_weapons(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $character = Character::factory()->create();

        // Create weapon type and items using factory
        $meleeType = \App\Models\ItemType::firstOrCreate(
            ['code' => 'M'],
            ['name' => 'Melee Weapon']
        );
        $uniqueId = uniqid();
        $longsword = \App\Models\Item::factory()->create([
            'slug' => 'test:longsword-'.$uniqueId,
            'name' => 'Longsword',
            'item_type_id' => $meleeType->id,
        ]);
        $longbow = \App\Models\Item::factory()->create([
            'slug' => 'test:longbow-'.$uniqueId,
            'name' => 'Longbow',
            'item_type_id' => $meleeType->id,
        ]);

        \App\Models\CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => $longsword->slug,
            'quantity' => 1,
            'equipped' => true,
        ]);
        \App\Models\CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => $longbow->slug,
            'quantity' => 1,
            'equipped' => true,
        ]);

        $party->characters()->attach($character);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk();

        $weapons = $response->json('data.characters.0.equipment.weapons');
        expect($weapons)->toHaveCount(2);

        $weaponNames = array_column($weapons, 'name');
        expect($weaponNames)->toContain('Longsword');
        expect($weaponNames)->toContain('Longbow');
    }

    #[Test]
    public function it_returns_equipment_shield(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $character = Character::factory()->create();

        // Create shield using factory
        $shieldType = \App\Models\ItemType::firstOrCreate(
            ['code' => 'S'],
            ['name' => 'Shield']
        );
        $shield = \App\Models\Item::factory()->create([
            'slug' => 'test:shield-'.uniqid(),
            'name' => 'Shield',
            'item_type_id' => $shieldType->id,
        ]);

        \App\Models\CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => $shield->slug,
            'quantity' => 1,
            'equipped' => true,
        ]);

        $party->characters()->attach($character);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk();
        expect($response->json('data.characters.0.equipment.shield'))->toBeTrue();
    }

    #[Test]
    public function it_returns_full_dm_screen_stats_structure(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $character = Character::factory()
            ->withAbilityScores([
                'strength' => 10,
                'dexterity' => 14,
                'constitution' => 12,
                'intelligence' => 10,
                'wisdom' => 14,
                'charisma' => 8,
            ])
            ->level(5)
            ->create();

        $party->characters()->attach($character);

        $response = $this->actingAs($user)->getJson("/api/v1/parties/{$party->id}/stats");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'party' => ['id', 'name'],
                    'characters' => [
                        '*' => [
                            'id',
                            'public_id',
                            'name',
                            'level',
                            'class_name',
                            'hit_points' => ['current', 'max', 'temp'],
                            'armor_class',
                            'proficiency_bonus',
                            'combat' => [
                                'initiative_modifier',
                                'speeds' => ['walk', 'fly', 'swim', 'climb'],
                                'death_saves' => ['successes', 'failures'],
                                'concentration' => ['active', 'spell'],
                            ],
                            'senses' => [
                                'passive_perception',
                                'passive_investigation',
                                'passive_insight',
                                'darkvision',
                            ],
                            'capabilities' => [
                                'languages',
                                'size',
                                'tool_proficiencies',
                            ],
                            'equipment' => [
                                'armor',
                                'weapons',
                                'shield',
                            ],
                            'saving_throws',
                            'conditions',
                            'spell_slots',
                        ],
                    ],
                    'party_summary' => [
                        'all_languages',
                        'darkvision_count',
                        'no_darkvision',
                        'has_healer',
                        'healers',
                        'has_detect_magic',
                        'has_dispel_magic',
                        'has_counterspell',
                    ],
                ],
            ]);
    }
}
