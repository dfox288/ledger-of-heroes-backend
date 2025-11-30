<?php

namespace Tests\Unit\Models;

use App\Models\Race;
use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class RaceTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function is_subrace_returns_true_when_has_parent_race(): void
    {
        $size = Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);
        $parentRace = Race::factory()->create(['size_id' => $size->id]);
        $subrace = Race::factory()->create([
            'size_id' => $size->id,
            'parent_race_id' => $parentRace->id,
        ]);

        $this->assertTrue($subrace->is_subrace);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function is_subrace_returns_false_when_no_parent_race(): void
    {
        $size = Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);
        $baseRace = Race::factory()->create([
            'size_id' => $size->id,
            'parent_race_id' => null,
        ]);

        $this->assertFalse($baseRace->is_subrace);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_speed_columns_fillable(): void
    {
        $race = new Race;

        $this->assertContains('fly_speed', $race->getFillable());
        $this->assertContains('swim_speed', $race->getFillable());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_casts_speed_columns_to_integer(): void
    {
        $race = new Race;
        $casts = $race->getCasts();

        $this->assertEquals('integer', $casts['fly_speed']);
        $this->assertEquals('integer', $casts['swim_speed']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function factory_can_create_race_with_fly_speed(): void
    {
        $race = Race::factory()->withFlySpeed(50)->create();

        $this->assertEquals(50, $race->fly_speed);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function factory_can_create_race_with_swim_speed(): void
    {
        $race = Race::factory()->withSwimSpeed(30)->create();

        $this->assertEquals(30, $race->swim_speed);
    }
}
