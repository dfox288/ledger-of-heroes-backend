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
}
