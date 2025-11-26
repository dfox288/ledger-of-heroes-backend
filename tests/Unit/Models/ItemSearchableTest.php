<?php

namespace Tests\Unit\Models;

use App\Models\EntitySource;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class ItemSearchableTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_searchable_array_with_denormalized_data(): void
    {
        $type = ItemType::firstOrCreate(
            ['code' => 'M'],
            ['name' => 'Melee Weapon']
        );
        $source = Source::firstOrCreate(
            ['code' => 'DMG'],
            [
                'name' => 'Dungeon Master\'s Guide',
                'publication_year' => 2014,
                'edition' => '5e',
            ]
        );

        $item = Item::factory()->create([
            'name' => 'Flametongue',
            'item_type_id' => $type->id,
            'description' => 'You can use a bonus action to speak this magic sword\'s command word',
            'rarity' => 'rare',
            'requires_attunement' => true,
            'is_magic' => true,
            'weight' => 3,
            'cost_cp' => 500000,
        ]);

        EntitySource::create([
            'reference_type' => Item::class,
            'reference_id' => $item->id,
            'source_id' => $source->id,
            'pages' => '170',
        ]);

        $item->refresh();

        $searchable = $item->toSearchableArray();

        $this->assertArrayHasKey('id', $searchable);
        $this->assertEquals('Flametongue', $searchable['name']);
        $this->assertEquals('Melee Weapon', $searchable['type_name']);
        $this->assertEquals('M', $searchable['type_code']);
        $this->assertEquals('rare', $searchable['rarity']);
        $this->assertTrue($searchable['is_magic']);
        $this->assertTrue($searchable['requires_attunement']);
        $this->assertArrayHasKey('description', $searchable);
        $this->assertEquals(['Dungeon Master\'s Guide'], $searchable['sources']);
        $this->assertEquals(['DMG'], $searchable['source_codes']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_defines_searchable_relationships(): void
    {
        $item = new Item;

        $this->assertIsArray($item->searchableWith());
        $this->assertContains('itemType', $item->searchableWith());
        $this->assertContains('sources.source', $item->searchableWith());
        $this->assertContains('damageType', $item->searchableWith());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_correct_search_index_name(): void
    {
        $item = new Item;

        $this->assertEquals('test_items', $item->searchableAs());
    }
}
