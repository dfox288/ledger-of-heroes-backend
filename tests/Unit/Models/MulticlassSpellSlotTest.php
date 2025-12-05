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

    #[Test]
    public function it_returns_null_for_negative_level(): void
    {
        $this->seed(\Database\Seeders\MulticlassSpellSlotSeeder::class);

        $slots = MulticlassSpellSlot::forCasterLevel(-5);

        $this->assertNull($slots);
    }

    #[Test]
    public function to_slots_array_returns_correct_structure(): void
    {
        $this->seed(\Database\Seeders\MulticlassSpellSlotSeeder::class);

        $slots = MulticlassSpellSlot::forCasterLevel(3);
        $array = $slots->toSlotsArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('1st', $array);
        $this->assertArrayHasKey('2nd', $array);
        $this->assertArrayHasKey('3rd', $array);
        $this->assertArrayHasKey('4th', $array);
        $this->assertArrayHasKey('5th', $array);
        $this->assertArrayHasKey('6th', $array);
        $this->assertArrayHasKey('7th', $array);
        $this->assertArrayHasKey('8th', $array);
        $this->assertArrayHasKey('9th', $array);
    }

    #[Test]
    public function to_slots_array_has_correct_values_for_level_1(): void
    {
        $this->seed(\Database\Seeders\MulticlassSpellSlotSeeder::class);

        $slots = MulticlassSpellSlot::forCasterLevel(1);
        $array = $slots->toSlotsArray();

        $this->assertEquals(2, $array['1st']);
        $this->assertEquals(0, $array['2nd']);
        $this->assertEquals(0, $array['3rd']);
        $this->assertEquals(0, $array['9th']);
    }

    #[Test]
    public function to_slots_array_has_correct_values_for_level_20(): void
    {
        $this->seed(\Database\Seeders\MulticlassSpellSlotSeeder::class);

        $slots = MulticlassSpellSlot::forCasterLevel(20);
        $array = $slots->toSlotsArray();

        $this->assertEquals(4, $array['1st']);
        $this->assertEquals(3, $array['2nd']);
        $this->assertEquals(3, $array['3rd']);
        $this->assertEquals(3, $array['4th']);
        $this->assertEquals(3, $array['5th']);
        $this->assertEquals(2, $array['6th']);
        $this->assertEquals(2, $array['7th']);
        $this->assertEquals(1, $array['8th']);
        $this->assertEquals(1, $array['9th']);
    }

    #[Test]
    public function it_uses_caster_level_as_primary_key(): void
    {
        $slot = MulticlassSpellSlot::make();

        $this->assertEquals('caster_level', $slot->getKeyName());
    }

    #[Test]
    public function it_does_not_use_incrementing_primary_key(): void
    {
        $slot = MulticlassSpellSlot::make();

        $this->assertFalse($slot->getIncrementing());
    }

    #[Test]
    public function it_does_not_use_timestamps(): void
    {
        $this->assertFalse(MulticlassSpellSlot::make()->usesTimestamps());
    }
}
