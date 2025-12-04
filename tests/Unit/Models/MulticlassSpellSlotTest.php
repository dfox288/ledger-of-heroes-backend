<?php

namespace Tests\Unit\Models;

use App\Models\MulticlassSpellSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MulticlassSpellSlotTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_retrieves_spell_slots_for_caster_level(): void
    {
        $this->seed(\Database\Seeders\MulticlassSpellSlotSeeder::class);

        $slots = MulticlassSpellSlot::forCasterLevel(5);

        $this->assertNotNull($slots);
        $this->assertEquals(4, $slots->slots_1st);
        $this->assertEquals(3, $slots->slots_2nd);
        $this->assertEquals(2, $slots->slots_3rd);
        $this->assertEquals(0, $slots->slots_4th);
    }

    #[Test]
    public function it_caps_at_level_20(): void
    {
        $this->seed(\Database\Seeders\MulticlassSpellSlotSeeder::class);

        $slots = MulticlassSpellSlot::forCasterLevel(25);

        $this->assertNotNull($slots);
        $this->assertEquals(20, $slots->caster_level);
    }

    #[Test]
    public function it_returns_null_for_level_zero(): void
    {
        $this->seed(\Database\Seeders\MulticlassSpellSlotSeeder::class);

        $slots = MulticlassSpellSlot::forCasterLevel(0);

        $this->assertNull($slots);
    }
}
