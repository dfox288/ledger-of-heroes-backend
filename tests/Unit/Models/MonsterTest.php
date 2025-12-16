<?php

namespace Tests\Unit\Models;

use App\Models\Modifier;
use App\Models\Monster;
use App\Models\MonsterAction;
use App\Models\MonsterLegendaryAction;
use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class MonsterTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_belongs_to_size(): void
    {
        $monster = Monster::factory()->create();

        $this->assertInstanceOf(Size::class, $monster->size);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_polymorphic_entity_traits(): void
    {
        $monster = Monster::factory()->create();
        \App\Models\CharacterTrait::create([
            'reference_type' => Monster::class,
            'reference_id' => $monster->id,
            'name' => 'Magic Resistance',
            'description' => 'The monster has advantage on saving throws against spells.',
            'sort_order' => 1,
        ]);
        \App\Models\CharacterTrait::create([
            'reference_type' => Monster::class,
            'reference_id' => $monster->id,
            'name' => 'Amphibious',
            'description' => 'The monster can breathe air and water.',
            'sort_order' => 2,
        ]);

        $this->assertCount(2, $monster->entityTraits);
        $this->assertInstanceOf(\App\Models\CharacterTrait::class, $monster->entityTraits->first());
        // Verify ordering by sort_order
        $this->assertEquals('Magic Resistance', $monster->entityTraits->first()->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_many_actions(): void
    {
        $monster = Monster::factory()->create();
        MonsterAction::factory()->count(2)->create(['monster_id' => $monster->id]);

        $this->assertCount(2, $monster->actions);
        $this->assertInstanceOf(MonsterAction::class, $monster->actions->first());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_many_legendary_actions(): void
    {
        $monster = Monster::factory()->create();
        MonsterLegendaryAction::factory()->count(3)->create(['monster_id' => $monster->id]);

        $this->assertCount(3, $monster->legendaryActions);
        $this->assertInstanceOf(MonsterLegendaryAction::class, $monster->legendaryActions->first());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_use_timestamps(): void
    {
        $this->assertFalse(Monster::make()->usesTimestamps());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_many_modifiers(): void
    {
        $monster = Monster::factory()->create();
        Modifier::factory()->count(2)->create([
            'reference_type' => Monster::class,
            'reference_id' => $monster->id,
        ]);

        $this->assertCount(2, $monster->modifiers);
        $this->assertInstanceOf(Modifier::class, $monster->modifiers->first());
    }

    // Computed Accessor Tests

    #[\PHPUnit\Framework\Attributes\Test]
    public function is_legendary_returns_true_when_has_legendary_actions(): void
    {
        $monster = Monster::factory()->create();
        MonsterLegendaryAction::factory()->create([
            'monster_id' => $monster->id,
            'is_lair_action' => false,
        ]);

        $this->assertTrue($monster->is_legendary);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function is_legendary_returns_false_when_only_lair_actions(): void
    {
        $monster = Monster::factory()->create();
        MonsterLegendaryAction::factory()->create([
            'monster_id' => $monster->id,
            'is_lair_action' => true,
        ]);

        $this->assertFalse($monster->is_legendary);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function is_legendary_returns_false_when_no_legendary_actions(): void
    {
        $monster = Monster::factory()->create();

        $this->assertFalse($monster->is_legendary);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('proficiencyBonusProvider')]
    public function proficiency_bonus_is_computed_from_challenge_rating(string $cr, int $expectedBonus): void
    {
        $monster = Monster::factory()->create(['challenge_rating' => $cr]);

        $this->assertSame($expectedBonus, $monster->proficiency_bonus);
    }

    public static function proficiencyBonusProvider(): array
    {
        return [
            'CR 0' => ['0', 2],
            'CR 1/8' => ['1/8', 2],
            'CR 1/4' => ['1/4', 2],
            'CR 1/2' => ['1/2', 2],
            'CR 1' => ['1', 2],
            'CR 4' => ['4', 2],
            'CR 5' => ['5', 3],
            'CR 8' => ['8', 3],
            'CR 9' => ['9', 4],
            'CR 12' => ['12', 4],
            'CR 13' => ['13', 5],
            'CR 16' => ['16', 5],
            'CR 17' => ['17', 6],
            'CR 20' => ['20', 6],
            'CR 21' => ['21', 7],
            'CR 24' => ['24', 7],
            'CR 25' => ['25', 8],
            'CR 28' => ['28', 8],
            'CR 29' => ['29', 9],
            'CR 30' => ['30', 9],
        ];
    }

    // Challenge Rating Tests

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('challengeRatingProvider')]
    public function get_challenge_rating_numeric_converts_correctly(string $cr, float $expected): void
    {
        $monster = Monster::factory()->create(['challenge_rating' => $cr]);

        $this->assertEquals($expected, $monster->getChallengeRatingNumeric());
    }

    public static function challengeRatingProvider(): array
    {
        return [
            'CR 0' => ['0', 0.0],
            'CR 1/8' => ['1/8', 0.125],
            'CR 1/4' => ['1/4', 0.25],
            'CR 1/2' => ['1/2', 0.5],
            'CR 1' => ['1', 1.0],
            'CR 5' => ['5', 5.0],
            'CR 10' => ['10', 10.0],
            'CR 20' => ['20', 20.0],
            'CR 30' => ['30', 30.0],
        ];
    }

    // Saving Throws Accessor Tests

    #[\PHPUnit\Framework\Attributes\Test]
    public function saving_throws_returns_all_six_abilities(): void
    {
        $monster = Monster::factory()->create([
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'intelligence' => 10,
            'wisdom' => 10,
            'charisma' => 10,
        ]);

        $savingThrows = $monster->saving_throws;

        $this->assertArrayHasKey('STR', $savingThrows);
        $this->assertArrayHasKey('DEX', $savingThrows);
        $this->assertArrayHasKey('CON', $savingThrows);
        $this->assertArrayHasKey('INT', $savingThrows);
        $this->assertArrayHasKey('WIS', $savingThrows);
        $this->assertArrayHasKey('CHA', $savingThrows);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function saving_throws_calculates_from_ability_scores_when_not_proficient(): void
    {
        // Formula: floor((ability_score - 10) / 2)
        $monster = Monster::factory()->create([
            'strength' => 10,     // (10-10)/2 = 0
            'dexterity' => 14,    // (14-10)/2 = +2
            'constitution' => 18, // (18-10)/2 = +4
            'intelligence' => 8,  // (8-10)/2 = -1
            'wisdom' => 15,       // (15-10)/2 = +2 (floor)
            'charisma' => 6,      // (6-10)/2 = -2
        ]);

        $savingThrows = $monster->saving_throws;

        $this->assertSame(0, $savingThrows['STR']);
        $this->assertSame(2, $savingThrows['DEX']);
        $this->assertSame(4, $savingThrows['CON']);
        $this->assertSame(-1, $savingThrows['INT']);
        $this->assertSame(2, $savingThrows['WIS']);
        $this->assertSame(-2, $savingThrows['CHA']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function saving_throws_uses_stored_modifier_for_proficient_saves(): void
    {
        $monster = Monster::factory()->create([
            'strength' => 10,     // Base: 0
            'dexterity' => 10,    // Base: 0, but proficient
            'constitution' => 10, // Base: 0
            'intelligence' => 10, // Base: 0
            'wisdom' => 10,       // Base: 0, but proficient
            'charisma' => 10,     // Base: 0
        ]);

        // Add proficient saving throws as modifiers
        Modifier::factory()->create([
            'reference_type' => Monster::class,
            'reference_id' => $monster->id,
            'modifier_category' => 'saving_throw_dex',
            'value' => '7',
        ]);
        Modifier::factory()->create([
            'reference_type' => Monster::class,
            'reference_id' => $monster->id,
            'modifier_category' => 'saving_throw_wis',
            'value' => '9',
        ]);

        // Refresh to reload modifiers relationship
        $monster->refresh();
        $savingThrows = $monster->saving_throws;

        // Proficient saves use stored value
        $this->assertSame(7, $savingThrows['DEX']);
        $this->assertSame(9, $savingThrows['WIS']);

        // Non-proficient saves use ability score calculation
        $this->assertSame(0, $savingThrows['STR']);
        $this->assertSame(0, $savingThrows['CON']);
        $this->assertSame(0, $savingThrows['INT']);
        $this->assertSame(0, $savingThrows['CHA']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function saving_throws_handles_ancient_red_dragon_stats(): void
    {
        // Real stats from Ancient Red Dragon: CR 24, +7 proficiency
        // Proficient in DEX, CON, WIS, CHA
        $monster = Monster::factory()->create([
            'strength' => 30,       // (30-10)/2 = +10 (not proficient)
            'dexterity' => 10,      // +7 (proficient)
            'constitution' => 29,   // +16 (proficient)
            'intelligence' => 18,   // (18-10)/2 = +4 (not proficient)
            'wisdom' => 15,         // +9 (proficient)
            'charisma' => 23,       // +13 (proficient)
            'challenge_rating' => '24',
        ]);

        // Add proficient saves
        Modifier::factory()->create([
            'reference_type' => Monster::class,
            'reference_id' => $monster->id,
            'modifier_category' => 'saving_throw_dex',
            'value' => '7',
        ]);
        Modifier::factory()->create([
            'reference_type' => Monster::class,
            'reference_id' => $monster->id,
            'modifier_category' => 'saving_throw_con',
            'value' => '16',
        ]);
        Modifier::factory()->create([
            'reference_type' => Monster::class,
            'reference_id' => $monster->id,
            'modifier_category' => 'saving_throw_wis',
            'value' => '9',
        ]);
        Modifier::factory()->create([
            'reference_type' => Monster::class,
            'reference_id' => $monster->id,
            'modifier_category' => 'saving_throw_cha',
            'value' => '13',
        ]);

        $monster->refresh();
        $savingThrows = $monster->saving_throws;

        // Non-proficient (calculated from ability score)
        $this->assertSame(10, $savingThrows['STR']); // (30-10)/2 = 10
        $this->assertSame(4, $savingThrows['INT']);  // (18-10)/2 = 4

        // Proficient (from stored modifiers)
        $this->assertSame(7, $savingThrows['DEX']);
        $this->assertSame(16, $savingThrows['CON']);
        $this->assertSame(9, $savingThrows['WIS']);
        $this->assertSame(13, $savingThrows['CHA']);
    }

    // Legendary Metadata Tests

    #[\PHPUnit\Framework\Attributes\Test]
    public function legendary_actions_per_round_defaults_to_null(): void
    {
        $monster = Monster::factory()->create();

        $this->assertNull($monster->legendary_actions_per_round);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function legendary_resistance_uses_defaults_to_null(): void
    {
        $monster = Monster::factory()->create();

        $this->assertNull($monster->legendary_resistance_uses);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function legendary_factory_state_sets_both_values(): void
    {
        $monster = Monster::factory()->legendary()->create();

        $this->assertSame(3, $monster->legendary_actions_per_round);
        $this->assertSame(3, $monster->legendary_resistance_uses);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function legendary_factory_state_accepts_custom_values(): void
    {
        $monster = Monster::factory()->legendary(5, 2)->create();

        $this->assertSame(5, $monster->legendary_actions_per_round);
        $this->assertSame(2, $monster->legendary_resistance_uses);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_set_legendary_actions_without_resistance(): void
    {
        $monster = Monster::factory()->withLegendaryActions(4)->create();

        $this->assertSame(4, $monster->legendary_actions_per_round);
        $this->assertNull($monster->legendary_resistance_uses);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_set_legendary_resistance_without_actions(): void
    {
        $monster = Monster::factory()->withLegendaryResistance(5)->create();

        $this->assertNull($monster->legendary_actions_per_round);
        $this->assertSame(5, $monster->legendary_resistance_uses);
    }
}
