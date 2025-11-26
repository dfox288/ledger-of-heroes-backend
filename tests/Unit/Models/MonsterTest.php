<?php

namespace Tests\Unit\Models;

use App\Models\Modifier;
use App\Models\Monster;
use App\Models\MonsterAction;
use App\Models\MonsterLegendaryAction;
use App\Models\MonsterTrait;
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
    public function it_has_many_traits(): void
    {
        $monster = Monster::factory()->create();
        MonsterTrait::factory()->count(3)->create(['monster_id' => $monster->id]);

        $this->assertCount(3, $monster->traits);
        $this->assertInstanceOf(MonsterTrait::class, $monster->traits->first());
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
}
