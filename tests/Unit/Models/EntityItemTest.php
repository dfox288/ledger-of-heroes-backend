<?php

namespace Tests\Unit\Models;

use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\EntityItem;
use App\Models\EquipmentChoiceItem;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EntityItemTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_morphs_to_reference(): void
    {
        $class = CharacterClass::factory()->create();
        $entityItem = EntityItem::factory()->create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
        ]);

        $this->assertInstanceOf(CharacterClass::class, $entityItem->reference);
        $this->assertEquals($class->id, $entityItem->reference->id);
    }

    #[Test]
    public function it_morphs_to_background(): void
    {
        $background = Background::factory()->create();
        $entityItem = EntityItem::factory()->create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
        ]);

        $this->assertInstanceOf(Background::class, $entityItem->reference);
        $this->assertEquals($background->id, $entityItem->reference->id);
    }

    #[Test]
    public function it_belongs_to_item(): void
    {
        $item = Item::factory()->create();
        $entityItem = EntityItem::factory()->create(['item_id' => $item->id]);

        $this->assertInstanceOf(Item::class, $entityItem->item);
        $this->assertEquals($item->id, $entityItem->item->id);
    }

    #[Test]
    public function it_has_many_choice_items(): void
    {
        $entityItem = EntityItem::factory()->create(['is_choice' => true]);
        EquipmentChoiceItem::factory()->count(3)->create([
            'entity_item_id' => $entityItem->id,
        ]);

        $this->assertCount(3, $entityItem->choiceItems);
        $this->assertInstanceOf(EquipmentChoiceItem::class, $entityItem->choiceItems->first());
    }

    #[Test]
    public function choice_items_are_ordered_by_sort_order(): void
    {
        $entityItem = EntityItem::factory()->create(['is_choice' => true]);

        $choice1 = EquipmentChoiceItem::factory()->create([
            'entity_item_id' => $entityItem->id,
            'sort_order' => 3,
        ]);
        $choice2 = EquipmentChoiceItem::factory()->create([
            'entity_item_id' => $entityItem->id,
            'sort_order' => 1,
        ]);
        $choice3 = EquipmentChoiceItem::factory()->create([
            'entity_item_id' => $entityItem->id,
            'sort_order' => 2,
        ]);

        $choiceItems = $entityItem->fresh()->choiceItems;

        $this->assertEquals($choice2->id, $choiceItems[0]->id);
        $this->assertEquals($choice3->id, $choiceItems[1]->id);
        $this->assertEquals($choice1->id, $choiceItems[2]->id);
    }

    #[Test]
    public function is_choice_casts_to_boolean(): void
    {
        $entityItem = EntityItem::factory()->create(['is_choice' => 1]);

        $this->assertIsBool($entityItem->is_choice);
        $this->assertTrue($entityItem->is_choice);
    }

    #[Test]
    public function quantity_casts_to_integer(): void
    {
        $entityItem = EntityItem::factory()->create(['quantity' => '5']);

        $this->assertIsInt($entityItem->quantity);
        $this->assertEquals(5, $entityItem->quantity);
    }

    #[Test]
    public function choice_option_casts_to_integer(): void
    {
        $entityItem = EntityItem::factory()->create(['choice_option' => '2']);

        $this->assertIsInt($entityItem->choice_option);
        $this->assertEquals(2, $entityItem->choice_option);
    }
}
