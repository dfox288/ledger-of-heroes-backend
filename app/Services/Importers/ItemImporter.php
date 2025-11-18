<?php

namespace App\Services\Importers;

use App\Models\DamageType;
use App\Models\EntitySource;
use App\Models\Item;
use App\Models\ItemProperty;
use App\Models\ItemType;
use App\Models\Proficiency;
use App\Models\Source;
use Illuminate\Support\Str;

class ItemImporter
{
    private array $itemTypeCache = [];
    private array $damageTypeCache = [];
    private array $itemPropertyCache = [];
    private array $sourceCache = [];

    public function import(array $itemData): Item
    {
        // Lookup foreign keys
        $itemTypeId = $this->getItemTypeId($itemData['type_code']);
        $damageTypeId = !empty($itemData['damage_type_code'])
            ? $this->getDamageTypeId($itemData['damage_type_code'])
            : null;

        // Create or update item
        $item = Item::updateOrCreate(
            ['slug' => Str::slug($itemData['name'])],
            [
                'name' => $itemData['name'],
                'item_type_id' => $itemTypeId,
                'rarity' => $itemData['rarity'],
                'requires_attunement' => $itemData['requires_attunement'],
                'cost_cp' => $itemData['cost_cp'],
                'weight' => $itemData['weight'],
                'damage_dice' => $itemData['damage_dice'],
                'versatile_damage' => $itemData['versatile_damage'],
                'damage_type_id' => $damageTypeId,
                'armor_class' => $itemData['armor_class'],
                'strength_requirement' => $itemData['strength_requirement'],
                'stealth_disadvantage' => $itemData['stealth_disadvantage'],
                'weapon_range' => $itemData['weapon_range'],
                'description' => $itemData['description'],
            ]
        );

        // Import sources (polymorphic)
        $this->importSources($item, $itemData['sources']);

        // Import properties (M2M)
        $this->importProperties($item, $itemData['properties']);

        // Import proficiencies (polymorphic)
        $this->importProficiencies($item, $itemData['proficiencies']);

        return $item;
    }

    private function getItemTypeId(string $code): int
    {
        if (!isset($this->itemTypeCache[$code])) {
            $itemType = ItemType::where('code', $code)->firstOrFail();
            $this->itemTypeCache[$code] = $itemType->id;
        }

        return $this->itemTypeCache[$code];
    }

    private function getDamageTypeId(string $code): int
    {
        $code = strtoupper($code);

        if (!isset($this->damageTypeCache[$code])) {
            $damageType = DamageType::where('code', $code)->firstOrFail();
            $this->damageTypeCache[$code] = $damageType->id;
        }

        return $this->damageTypeCache[$code];
    }

    private function importSources(Item $item, array $sources): void
    {
        // Clear existing sources
        $item->sources()->delete();

        foreach ($sources as $sourceData) {
            $source = $this->getSourceByName($sourceData['source_name']);

            EntitySource::create([
                'reference_type' => Item::class,
                'reference_id' => $item->id,
                'source_id' => $source->id,
                'pages' => $sourceData['pages'],
            ]);
        }
    }

    private function importProperties(Item $item, array $propertyCodes): void
    {
        // Clear existing properties
        $item->properties()->detach();

        $propertyIds = [];
        foreach ($propertyCodes as $code) {
            $propertyId = $this->getItemPropertyId($code);
            if ($propertyId) {
                $propertyIds[] = $propertyId;
            }
        }

        // Attach properties
        $item->properties()->attach($propertyIds);
    }

    private function importProficiencies(Item $item, array $proficiencies): void
    {
        // Clear existing proficiencies
        $item->proficiencies()->delete();

        foreach ($proficiencies as $profData) {
            Proficiency::create([
                'reference_type' => Item::class,
                'reference_id' => $item->id,
                'proficiency_type' => $profData['type'],
                'proficiency_name' => $profData['name'],
            ]);
        }
    }

    private function getSourceByName(string $name): Source
    {
        if (!isset($this->sourceCache[$name])) {
            $source = Source::where('name', 'like', '%' . $name . '%')->firstOrFail();
            $this->sourceCache[$name] = $source;
        }

        return $this->sourceCache[$name];
    }

    private function getItemPropertyId(string $code): ?int
    {
        if (!isset($this->itemPropertyCache[$code])) {
            $property = ItemProperty::where('code', $code)->first();
            $this->itemPropertyCache[$code] = $property?->id;
        }

        return $this->itemPropertyCache[$code];
    }
}
