<?php

namespace Tests\Unit\Models;

use App\Models\CharacterClass;
use App\Models\EntitySource;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
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

    // Computed Accessor Tests - Hit Die Inheritance

    #[\PHPUnit\Framework\Attributes\Test]
    public function effective_hit_die_returns_own_hit_die_for_base_class(): void
    {
        $baseClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $this->assertEquals(10, $baseClass->effective_hit_die);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function effective_hit_die_inherits_from_parent_when_zero(): void
    {
        $baseClass = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'hit_die' => 8,
            'parent_class_id' => null,
        ]);

        $subclass = CharacterClass::factory()->create([
            'name' => 'Death Domain',
            'hit_die' => 0,
            'parent_class_id' => $baseClass->id,
        ]);

        $this->assertEquals(8, $subclass->effective_hit_die);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function hit_points_computed_returns_data_for_subclass_with_inherited_hit_die(): void
    {
        $baseClass = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'hit_die' => 8,
            'parent_class_id' => null,
        ]);

        $subclass = CharacterClass::factory()->create([
            'name' => 'Death Domain',
            'hit_die' => 0,
            'parent_class_id' => $baseClass->id,
        ]);

        $hitPoints = $subclass->hit_points;

        $this->assertNotNull($hitPoints);
        $this->assertEquals('d8', $hitPoints['hit_die']);
        $this->assertEquals(8, $hitPoints['hit_die_numeric']);
        $this->assertEquals(8, $hitPoints['first_level']['value']);
    }
}
