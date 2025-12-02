<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\ClassFeature;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterLevelUpApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_levels_up_character(): void
    {
        $class = CharacterClass::factory()->create(['hit_die' => 8]);
        $race = Race::factory()->create();

        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(10)
            ->create(['level' => 1]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/level-up");

        $response->assertOk()
            ->assertJsonPath('previous_level', 1)
            ->assertJsonPath('new_level', 2);

        $this->assertEquals(2, $character->fresh()->level);
    }

    #[Test]
    public function it_returns_level_up_details(): void
    {
        $class = CharacterClass::factory()->create([
            'hit_die' => 8,
            'slug' => 'fighter',
        ]);
        $race = Race::factory()->create();

        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->withAbilityScores(['CON' => 14]) // +2 modifier
            ->withHitPoints(10)
            ->create(['level' => 1]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/level-up");

        $response->assertOk()
            ->assertJsonStructure([
                'previous_level',
                'new_level',
                'hp_increase',
                'new_max_hp',
                'features_gained',
                'spell_slots',
                'asi_pending',
            ])
            ->assertJsonPath('hp_increase', 7) // d8 avg(5) + CON(+2)
            ->assertJsonPath('new_max_hp', 17);
    }

    #[Test]
    public function it_returns_422_at_max_level(): void
    {
        $class = CharacterClass::factory()->create(['hit_die' => 8]);
        $race = Race::factory()->create();

        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(100)
            ->create(['level' => 20]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/level-up");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Character is already at maximum level (20).');
    }

    #[Test]
    public function it_returns_422_for_incomplete_character(): void
    {
        // Character without class
        $character = Character::factory()
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(10)
            ->create(['level' => 1]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/level-up");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Character must be complete before leveling up.');
    }

    #[Test]
    public function it_returns_404_for_nonexistent_character(): void
    {
        $response = $this->postJson('/api/v1/characters/99999/level-up');

        $response->assertNotFound();
    }

    #[Test]
    public function it_grants_class_features_at_new_level(): void
    {
        $class = CharacterClass::factory()->create(['hit_die' => 8]);
        $race = Race::factory()->create();

        ClassFeature::factory()
            ->forClass($class)
            ->atLevel(2)
            ->create(['feature_name' => 'Extra Attack']);

        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(10)
            ->create(['level' => 1]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/level-up");

        $response->assertOk();

        $featuresGained = $response->json('features_gained');
        $this->assertCount(1, $featuresGained);
        $this->assertEquals('Extra Attack', $featuresGained[0]['name']);
    }

    #[Test]
    public function it_includes_spell_slots_for_casters(): void
    {
        $class = CharacterClass::factory()->create([
            'hit_die' => 6,
            'slug' => 'wizard',
        ]);
        $race = Race::factory()->create();

        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(6)
            ->create(['level' => 1]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/level-up");

        $response->assertOk();

        $spellSlots = $response->json('spell_slots');
        $this->assertArrayHasKey('1', $spellSlots);
        $this->assertEquals(3, $spellSlots['1']); // Level 2 wizard: 3 1st-level slots
    }

    #[Test]
    public function it_sets_asi_pending_at_level_4(): void
    {
        $class = CharacterClass::factory()->create([
            'hit_die' => 8,
            'slug' => 'cleric',
        ]);
        $race = Race::factory()->create();

        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(20)
            ->create(['level' => 3]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/level-up");

        $response->assertOk()
            ->assertJsonPath('asi_pending', true);

        $this->assertEquals(1, $character->fresh()->asi_choices_remaining);
    }

    #[Test]
    public function it_increments_hp_correctly(): void
    {
        $class = CharacterClass::factory()->create(['hit_die' => 10]);
        $race = Race::factory()->create();

        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->withAbilityScores(['CON' => 16]) // +3 modifier
            ->create([
                'level' => 1,
                'max_hit_points' => 13, // 10 + 3
                'current_hit_points' => 10, // Damaged
            ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/level-up");

        $response->assertOk()
            ->assertJsonPath('hp_increase', 9) // d10 avg(6) + CON(+3)
            ->assertJsonPath('new_max_hp', 22); // 13 + 9

        $character->refresh();
        $this->assertEquals(22, $character->max_hit_points);
        $this->assertEquals(19, $character->current_hit_points); // 10 + 9
    }

    #[Test]
    public function character_resource_includes_asi_choices_remaining(): void
    {
        $class = CharacterClass::factory()->create([
            'hit_die' => 8,
            'slug' => 'fighter',
        ]);
        $race = Race::factory()->create();

        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(10)
            ->create([
                'level' => 4,
                'asi_choices_remaining' => 1,
            ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.asi_choices_remaining', 1);
    }
}
