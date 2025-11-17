<?php

namespace Tests\Unit\Services;

use App\Models\Item;
use App\Models\ItemType;
use App\Models\ItemRarity;
use App\Models\SourceBook;
use App\Services\Importers\ItemImporter;
use App\Services\Parsers\ItemXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemImporterTest extends TestCase
{
    use RefreshDatabase;

    private ItemImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new ItemImporter(new ItemXmlParser());
    }

    public function test_imports_item_from_parsed_data(): void
    {
        $data = [
            'name' => 'Test Sword',
            'type_code' => 'M',
            'rarity_code' => 'common',
            'weight_lbs' => 3.0,
            'value_gp' => 15.0,
            'description' => 'A test weapon',
            'source_code' => 'PHB',
            'source_page' => 100,
            'properties' => [],
            'damage_dice' => null,
            'damage_dice_versatile' => null,
            'damage_type_code' => null,
            'range' => null,
        ];

        $item = $this->importer->importFromParsedData($data);

        $this->assertInstanceOf(Item::class, $item);
        $this->assertEquals('Test Sword', $item->name);
        $this->assertEquals(3.0, $item->weight_lbs);
        $this->assertEquals(15.0, $item->value_gp);
    }

    public function test_updates_existing_item_instead_of_duplicating(): void
    {
        // Create item type and rarity
        $itemType = ItemType::where('code', 'M')->first();
        $rarity = ItemRarity::where('code', 'common')->first();
        $sourceBook = SourceBook::where('code', 'PHB')->first();

        $item = Item::create([
            'name' => 'Existing Item',
            'slug' => 'existing-item',
            'item_type_id' => $itemType->id,
            'rarity_id' => $rarity->id,
            'description' => 'Original description',
            'source_book_id' => $sourceBook->id,
        ]);

        $initialId = $item->id;

        $data = [
            'name' => 'Existing Item',
            'type_code' => 'M',
            'rarity_code' => 'common',
            'weight_lbs' => 5.0,
            'value_gp' => 20.0,
            'description' => 'Updated description',
            'source_code' => 'PHB',
            'source_page' => 150,
            'properties' => [],
            'damage_dice' => null,
            'damage_dice_versatile' => null,
            'damage_type_code' => null,
            'range' => null,
        ];

        $updatedItem = $this->importer->importFromParsedData($data);

        $this->assertEquals($initialId, $updatedItem->id);
        $this->assertEquals('Updated description', $updatedItem->description);
        $this->assertEquals(1, Item::count());
    }

    public function test_handles_unknown_item_type(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unknown item type');

        $data = [
            'name' => 'Unknown Item',
            'type_code' => 'UNKNOWN',
            'rarity_code' => 'common',
            'description' => 'Test',
            'source_code' => 'PHB',
            'source_page' => 100,
            'properties' => [],
            'damage_dice' => null,
            'damage_dice_versatile' => null,
            'damage_type_code' => null,
            'range' => null,
        ];

        $this->importer->importFromParsedData($data);
    }
}
