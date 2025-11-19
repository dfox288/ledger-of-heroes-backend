<?php

namespace Tests\Feature\Migrations;

use App\Models\Background;
use App\Models\EntityItem;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EntityItemsTableTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_entity_items_table(): void
    {
        $this->assertTrue(Schema::hasTable('entity_items'));
    }

    #[Test]
    public function it_has_all_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('entity_items', [
            'id',
            'reference_type',
            'reference_id',
            'item_id',
            'quantity',
            'is_choice',
            'choice_description',
        ]));
    }

    #[Test]
    public function it_has_polymorphic_index(): void
    {
        // Check if index exists on reference_type and reference_id
        $indexes = Schema::getIndexes('entity_items');
        $hasPolymorphicIndex = collect($indexes)->contains(function ($index) {
            return in_array('reference_type', $index['columns'])
                && in_array('reference_id', $index['columns']);
        });

        $this->assertTrue($hasPolymorphicIndex);
    }

    #[Test]
    public function background_has_equipment_relationship(): void
    {
        $background = Background::factory()->create();
        $equipment = EntityItem::factory()
            ->forEntity(Background::class, $background->id)
            ->create();

        $this->assertCount(1, $background->equipment);
        $this->assertEquals($equipment->id, $background->equipment->first()->id);
    }

    #[Test]
    public function entity_item_factory_supports_for_entity_state(): void
    {
        $background = Background::factory()->create();
        $equipment = EntityItem::factory()
            ->forEntity(Background::class, $background->id)
            ->create();

        $this->assertEquals(Background::class, $equipment->reference_type);
        $this->assertEquals($background->id, $equipment->reference_id);
    }

    #[Test]
    public function entity_item_factory_supports_with_item_state(): void
    {
        $item = Item::factory()->create();
        $equipment = EntityItem::factory()
            ->withItem($item->id, 5)
            ->create();

        $this->assertEquals($item->id, $equipment->item_id);
        $this->assertEquals(5, $equipment->quantity);
    }

    #[Test]
    public function entity_item_factory_supports_as_choice_state(): void
    {
        $equipment = EntityItem::factory()
            ->asChoice('one of your choice')
            ->create();

        $this->assertTrue($equipment->is_choice);
        $this->assertEquals('one of your choice', $equipment->choice_description);
    }

    #[Test]
    public function entity_item_has_reference_relationship(): void
    {
        $background = Background::factory()->create();
        $equipment = EntityItem::factory()
            ->forEntity(Background::class, $background->id)
            ->create();

        $this->assertInstanceOf(Background::class, $equipment->reference);
        $this->assertEquals($background->id, $equipment->reference->id);
    }

    #[Test]
    public function entity_item_has_item_relationship(): void
    {
        $item = Item::factory()->create();
        $equipment = EntityItem::factory()
            ->withItem($item->id)
            ->create();

        $this->assertInstanceOf(Item::class, $equipment->item);
        $this->assertEquals($item->id, $equipment->item->id);
    }
}
