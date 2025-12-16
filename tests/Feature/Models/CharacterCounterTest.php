<?php

namespace Tests\Feature\Models;

use App\Models\Character;
use App\Models\CharacterCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterCounterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_belongs_to_a_character(): void
    {
        $character = Character::factory()->create();
        $counter = CharacterCounter::factory()->create([
            'character_id' => $character->id,
        ]);

        $this->assertInstanceOf(Character::class, $counter->character);
        $this->assertEquals($character->id, $counter->character->id);
    }

    #[Test]
    public function it_can_use_a_counter_when_uses_remain(): void
    {
        $counter = CharacterCounter::factory()->create([
            'current_uses' => 3,
            'max_uses' => 5,
        ]);

        $result = $counter->use();

        $this->assertTrue($result);
        $this->assertEquals(2, $counter->fresh()->current_uses);
    }

    #[Test]
    public function it_cannot_use_a_counter_when_no_uses_remain(): void
    {
        $counter = CharacterCounter::factory()->create([
            'current_uses' => 0,
            'max_uses' => 5,
        ]);

        $result = $counter->use();

        $this->assertFalse($result);
        $this->assertEquals(0, $counter->fresh()->current_uses);
    }

    #[Test]
    public function it_can_use_unlimited_counter(): void
    {
        $counter = CharacterCounter::factory()->create([
            'current_uses' => null,
            'max_uses' => -1,
        ]);

        $result = $counter->use();

        $this->assertTrue($result);
        $this->assertNull($counter->fresh()->current_uses);
    }

    #[Test]
    public function it_resets_to_full_uses(): void
    {
        $counter = CharacterCounter::factory()->create([
            'current_uses' => 1,
            'max_uses' => 5,
        ]);

        $counter->reset();

        $this->assertNull($counter->fresh()->current_uses);
    }

    #[Test]
    public function it_detects_unlimited_counters(): void
    {
        $unlimited = CharacterCounter::factory()->create([
            'max_uses' => -1,
        ]);
        $limited = CharacterCounter::factory()->create([
            'max_uses' => 3,
        ]);

        $this->assertTrue($unlimited->isUnlimited());
        $this->assertFalse($limited->isUnlimited());
    }

    #[Test]
    public function it_calculates_remaining_uses_correctly(): void
    {
        // Full uses (null current = full)
        $full = CharacterCounter::factory()->create([
            'current_uses' => null,
            'max_uses' => 5,
        ]);
        $this->assertEquals(5, $full->remaining);

        // Partial uses
        $partial = CharacterCounter::factory()->create([
            'current_uses' => 2,
            'max_uses' => 5,
        ]);
        $this->assertEquals(2, $partial->remaining);

        // Unlimited
        $unlimited = CharacterCounter::factory()->create([
            'max_uses' => -1,
        ]);
        $this->assertNull($unlimited->remaining);
    }

    #[Test]
    public function character_has_many_counters(): void
    {
        $character = Character::factory()->create();
        CharacterCounter::factory()->count(3)->create(['character_id' => $character->id]);

        $this->assertCount(3, $character->counters);
    }

    #[Test]
    public function deleting_character_cascades_to_counters(): void
    {
        $character = Character::factory()->create();
        CharacterCounter::factory()->count(2)->create(['character_id' => $character->id]);

        $this->assertDatabaseCount('character_counters', 2);

        $character->delete();

        $this->assertDatabaseCount('character_counters', 0);
    }
}
