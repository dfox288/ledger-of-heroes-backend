<?php

namespace Tests\Unit\Models;

use App\Models\Background;
use App\Models\EntitySource;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class BackgroundSearchableTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_searchable_array_with_denormalized_data(): void
    {
        $source = Source::firstOrCreate(['code' => 'PHB'], ['name' => 'Player\'s Handbook']);

        $background = Background::factory()->create([
            'name' => 'Acolyte',
        ]);

        EntitySource::factory()->create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'source_id' => $source->id,
            'pages' => '127',
        ]);

        $background->refresh();

        $searchable = $background->toSearchableArray();

        $this->assertArrayHasKey('id', $searchable);
        $this->assertEquals('Acolyte', $searchable['name']);
        $this->assertEquals(['Player\'s Handbook'], $searchable['sources']);
        $this->assertEquals(['PHB'], $searchable['source_codes']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_defines_searchable_relationships(): void
    {
        $background = new Background;

        $this->assertIsArray($background->searchableWith());
        $this->assertContains('sources.source', $background->searchableWith());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_correct_search_index_name(): void
    {
        $background = new Background;
        $this->assertEquals('test_backgrounds', $background->searchableAs());
    }
}
