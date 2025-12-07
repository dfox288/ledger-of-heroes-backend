<?php

namespace Database\Seeders\Testing;

use App\Models\DamageType;
use App\Models\EntitySource;
use App\Models\Item;
use App\Models\ItemProperty;
use App\Models\ItemType;
use App\Models\Source;

class ItemFixtureSeeder extends FixtureSeeder
{
    protected function fixturePath(): string
    {
        return 'tests/fixtures/entities/items.json';
    }

    protected function model(): string
    {
        return Item::class;
    }

    protected function createFromFixture(array $item): void
    {
        // Resolve item type by code
        $itemType = ItemType::where('code', $item['item_type'])->first();

        // Resolve damage type by code (for weapons)
        $damageType = null;
        if (! empty($item['damage_type'])) {
            $damageType = DamageType::where('code', $item['damage_type'])->first();
        }

        // Generate full_slug from source (if available)
        $fullSlug = null;
        if (! empty($item['source'])) {
            $sourceCode = strtolower($item['source']);
            $fullSlug = $sourceCode.':'.$item['slug'];
        }

        // Create item
        $itemModel = Item::create([
            'name' => $item['name'],
            'slug' => $item['slug'],
            'full_slug' => $fullSlug,
            'item_type_id' => $itemType?->id,
            'detail' => $item['detail'],
            'rarity' => $item['rarity'],
            'requires_attunement' => $item['requires_attunement'],
            'is_magic' => $item['is_magic'],
            'cost_cp' => $item['cost_cp'],
            'weight' => $item['weight'],
            'description' => $item['description'],
            // Weapon-specific fields
            'damage_dice' => $item['damage_dice'],
            'versatile_damage' => $item['versatile_damage'],
            'damage_type_id' => $damageType?->id,
            'range_normal' => $item['range_normal'],
            'range_long' => $item['range_long'],
            // Armor-specific fields
            'armor_class' => $item['armor_class'],
            'strength_requirement' => $item['strength_requirement'],
            'stealth_disadvantage' => $item['stealth_disadvantage'],
            // Charge mechanics (magic items)
            'charges_max' => $item['charges_max'],
            'recharge_formula' => $item['recharge_formula'],
            'recharge_timing' => $item['recharge_timing'],
        ]);

        // Attach properties
        if (! empty($item['properties'])) {
            $propertyIds = ItemProperty::whereIn('code', $item['properties'])->pluck('id');
            $itemModel->properties()->attach($propertyIds);
        }

        // Create entity source
        if (! empty($item['source'])) {
            $source = Source::where('code', $item['source'])->first();
            if ($source) {
                EntitySource::create([
                    'reference_type' => Item::class,
                    'reference_id' => $itemModel->id,
                    'source_id' => $source->id,
                    'pages' => $item['pages'] ?? null,
                ]);
            }
        }
    }
}
