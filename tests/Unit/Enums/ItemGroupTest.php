<?php

namespace Tests\Unit\Enums;

use App\Enums\ItemGroup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class ItemGroupTest extends TestCase
{
    #[Test]
    #[DataProvider('itemTypeToGroupProvider')]
    public function it_maps_item_types_to_correct_groups(string $itemTypeName, ItemGroup $expectedGroup): void
    {
        $result = ItemGroup::fromItemType($itemTypeName);

        $this->assertSame($expectedGroup, $result);
    }

    public static function itemTypeToGroupProvider(): array
    {
        return [
            // Weapons
            ['Melee Weapon', ItemGroup::WEAPONS],
            ['Ranged Weapon', ItemGroup::WEAPONS],
            ['Ammunition', ItemGroup::WEAPONS],

            // Armor
            ['Light Armor', ItemGroup::ARMOR],
            ['Medium Armor', ItemGroup::ARMOR],
            ['Heavy Armor', ItemGroup::ARMOR],
            ['Shield', ItemGroup::ARMOR],

            // Consumables
            ['Potion', ItemGroup::CONSUMABLES],
            ['Scroll', ItemGroup::CONSUMABLES],

            // Magic Items
            ['Wand', ItemGroup::MAGIC_ITEMS],
            ['Rod', ItemGroup::MAGIC_ITEMS],
            ['Ring', ItemGroup::MAGIC_ITEMS],
            ['Staff', ItemGroup::MAGIC_ITEMS],
            ['Wondrous Item', ItemGroup::MAGIC_ITEMS],

            // Gear
            ['Adventuring Gear', ItemGroup::GEAR],
            ['Trade Goods', ItemGroup::GEAR],
        ];
    }

    #[Test]
    public function it_returns_miscellaneous_for_null_item_type(): void
    {
        $result = ItemGroup::fromItemType(null);

        $this->assertSame(ItemGroup::MISCELLANEOUS, $result);
    }

    #[Test]
    public function it_returns_miscellaneous_for_unknown_item_type(): void
    {
        $result = ItemGroup::fromItemType('Unknown Type');

        $this->assertSame(ItemGroup::MISCELLANEOUS, $result);
    }

    #[Test]
    public function it_has_correct_display_values(): void
    {
        $this->assertSame('Weapons', ItemGroup::WEAPONS->value);
        $this->assertSame('Armor', ItemGroup::ARMOR->value);
        $this->assertSame('Consumables', ItemGroup::CONSUMABLES->value);
        $this->assertSame('Magic Items', ItemGroup::MAGIC_ITEMS->value);
        $this->assertSame('Gear', ItemGroup::GEAR->value);
        $this->assertSame('Miscellaneous', ItemGroup::MISCELLANEOUS->value);
    }
}
