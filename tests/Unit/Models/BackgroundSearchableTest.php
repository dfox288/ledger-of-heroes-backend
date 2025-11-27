<?php

namespace Tests\Unit\Models;

use App\Models\Background;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class BackgroundSearchableTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_searchable_array_with_denormalized_data(): void
    {
        // Use fixture data - Acolyte background is seeded by TestDatabaseSeeder
        $background = Background::where('slug', 'acolyte')->firstOrFail();

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
