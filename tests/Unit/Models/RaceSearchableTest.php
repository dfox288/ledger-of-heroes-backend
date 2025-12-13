<?php

namespace Tests\Unit\Models;

use App\Models\EntitySource;
use App\Models\Race;
use App\Models\Size;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class RaceSearchableTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_searchable_array_with_denormalized_data(): void
    {
        $size = Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);
        $source = Source::firstOrCreate(['code' => 'PHB'], ['name' => 'Player\'s Handbook']);

        $race = Race::factory()->create([
            'name' => 'Hill Dwarf',
            'size_id' => $size->id,
            'speed' => 25,
        ]);

        EntitySource::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'source_id' => $source->id,
            'pages' => '20',
        ]);

        $race->refresh();

        $searchable = $race->toSearchableArray();

        $this->assertArrayHasKey('id', $searchable);
        $this->assertEquals('Hill Dwarf', $searchable['name']);
        $this->assertEquals('Medium', $searchable['size_name']);
        $this->assertEquals(25, $searchable['speed']);
        $this->assertEquals(['Player\'s Handbook'], $searchable['sources']);
        $this->assertEquals(['PHB'], $searchable['source_codes']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_defines_searchable_relationships(): void
    {
        $race = new Race;

        $this->assertIsArray($race->searchableWith());
        $this->assertContains('size', $race->searchableWith());
        $this->assertContains('sources.source', $race->searchableWith());
        $this->assertContains('parent', $race->searchableWith());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_correct_search_index_name(): void
    {
        $race = new Race;
        $this->assertEquals('test_races', $race->searchableAs());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_indexes_darkvision_fields_in_searchable_array(): void
    {
        $size = Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);
        $sense = \App\Models\Sense::firstOrCreate(['slug' => 'core:darkvision'], ['name' => 'Darkvision']);

        $race = Race::factory()->create(['size_id' => $size->id]);

        \App\Models\EntitySense::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'sense_id' => $sense->id,
            'range_feet' => 60,
            'is_limited' => false,
        ]);

        $race->refresh();
        $searchable = $race->toSearchableArray();

        $this->assertTrue($searchable['has_darkvision']);
        $this->assertEquals(60, $searchable['darkvision_range']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_indexes_speed_fields_in_searchable_array(): void
    {
        $size = Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);

        $race = Race::factory()->create([
            'size_id' => $size->id,
            'fly_speed' => 50,
            'swim_speed' => 30,
            'climb_speed' => 20,
        ]);

        $searchable = $race->toSearchableArray();

        $this->assertEquals(50, $searchable['fly_speed']);
        $this->assertEquals(30, $searchable['swim_speed']);
        $this->assertEquals(20, $searchable['climb_speed']);
        $this->assertTrue($searchable['has_fly_speed']);
        $this->assertTrue($searchable['has_swim_speed']);
        $this->assertTrue($searchable['has_climb_speed']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_indexes_false_for_missing_speeds(): void
    {
        $size = Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);

        $race = Race::factory()->create([
            'size_id' => $size->id,
            'fly_speed' => null,
            'swim_speed' => null,
            'climb_speed' => null,
        ]);

        $searchable = $race->toSearchableArray();

        $this->assertNull($searchable['fly_speed']);
        $this->assertNull($searchable['swim_speed']);
        $this->assertNull($searchable['climb_speed']);
        $this->assertFalse($searchable['has_fly_speed']);
        $this->assertFalse($searchable['has_swim_speed']);
        $this->assertFalse($searchable['has_climb_speed']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_speed_and_sense_fields_in_filterable_attributes(): void
    {
        $race = new Race;
        $options = $race->searchableOptions();

        $filterable = $options['filterableAttributes'];

        $this->assertContains('fly_speed', $filterable);
        $this->assertContains('swim_speed', $filterable);
        $this->assertContains('climb_speed', $filterable);
        $this->assertContains('has_fly_speed', $filterable);
        $this->assertContains('has_swim_speed', $filterable);
        $this->assertContains('has_climb_speed', $filterable);
        $this->assertContains('has_darkvision', $filterable);
        $this->assertContains('darkvision_range', $filterable);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_climb_speed_in_sortable_attributes(): void
    {
        $race = new Race;
        $options = $race->searchableOptions();

        $sortable = $options['sortableAttributes'];

        $this->assertContains('climb_speed', $sortable);
    }
}
