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

    /**
     * Generate hp_levels_resolved array for a character at a given level.
     * Level 1 HP is automatic, so we need levels 2 through current level resolved.
     */
    private function hpLevelsResolvedForLevel(int $level): array
    {
        if ($level <= 1) {
            return [];
        }

        return range(2, $level);
    }

    /** @test */
    public function it_increases_character_level_by_one(): void
    {
        $class = CharacterClass::factory()->create(['hit_die' => 8]);
        $race = Race::factory()->create();
        $character = Character::factory()
            ->withClass($class, level: 1)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(10)
            ->create();

        $result = $this->service->levelUp($character);

        $this->assertEquals(1, $result->previousLevel);
        $this->assertEquals(2, $result->newLevel);
        $this->assertEquals(2, $character->fresh()->total_level);
    }

    /** @test */
    public function it_increments_level_on_primary_class_pivot(): void
    {
        $class = CharacterClass::factory()->create(['hit_die' => 8]);
        $race = Race::factory()->create();
        $character = Character::factory()
            ->withClass($class, level: 1)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(10)
            ->create();

        $this->service->levelUp($character);

        $character->refresh();
        $pivot = $character->characterClasses->firstWhere('is_primary', true);
        $this->assertEquals(2, $pivot->level);
    }

    /** @test */
    public function it_does_not_modify_hp_directly(): void
    {
        $class = CharacterClass::factory()->create(['hit_die' => 8]);
        $race = Race::factory()->create();
        $character = Character::factory()
            ->withClass($class, level: 1)
            ->withRace($race)
            ->withAbilityScores(['CON' => 14])
            ->withHitPoints(10)
            ->create();

        $this->service->levelUp($character);

        $character->refresh();
        // HP should NOT be modified - it's handled by HP choice handler
        $this->assertEquals(10, $character->max_hit_points);
        $this->assertEquals(10, $character->current_hit_points);
    }

    /** @test */
    public function it_returns_zero_hp_increase_in_result(): void
    {
        $class = CharacterClass::factory()->create(['hit_die' => 8]);
        $race = Race::factory()->create();
        $character = Character::factory()
            ->withClass($class, level: 1)
            ->withRace($race)
            ->withAbilityScores(['CON' => 14])
            ->withHitPoints(10)
            ->create();

        $result = $this->service->levelUp($character);

        // HP increase is 0 because it's handled by choice system
        $this->assertEquals(0, $result->hpIncrease);
        $this->assertEquals(10, $result->newMaxHp);
    }

    /** @test */
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
            ->withClass($class, level: 1)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(10)
            ->create();

        $result = $this->service->levelUp($character);

        $this->assertCount(2, $result->featuresGained);
        $this->assertEquals('Extra Attack', $result->featuresGained[0]['name']);
        $this->assertEquals('Action Surge', $result->featuresGained[1]['name']);

        // Verify CharacterFeature records created
        $characterFeatures = CharacterFeature::where('character_id', $character->id)->get();
        $this->assertCount(2, $characterFeatures);
        $this->assertEquals(2, $characterFeatures->first()->level_acquired);
    }

    /** @test */
    public function it_returns_updated_spell_slots_for_casters(): void
    {
        $class = CharacterClass::factory()->create([
            'hit_die' => 6,
            'slug' => 'wizard',
        ]);
        $race = Race::factory()->create();

        $character = Character::factory()
            ->withClass($class, level: 1)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(6)
            ->create();

        $result = $this->service->levelUp($character);

        // Wizard at level 2 should have spell slots
        $this->assertArrayHasKey(1, $result->spellSlots);
        $this->assertEquals(3, $result->spellSlots[1]); // Level 2 wizard: 3 1st-level slots
    }

    /** @test */
    public function it_sets_asi_pending_at_level_4(): void
    {
        // Use generic slug to avoid triggering FightingStyleChoiceHandler
        $class = CharacterClass::factory()->create([
            'hit_die' => 8,
            'slug' => 'test-fighter',
        ]);
        $race = Race::factory()->create();

        $character = Character::factory()
            ->withClass($class, level: 3)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(20)
            ->create([
                'hp_levels_resolved' => $this->hpLevelsResolvedForLevel(3),
            ]);

        $result = $this->service->levelUp($character);

        $this->assertTrue($result->asiPending);
        $this->assertEquals(1, $character->fresh()->asi_choices_remaining);
    }

    /** @test */
    public function it_sets_asi_pending_at_fighter_level_6(): void
    {
        // Fighter gets ASI at level 6 (extra ASI compared to other classes)
        $class = CharacterClass::factory()->create([
            'hit_die' => 10,
            'slug' => 'fighter',
        ]);
        $race = Race::factory()->create();

        // Create a fighting style optional feature for the handler to check against
        $fightingStyle = \App\Models\OptionalFeature::factory()->create([
            'feature_type' => 'fighting_style',
            'name' => 'Defense',
        ]);

        $character = Character::factory()
            ->withClass($class, level: 5)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(30)
            ->create([
                'hp_levels_resolved' => $this->hpLevelsResolvedForLevel(5),
            ]);

        // Resolve the required fighting style choice
        \App\Models\FeatureSelection::create([
            'character_id' => $character->id,
            'class_slug' => $class->full_slug,
            'feature_type' => 'fighting_style',
            'optional_feature_slug' => $fightingStyle->full_slug,
        ]);

        $result = $this->service->levelUp($character);

        $this->assertTrue($result->asiPending);
        $this->assertEquals(1, $character->fresh()->asi_choices_remaining);
    }

    /** @test */
    public function it_sets_asi_pending_at_rogue_level_10(): void
    {
        $class = CharacterClass::factory()->create([
            'hit_die' => 8,
            'slug' => 'rogue',
        ]);
        $race = Race::factory()->create();

        $character = Character::factory()
            ->withClass($class, level: 9)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(50)
            ->create([
                'hp_levels_resolved' => $this->hpLevelsResolvedForLevel(9),
            ]);

        $result = $this->service->levelUp($character);

        $this->assertTrue($result->asiPending);
        $this->assertEquals(1, $character->fresh()->asi_choices_remaining);
    }

    /** @test */
    public function it_does_not_set_asi_at_non_asi_level(): void
    {
        $class = CharacterClass::factory()->create([
            'hit_die' => 8,
            'slug' => 'cleric',
        ]);
        $race = Race::factory()->create();

        $character = Character::factory()
            ->withClass($class, level: 2)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(10)
            ->create([
                'hp_levels_resolved' => $this->hpLevelsResolvedForLevel(2),
            ]);

        $result = $this->service->levelUp($character);

        $this->assertFalse($result->asiPending);
        $this->assertEquals(0, $character->fresh()->asi_choices_remaining);
    }

    /** @test */
    public function it_throws_exception_at_max_level(): void
    {
        $class = CharacterClass::factory()->create(['hit_die' => 8]);
        $race = Race::factory()->create();

        $character = Character::factory()
            ->withClass($class, level: 20)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(100)
            ->create([
                'hp_levels_resolved' => $this->hpLevelsResolvedForLevel(20),
            ]);

        $this->expectException(MaxLevelReachedException::class);

        $this->service->levelUp($character);
    }

    /** @test */
    public function it_throws_exception_for_incomplete_character(): void
    {
        // Character without class
        $character = Character::factory()
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(10)
            ->create();

        $this->expectException(IncompleteCharacterException::class);

        $this->service->levelUp($character);
    }

    /** @test */
    public function it_increments_asi_choices_remaining(): void
    {
        // Use generic slug to avoid triggering FightingStyleChoiceHandler
        $class = CharacterClass::factory()->create([
            'hit_die' => 8,
            'slug' => 'test-fighter',
        ]);
        $race = Race::factory()->create();

        // Start with 1 existing ASI choice
        $character = Character::factory()
            ->withClass($class, level: 3)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(20)
            ->create([
                'asi_choices_remaining' => 1,
                'hp_levels_resolved' => $this->hpLevelsResolvedForLevel(3),
            ]);

        $this->service->levelUp($character);

        // Level 4 is an ASI level, so should increment to 2
        $this->assertEquals(2, $character->fresh()->asi_choices_remaining);
    }

    /** @test */
    public function it_can_level_up_specific_class_for_multiclass(): void
    {
        // Use generic slugs to avoid triggering FightingStyleChoiceHandler
        $fighter = CharacterClass::factory()->create([
            'hit_die' => 10,
            'slug' => 'test-fighter',
        ]);
        $wizard = CharacterClass::factory()->create([
            'hit_die' => 6,
            'slug' => 'test-wizard',
        ]);
        $race = Race::factory()->create();

        $character = Character::factory()
            ->withClass($fighter, level: 3)
            ->withClass($wizard, level: 2)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(30)
            ->create([
                'hp_levels_resolved' => $this->hpLevelsResolvedForLevel(5),
            ]);

        // Level up the wizard specifically
        $result = $this->service->levelUp($character, $wizard->full_slug);

        $character->refresh();
        $fighterPivot = $character->characterClasses->firstWhere('class_slug', $fighter->full_slug);
        $wizardPivot = $character->characterClasses->firstWhere('class_slug', $wizard->full_slug);

        // Fighter should still be level 3
        $this->assertEquals(3, $fighterPivot->level);
        // Wizard should now be level 3
        $this->assertEquals(3, $wizardPivot->level);
        // Total level should be 6
        $this->assertEquals(6, $character->total_level);
        $this->assertEquals(5, $result->previousLevel);
        $this->assertEquals(6, $result->newLevel);
    }

    /** @test */
    public function it_defaults_to_primary_class_when_no_class_specified(): void
    {
        // Use generic slugs to avoid triggering FightingStyleChoiceHandler
        $fighter = CharacterClass::factory()->create([
            'hit_die' => 10,
            'slug' => 'test-fighter',
        ]);
        $wizard = CharacterClass::factory()->create([
            'hit_die' => 6,
            'slug' => 'test-wizard',
        ]);
        $race = Race::factory()->create();

        $character = Character::factory()
            ->withClass($fighter, level: 3) // Primary (added first)
            ->withClass($wizard, level: 2)
            ->withRace($race)
            ->withAbilityScores(['CON' => 10])
            ->withHitPoints(30)
            ->create([
                'hp_levels_resolved' => $this->hpLevelsResolvedForLevel(5),
            ]);

        // Level up without specifying class - should default to primary (fighter)
        $this->service->levelUp($character);

        $character->refresh();
        $fighterPivot = $character->characterClasses->firstWhere('class_slug', $fighter->full_slug);
        $wizardPivot = $character->characterClasses->firstWhere('class_slug', $wizard->full_slug);

        // Fighter (primary) should now be level 4
        $this->assertEquals(4, $fighterPivot->level);
        // Wizard should still be level 2
        $this->assertEquals(2, $wizardPivot->level);
    }

    /** @test */
    public function level_up_result_dto_converts_to_array(): void
    {
        $result = new LevelUpResult(
            previousLevel: 3,
            newLevel: 4,
            hpIncrease: 0,
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
            'hp_increase' => 0,
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
