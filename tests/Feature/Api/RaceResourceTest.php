<?php

namespace Tests\Feature\Api;

use App\Http\Resources\RaceResource;
use App\Models\Race;
use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class RaceResourceTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_speed_fields_in_resource(): void
    {
        $size = Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);

        $race = Race::factory()->create([
            'size_id' => $size->id,
            'fly_speed' => 50,
            'swim_speed' => 30,
            'climb_speed' => 20,
        ]);

        $resource = (new RaceResource($race))->toArray(request());

        $this->assertArrayHasKey('fly_speed', $resource);
        $this->assertArrayHasKey('swim_speed', $resource);
        $this->assertArrayHasKey('climb_speed', $resource);
        $this->assertEquals(50, $resource['fly_speed']);
        $this->assertEquals(30, $resource['swim_speed']);
        $this->assertEquals(20, $resource['climb_speed']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_for_missing_speeds(): void
    {
        $size = Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);

        $race = Race::factory()->create([
            'size_id' => $size->id,
            'fly_speed' => null,
            'swim_speed' => null,
            'climb_speed' => null,
        ]);

        $resource = (new RaceResource($race))->toArray(request());

        $this->assertArrayHasKey('fly_speed', $resource);
        $this->assertArrayHasKey('swim_speed', $resource);
        $this->assertArrayHasKey('climb_speed', $resource);
        $this->assertNull($resource['fly_speed']);
        $this->assertNull($resource['swim_speed']);
        $this->assertNull($resource['climb_speed']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_subrace_required_in_resource(): void
    {
        $size = Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);

        $race = Race::factory()->create([
            'size_id' => $size->id,
            'subrace_required' => true,
        ]);

        $resource = (new RaceResource($race))->toArray(request());

        $this->assertArrayHasKey('subrace_required', $resource);
        $this->assertTrue($resource['subrace_required']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_false_for_subrace_required_when_set(): void
    {
        $size = Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);

        $race = Race::factory()->create([
            'size_id' => $size->id,
            'subrace_required' => false,
        ]);

        $resource = (new RaceResource($race))->toArray(request());

        $this->assertArrayHasKey('subrace_required', $resource);
        $this->assertFalse($resource['subrace_required']);
    }
}
