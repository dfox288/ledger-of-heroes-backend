<?php

namespace Tests\Unit\Models;

use App\Models\Monster;
use App\Models\MonsterAction;
use App\Models\MonsterLegendaryAction;
use App\Models\MonsterSpellcasting;
use App\Models\MonsterTrait;
use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
    public function it_has_one_spellcasting(): void
    {
        $monster = Monster::factory()->create();
        MonsterSpellcasting::factory()->create(['monster_id' => $monster->id]);

        $this->assertInstanceOf(MonsterSpellcasting::class, $monster->spellcasting);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_use_timestamps(): void
    {
        $this->assertFalse(Monster::make()->usesTimestamps());
    }
}
