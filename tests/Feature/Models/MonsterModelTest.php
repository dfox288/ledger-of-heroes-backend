<?php

namespace Tests\Feature\Models;

use App\Models\EntitySense;
use App\Models\Monster;
use App\Models\MonsterAction;
use App\Models\MonsterLegendaryAction;
use App\Models\Sense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Edge case tests for Monster model.
 *
 * Tests relationships, accessors, and boundary conditions.
 *
 * @see https://github.com/dfox288/ledger-of-heroes/issues/581
 */
#[Group('feature-db')]
class MonsterModelTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Action Edge Cases
    // =========================================================================

    #[Test]
    public function monster_with_no_actions_returns_empty_collection(): void
    {
        $monster = Monster::factory()->create();

        $this->assertCount(0, $monster->actions);
        $this->assertTrue($monster->actions->isEmpty());
    }

    #[Test]
    public function monster_with_single_action(): void
    {
        $monster = Monster::factory()->create();
        MonsterAction::create([
            'monster_id' => $monster->id,
            'action_type' => 'action',
            'name' => 'Bite',
            'description' => 'Melee Weapon Attack: +5 to hit',
        ]);

        $monster = $monster->fresh(['actions']);

        $this->assertCount(1, $monster->actions);
        $this->assertEquals('Bite', $monster->actions->first()->name);
    }

    #[Test]
    public function monster_with_multiple_actions(): void
    {
        $monster = Monster::factory()->create();
        MonsterAction::create(['monster_id' => $monster->id, 'action_type' => 'action', 'name' => 'Bite', 'description' => 'Bite attack']);
        MonsterAction::create(['monster_id' => $monster->id, 'action_type' => 'action', 'name' => 'Claw', 'description' => 'Claw attack']);
        MonsterAction::create(['monster_id' => $monster->id, 'action_type' => 'action', 'name' => 'Tail', 'description' => 'Tail attack']);

        $monster = $monster->fresh(['actions']);

        $this->assertCount(3, $monster->actions);
    }

    // =========================================================================
    // Legendary Action Edge Cases
    // =========================================================================

    #[Test]
    public function monster_with_no_legendary_actions_returns_empty_collection(): void
    {
        $monster = Monster::factory()->create();

        $this->assertCount(0, $monster->legendaryActions);
        $this->assertTrue($monster->legendaryActions->isEmpty());
    }

    #[Test]
    public function monster_with_legendary_actions(): void
    {
        $monster = Monster::factory()->create();
        MonsterLegendaryAction::create([
            'monster_id' => $monster->id,
            'name' => 'Wing Attack',
            'description' => 'The dragon beats its wings.',
            'action_cost' => 2,
            'is_lair_action' => false,
        ]);

        $monster = $monster->fresh(['legendaryActions']);

        $this->assertCount(1, $monster->legendaryActions);
        $this->assertEquals('Wing Attack', $monster->legendaryActions->first()->name);
        $this->assertEquals(2, $monster->legendaryActions->first()->action_cost);
    }

    #[Test]
    public function monster_with_lair_actions(): void
    {
        $monster = Monster::factory()->create();
        MonsterLegendaryAction::create([
            'monster_id' => $monster->id,
            'name' => 'Lair Action: Tremors',
            'description' => 'The ground shakes.',
            'action_cost' => 1,
            'is_lair_action' => true,
        ]);

        $monster = $monster->fresh(['legendaryActions']);

        $this->assertCount(1, $monster->legendaryActions);
        $this->assertTrue($monster->legendaryActions->first()->is_lair_action);
    }

    // =========================================================================
    // is_legendary Accessor Edge Cases
    // =========================================================================

    #[Test]
    public function is_legendary_returns_false_for_monster_without_legendary_actions(): void
    {
        $monster = Monster::factory()->create();

        $this->assertFalse($monster->is_legendary);
    }

    #[Test]
    public function is_legendary_returns_true_for_monster_with_legendary_actions(): void
    {
        $monster = Monster::factory()->create();
        MonsterLegendaryAction::create([
            'monster_id' => $monster->id,
            'name' => 'Detect',
            'description' => 'The dragon makes a Wisdom (Perception) check.',
            'action_cost' => 1,
            'is_lair_action' => false,
        ]);

        $monster = $monster->fresh();

        $this->assertTrue($monster->is_legendary);
    }

    #[Test]
    public function is_legendary_returns_false_for_monster_with_only_lair_actions(): void
    {
        $monster = Monster::factory()->create();
        MonsterLegendaryAction::create([
            'monster_id' => $monster->id,
            'name' => 'Lair Action Only',
            'description' => 'This is a lair action, not a legendary action.',
            'action_cost' => 1,
            'is_lair_action' => true,
        ]);

        $monster = $monster->fresh();

        // Lair actions don't make a monster "legendary"
        $this->assertFalse($monster->is_legendary);
    }

    // =========================================================================
    // Proficiency Bonus (CR-based calculation)
    // =========================================================================

    #[Test]
    public function proficiency_bonus_for_cr_0(): void
    {
        $monster = Monster::factory()->create(['challenge_rating' => '0']);

        $this->assertEquals(2, $monster->proficiency_bonus);
    }

    #[Test]
    public function proficiency_bonus_for_fractional_cr(): void
    {
        $monster125 = Monster::factory()->create(['challenge_rating' => '1/8']);
        $monster25 = Monster::factory()->create(['challenge_rating' => '1/4']);
        $monster5 = Monster::factory()->create(['challenge_rating' => '1/2']);

        $this->assertEquals(2, $monster125->proficiency_bonus);
        $this->assertEquals(2, $monster25->proficiency_bonus);
        $this->assertEquals(2, $monster5->proficiency_bonus);
    }

    #[Test]
    public function proficiency_bonus_for_cr_5(): void
    {
        $monster = Monster::factory()->create(['challenge_rating' => '5']);

        $this->assertEquals(3, $monster->proficiency_bonus);
    }

    #[Test]
    public function proficiency_bonus_for_cr_17(): void
    {
        $monster = Monster::factory()->create(['challenge_rating' => '17']);

        $this->assertEquals(6, $monster->proficiency_bonus);
    }

    #[Test]
    public function proficiency_bonus_for_cr_30(): void
    {
        $monster = Monster::factory()->create(['challenge_rating' => '30']);

        $this->assertEquals(9, $monster->proficiency_bonus);
    }

    // =========================================================================
    // Challenge Rating Numeric Conversion
    // =========================================================================

    #[Test]
    public function challenge_rating_numeric_for_fractional_values(): void
    {
        $monster125 = Monster::factory()->create(['challenge_rating' => '1/8']);
        $monster25 = Monster::factory()->create(['challenge_rating' => '1/4']);
        $monster5 = Monster::factory()->create(['challenge_rating' => '1/2']);

        $this->assertEquals(0.125, $monster125->getChallengeRatingNumeric());
        $this->assertEquals(0.25, $monster25->getChallengeRatingNumeric());
        $this->assertEquals(0.5, $monster5->getChallengeRatingNumeric());
    }

    #[Test]
    public function challenge_rating_numeric_for_integer_values(): void
    {
        $monster1 = Monster::factory()->create(['challenge_rating' => '1']);
        $monster10 = Monster::factory()->create(['challenge_rating' => '10']);
        $monster20 = Monster::factory()->create(['challenge_rating' => '20']);

        $this->assertEquals(1.0, $monster1->getChallengeRatingNumeric());
        $this->assertEquals(10.0, $monster10->getChallengeRatingNumeric());
        $this->assertEquals(20.0, $monster20->getChallengeRatingNumeric());
    }

    // =========================================================================
    // Sense Edge Cases
    // =========================================================================

    #[Test]
    public function monster_with_no_senses_returns_empty_collection(): void
    {
        $monster = Monster::factory()->create();

        $this->assertCount(0, $monster->senses);
    }

    #[Test]
    public function monster_with_darkvision(): void
    {
        $monster = Monster::factory()->create();
        $darkvision = Sense::firstOrCreate(
            ['slug' => 'core:darkvision'],
            ['name' => 'Darkvision']
        );

        EntitySense::create([
            'reference_type' => Monster::class,
            'reference_id' => $monster->id,
            'sense_id' => $darkvision->id,
            'range_feet' => 120,
        ]);

        $monster = $monster->fresh(['senses.sense']);

        $this->assertCount(1, $monster->senses);
        $this->assertEquals(120, $monster->senses->first()->range_feet);
    }

    #[Test]
    public function monster_with_multiple_senses(): void
    {
        $monster = Monster::factory()->create();
        $darkvision = Sense::firstOrCreate(['slug' => 'core:darkvision'], ['name' => 'Darkvision']);
        $blindsight = Sense::firstOrCreate(['slug' => 'core:blindsight'], ['name' => 'Blindsight']);
        $truesight = Sense::firstOrCreate(['slug' => 'core:truesight'], ['name' => 'Truesight']);

        EntitySense::create(['reference_type' => Monster::class, 'reference_id' => $monster->id, 'sense_id' => $darkvision->id, 'range_feet' => 120]);
        EntitySense::create(['reference_type' => Monster::class, 'reference_id' => $monster->id, 'sense_id' => $blindsight->id, 'range_feet' => 60]);
        EntitySense::create(['reference_type' => Monster::class, 'reference_id' => $monster->id, 'sense_id' => $truesight->id, 'range_feet' => 30]);

        $monster = $monster->fresh(['senses']);

        $this->assertCount(3, $monster->senses);
    }

    // =========================================================================
    // Speed Edge Cases
    // =========================================================================

    #[Test]
    public function monster_with_multiple_movement_types(): void
    {
        $monster = Monster::factory()->create([
            'speed_walk' => 40,
            'speed_fly' => 80,
            'speed_swim' => 40,
            'speed_burrow' => 20,
            'speed_climb' => 40,
            'can_hover' => true,
        ]);

        $this->assertEquals(40, $monster->speed_walk);
        $this->assertEquals(80, $monster->speed_fly);
        $this->assertEquals(40, $monster->speed_swim);
        $this->assertEquals(20, $monster->speed_burrow);
        $this->assertEquals(40, $monster->speed_climb);
        $this->assertTrue($monster->can_hover);
    }

    #[Test]
    public function monster_with_no_movement(): void
    {
        // Some creatures (like animated objects) may have 0 walk speed
        $monster = Monster::factory()->create([
            'speed_walk' => 0,
            'speed_fly' => null,
            'speed_swim' => null,
        ]);

        $this->assertEquals(0, $monster->speed_walk);
        $this->assertNull($monster->speed_fly);
    }

    // =========================================================================
    // NPC Flag Edge Cases
    // =========================================================================

    #[Test]
    public function monster_can_be_marked_as_npc(): void
    {
        $monster = Monster::factory()->create(['is_npc' => true]);

        $this->assertTrue($monster->is_npc);
    }

    #[Test]
    public function monster_can_be_non_npc(): void
    {
        $monster = Monster::factory()->create(['is_npc' => false]);

        $this->assertFalse($monster->is_npc);
    }
}
