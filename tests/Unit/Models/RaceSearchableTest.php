<?php

namespace Tests\Unit\Models;

use App\Models\EntitySource;
use App\Models\Race;
use App\Models\Size;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
        $this->assertEquals('races', $race->searchableAs());
    }
}
