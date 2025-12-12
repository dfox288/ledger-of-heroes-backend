<?php

namespace Tests\Unit\Models;

use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\EntityItem;
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
    public function quantity_casts_to_integer(): void
    {
        $entityItem = EntityItem::factory()->create(['quantity' => '5']);

        $this->assertIsInt($entityItem->quantity);
        $this->assertEquals(5, $entityItem->quantity);
    }
}
