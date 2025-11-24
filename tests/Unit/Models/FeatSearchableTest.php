<?php

namespace Tests\Unit\Models;

use App\Models\EntitySource;
use App\Models\Feat;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatSearchableTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_searchable_array_with_denormalized_data(): void
    {
        $source = Source::firstOrCreate(['code' => 'PHB'], ['name' => 'Player\'s Handbook']);

        $feat = Feat::factory()->create([
            'name' => 'Alert',
            'prerequisites_text' => 'Dexterity 13 or higher',
        ]);

        EntitySource::factory()->create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'source_id' => $source->id,
            'pages' => '165',
        ]);

        $feat->refresh();

        $searchable = $feat->toSearchableArray();

        $this->assertArrayHasKey('id', $searchable);
        $this->assertEquals('Alert', $searchable['name']);
        $this->assertEquals('Dexterity 13 or higher', $searchable['prerequisites_text']);
        $this->assertEquals(['Player\'s Handbook'], $searchable['sources']);
        $this->assertEquals(['PHB'], $searchable['source_codes']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_defines_searchable_relationships(): void
    {
        $feat = new Feat;

        $this->assertIsArray($feat->searchableWith());
        $this->assertContains('sources.source', $feat->searchableWith());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_correct_search_index_name(): void
    {
        $feat = new Feat;
        $this->assertEquals('test_feats', $feat->searchableAs());
    }
}
