<?php

namespace Tests\Unit\Services;

use App\DTOs\LevelUpResult;
use App\Exceptions\IncompleteCharacterException;
use App\Exceptions\MaxLevelReachedException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterFeature;
use App\Models\ClassFeature;
use App\Models\Race;
use App\Services\LevelUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LevelUpServiceTest extends TestCase
{
    use RefreshDatabase;

    private LevelUpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LevelUpService;
    }

    #[Test]
    public function it_increases_character_level_by_one(): void
    {
        $class = CharacterClass::factory()->create(['hit_die' => 8]);
        $race = Race::factory()->create();
        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(10)
            ->create(['level' => 1]);

        $result = $this->service->levelUp($character);

        $this->assertEquals(1, $result->previousLevel);
        $this->assertEquals(2, $result->newLevel);
        $this->assertEquals(2, $character->fresh()->level);
    }

    #[Test]
    public function it_calculates_hp_increase_with_con_modifier(): void
    {
        $class = CharacterClass::factory()->create(['hit_die' => 8]);
        $race = Race::factory()->create();

        // CON 14 = +2 modifier, d8 average = 5, so HP increase = 7
        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->withAbilityScores(['CON' => 14])
            ->withHitPoints(10)
            ->create(['level' => 1]);

        $result = $this->service->levelUp($character);

        $this->assertEquals(7, $result->hpIncrease);
        $this->assertEquals(17, $result->newMaxHp);
    }

    #[Test]
    public function it_uses_average_hit_die_for_hp(): void
    {
        $race = Race::factory()->create();

        // Test d6 (average 4)
        $classD6 = CharacterClass::factory()->create(['hit_die' => 6]);
        $characterD6 = Character::factory()
            ->withClass($classD6)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10]) // +0 modifier
            ->withHitPoints(10)
            ->create(['level' => 1]);

        $result = $this->service->levelUp($characterD6);
        $this->assertEquals(4, $result->hpIncrease); // d6 average = 4

        // Test d10 (average 6)
        $classD10 = CharacterClass::factory()->create(['hit_die' => 10]);
        $characterD10 = Character::factory()
            ->withClass($classD10)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(10)
            ->create(['level' => 1]);

        $result = $this->service->levelUp($characterD10);
        $this->assertEquals(6, $result->hpIncrease); // d10 average = 6

        // Test d12 (average 7)
        $classD12 = CharacterClass::factory()->create(['hit_die' => 12]);
        $characterD12 = Character::factory()
            ->withClass($classD12)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(10)
            ->create(['level' => 1]);

        $result = $this->service->levelUp($characterD12);
        $this->assertEquals(7, $result->hpIncrease); // d12 average = 7
    }

    #[Test]
    public function it_grants_class_features_for_new_level(): void
    {
        $class = CharacterClass::factory()->create(['hit_die' => 8]);
        $race = Race::factory()->create();

        // Create features at level 2
        $feature1 = ClassFeature::factory()
            ->forClass($class)
            ->atLevel(2)
            ->create(['feature_name' => 'Extra Attack']);

        $feature2 = ClassFeature::factory()
            ->forClass($class)
            ->atLevel(2)
            ->create(['feature_name' => 'Action Surge']);

        // Create optional feature (should NOT be granted)
        ClassFeature::factory()
            ->forClass($class)
            ->atLevel(2)
            ->optional()
            ->create(['feature_name' => 'Optional Feature']);

        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(10)
            ->create(['level' => 1]);

        $result = $this->service->levelUp($character);

        $this->assertCount(2, $result->featuresGained);
        $this->assertEquals('Extra Attack', $result->featuresGained[0]['name']);
        $this->assertEquals('Action Surge', $result->featuresGained[1]['name']);

        // Verify CharacterFeature records created
        $characterFeatures = CharacterFeature::where('character_id', $character->id)->get();
        $this->assertCount(2, $characterFeatures);
        $this->assertEquals(2, $characterFeatures->first()->level_acquired);
    }

    #[Test]
    public function it_returns_updated_spell_slots_for_casters(): void
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

        $result = $this->service->levelUp($character);

        // Wizard at level 2 should have spell slots
        $this->assertArrayHasKey(1, $result->spellSlots);
        $this->assertEquals(3, $result->spellSlots[1]); // Level 2 wizard: 3 1st-level slots
    }

    #[Test]
    public function it_sets_asi_pending_at_level_4(): void
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
            ->withHitPoints(20)
            ->create(['level' => 3]);

        $result = $this->service->levelUp($character);

        $this->assertTrue($result->asiPending);
        $this->assertEquals(1, $character->fresh()->asi_choices_remaining);
    }

    #[Test]
    public function it_sets_asi_pending_at_fighter_level_6(): void
    {
        $class = CharacterClass::factory()->create([
            'hit_die' => 10,
            'slug' => 'fighter',
        ]);
        $race = Race::factory()->create();

        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(30)
            ->create(['level' => 5]);

        $result = $this->service->levelUp($character);

        $this->assertTrue($result->asiPending);
        $this->assertEquals(1, $character->fresh()->asi_choices_remaining);
    }

    #[Test]
    public function it_sets_asi_pending_at_rogue_level_10(): void
    {
        $class = CharacterClass::factory()->create([
            'hit_die' => 8,
            'slug' => 'rogue',
        ]);
        $race = Race::factory()->create();

        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(50)
            ->create(['level' => 9]);

        $result = $this->service->levelUp($character);

        $this->assertTrue($result->asiPending);
        $this->assertEquals(1, $character->fresh()->asi_choices_remaining);
    }

    #[Test]
    public function it_does_not_set_asi_at_non_asi_level(): void
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
            ->withHitPoints(10)
            ->create(['level' => 2]);

        $result = $this->service->levelUp($character);

        $this->assertFalse($result->asiPending);
        $this->assertEquals(0, $character->fresh()->asi_choices_remaining);
    }

    #[Test]
    public function it_throws_exception_at_max_level(): void
    {
        $class = CharacterClass::factory()->create(['hit_die' => 8]);
        $race = Race::factory()->create();

        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(100)
            ->create(['level' => 20]);

        $this->expectException(MaxLevelReachedException::class);
        $this->expectExceptionMessage('Character is already at maximum level');

        $this->service->levelUp($character);
    }

    #[Test]
    public function it_throws_exception_for_incomplete_character(): void
    {
        // Character without class
        $character = Character::factory()
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(10)
            ->create(['level' => 1]);

        $this->expectException(IncompleteCharacterException::class);
        $this->expectExceptionMessage('Character must be complete before leveling up');

        $this->service->levelUp($character);
    }

    #[Test]
    public function it_handles_negative_con_modifier(): void
    {
        $class = CharacterClass::factory()->create(['hit_die' => 6]);
        $race = Race::factory()->create();

        // CON 6 = -2 modifier, d6 average = 4, so HP increase = max(1, 4 + -2) = 2
        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->withAbilityScores(['CON' => 6])
            ->withHitPoints(4)
            ->create(['level' => 1]);

        $result = $this->service->levelUp($character);

        $this->assertEquals(2, $result->hpIncrease);
    }

    #[Test]
    public function it_enforces_minimum_1_hp_on_level_up(): void
    {
        $class = CharacterClass::factory()->create(['hit_die' => 6]);
        $race = Race::factory()->create();

        // CON 3 = -4 modifier, d6 average = 4, so HP increase = max(1, 4 + -4) = max(1, 0) = 1
        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->withAbilityScores(['CON' => 3])
            ->withHitPoints(2)
            ->create(['level' => 1]);

        $result = $this->service->levelUp($character);

        $this->assertEquals(1, $result->hpIncrease);
    }

    #[Test]
    public function it_increments_asi_choices_remaining(): void
    {
        $class = CharacterClass::factory()->create([
            'hit_die' => 8,
            'slug' => 'fighter',
        ]);
        $race = Race::factory()->create();

        // Start with 1 existing ASI choice
        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(20)
            ->create([
                'level' => 3,
                'asi_choices_remaining' => 1,
            ]);

        $this->service->levelUp($character);

        // Level 4 is an ASI level, so should increment to 2
        $this->assertEquals(2, $character->fresh()->asi_choices_remaining);
    }

    #[Test]
    public function it_updates_current_hit_points_with_increase(): void
    {
        $class = CharacterClass::factory()->create(['hit_die' => 8]);
        $race = Race::factory()->create();

        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->create([
                'level' => 1,
                'max_hit_points' => 10,
                'current_hit_points' => 7, // Damaged
            ]);

        $result = $this->service->levelUp($character);

        $character->refresh();
        // HP increase of 5 (d8 average), both max and current should increase
        $this->assertEquals(15, $character->max_hit_points);
        $this->assertEquals(12, $character->current_hit_points);
    }

    #[Test]
    public function level_up_result_dto_converts_to_array(): void
    {
        $result = new LevelUpResult(
            previousLevel: 3,
            newLevel: 4,
            hpIncrease: 7,
            newMaxHp: 35,
            featuresGained: [
                ['id' => 1, 'name' => 'Ability Score Improvement', 'description' => 'Increase scores'],
            ],
            spellSlots: [1 => 4, 2 => 3],
            asiPending: true,
            hpChoicePending: true,
        );

        $array = $result->toArray();

        $this->assertEquals([
            'previous_level' => 3,
            'new_level' => 4,
            'hp_increase' => 7,
            'new_max_hp' => 35,
            'features_gained' => [
                ['id' => 1, 'name' => 'Ability Score Improvement', 'description' => 'Increase scores'],
            ],
            'spell_slots' => [1 => 4, 2 => 3],
            'asi_pending' => true,
            'hp_choice_pending' => true,
        ], $array);
    }
}
