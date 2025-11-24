<?php

namespace Tests\Unit\Models;

use App\Models\CharacterClass;
use App\Models\EntitySource;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterClassSearchableTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_searchable_array_with_denormalized_data(): void
    {
        $source = Source::firstOrCreate(['code' => 'PHB'], ['name' => 'Player\'s Handbook']);

        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        EntitySource::factory()->create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'source_id' => $source->id,
            'pages' => '70',
        ]);

        $class->refresh();

        $searchable = $class->toSearchableArray();

        $this->assertArrayHasKey('id', $searchable);
        $this->assertEquals('Fighter', $searchable['name']);
        $this->assertEquals(10, $searchable['hit_die']);
        $this->assertEquals(['Player\'s Handbook'], $searchable['sources']);
        $this->assertEquals(['PHB'], $searchable['source_codes']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_defines_searchable_relationships(): void
    {
        $class = new CharacterClass;

        $this->assertIsArray($class->searchableWith());
        $this->assertContains('sources.source', $class->searchableWith());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_correct_search_index_name(): void
    {
        $class = new CharacterClass;
        $this->assertEquals('test_classes', $class->searchableAs());
    }
}
