<?php

namespace App\Services\Importers;

use App\Models\DamageType;
use App\Models\EntitySource;
use App\Models\Item;
use App\Models\ItemProperty;
use App\Models\ItemType;
use App\Models\Modifier;
use App\Models\Proficiency;
use App\Models\Source;
use App\Services\Parsers\ItemXmlParser;
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
                'is_magic' => $itemData['is_magic'],
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

        // Import modifiers (polymorphic)
        $this->importModifiers($item, $itemData['modifiers']);

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
            $source = $this->getSourceByCode($sourceData['code']);

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

    private function importModifiers(Item $item, array $modifiers): void
    {
        // Clear existing modifiers
        $item->modifiers()->delete();

        foreach ($modifiers as $modData) {
            Modifier::create([
                'reference_type' => Item::class,
                'reference_id' => $item->id,
                'modifier_category' => $modData['category'],
                'modifier_text' => $modData['text'],
            ]);
        }
    }

    private function getSourceByCode(string $code): Source
    {
        if (!isset($this->sourceCache[$code])) {
            $source = Source::where('code', $code)->firstOrFail();
            $this->sourceCache[$code] = $source;
        }

        return $this->sourceCache[$code];
    }

    private function getItemPropertyId(string $code): ?int
    {
        if (!isset($this->itemPropertyCache[$code])) {
            $property = ItemProperty::where('code', $code)->first();
            $this->itemPropertyCache[$code] = $property?->id;
        }

        return $this->itemPropertyCache[$code];
    }

    public function importFromFile(string $filePath): int
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $xmlContent = file_get_contents($filePath);
        $parser = new ItemXmlParser();
        $items = $parser->parse($xmlContent);

        $count = 0;
        foreach ($items as $itemData) {
            $this->import($itemData);
            $count++;
        }

        return $count;
    }
}
