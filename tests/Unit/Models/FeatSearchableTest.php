<?php

namespace Tests\Unit\Models;

use App\Models\EntitySource;
use App\Models\Feat;
use App\Models\Modifier;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
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

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_is_half_feat_in_searchable_array(): void
    {
        $feat = Feat::factory()->create(['name' => 'Actor']);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'ability_score',
            'value' => '1',
        ]);

        $feat->refresh();

        $searchable = $feat->toSearchableArray();

        $this->assertArrayHasKey('is_half_feat', $searchable);
        $this->assertTrue($searchable['is_half_feat']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_parent_feat_slug_in_searchable_array(): void
    {
        $feat = Feat::factory()->create([
            'name' => 'Resilient (Constitution)',
            'slug' => 'resilient-constitution',
        ]);

        $searchable = $feat->toSearchableArray();

        $this->assertArrayHasKey('parent_feat_slug', $searchable);
        $this->assertEquals('resilient', $searchable['parent_feat_slug']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_new_fields_in_filterable_attributes(): void
    {
        $feat = new Feat;

        $options = $feat->searchableOptions();

        $this->assertContains('is_half_feat', $options['filterableAttributes']);
        $this->assertContains('parent_feat_slug', $options['filterableAttributes']);
    }
}
