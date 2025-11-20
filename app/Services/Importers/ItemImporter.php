<?php

namespace App\Services\Importers;

use App\Models\AbilityScore;
use App\Models\DamageType;
use App\Models\EntityPrerequisite;
use App\Models\EntitySource;
use App\Models\Item;
use App\Models\ItemAbility;
use App\Models\ItemProperty;
use App\Models\ItemType;
use App\Models\Modifier;
use App\Models\Proficiency;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use App\Models\Source;
use App\Services\Parsers\ItemTableDetector;
use App\Services\Parsers\ItemTableParser;
use App\Services\Parsers\ItemXmlParser;

class ItemImporter extends BaseImporter
{
    private array $itemTypeCache = [];

    private array $damageTypeCache = [];

    private array $itemPropertyCache = [];

    private array $sourceCache = [];

    protected function importEntity(array $itemData): Item
    {
        // Lookup foreign keys
        $itemTypeId = $this->getItemTypeId($itemData['type_code']);
        $damageTypeId = ! empty($itemData['damage_type_code'])
            ? $this->getDamageTypeId($itemData['damage_type_code'])
            : null;

        // Create or update item
        $item = Item::updateOrCreate(
            ['slug' => $this->generateSlug($itemData['name'])],
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
                'range_normal' => $itemData['range_normal'],
                'range_long' => $itemData['range_long'],
                'armor_class' => $itemData['armor_class'],
                'strength_requirement' => $itemData['strength_requirement'],
                'stealth_disadvantage' => $itemData['stealth_disadvantage'],
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

        // Import abilities
        $this->importAbilities($item, $itemData['abilities']);

        // Import random tables from description text
        $this->importRandomTables($item, $itemData['description']);

        // Import prerequisites from strength_requirement
        $this->importPrerequisites($item, $itemData['strength_requirement']);

        return $item;
    }

    private function getItemTypeId(string $code): int
    {
        if (! isset($this->itemTypeCache[$code])) {
            $itemType = ItemType::where('code', $code)->firstOrFail();
            $this->itemTypeCache[$code] = $itemType->id;
        }

        return $this->itemTypeCache[$code];
    }

    private function getDamageTypeId(string $code): int
    {
        $code = strtoupper($code);

        if (! isset($this->damageTypeCache[$code])) {
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
                'proficiency_type_id' => $profData['proficiency_type_id'] ?? null,
                'grants' => $profData['grants'] ?? false, // Items require proficiency
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
                'value' => (string) $modData['value'], // Now an integer from parser
                'ability_score_id' => $modData['ability_score_id'] ?? null,
                'skill_id' => $modData['skill_id'] ?? null,
                'damage_type_id' => $modData['damage_type_id'] ?? null,
            ]);
        }
    }

    private function importAbilities(Item $item, array $abilities): void
    {
        // Clear existing abilities
        $item->abilities()->delete();

        foreach ($abilities as $abilityData) {
            ItemAbility::create([
                'item_id' => $item->id,
                'ability_type' => $abilityData['ability_type'],
                'name' => $abilityData['name'],
                'description' => $abilityData['description'],
                'roll_formula' => $abilityData['roll_formula'] ?? null,
                'sort_order' => $abilityData['sort_order'],
            ]);
        }
    }

    private function importRandomTables(Item $item, string $description): void
    {
        // Detect tables in description
        $detector = new ItemTableDetector;
        $tables = $detector->detectTables($description);

        if (empty($tables)) {
            return;
        }

        // Clear existing tables
        $item->randomTables()->delete();

        foreach ($tables as $tableData) {
            $parser = new ItemTableParser;
            $parsed = $parser->parse($tableData['text'], $tableData['dice_type'] ?? null);

            if (empty($parsed['rows'])) {
                continue; // Skip tables with no valid rows
            }

            $table = RandomTable::create([
                'reference_type' => Item::class,
                'reference_id' => $item->id,
                'table_name' => $parsed['table_name'],
                'dice_type' => $parsed['dice_type'],
            ]);

            foreach ($parsed['rows'] as $index => $row) {
                RandomTableEntry::create([
                    'random_table_id' => $table->id,
                    'roll_min' => $row['roll_min'],
                    'roll_max' => $row['roll_max'],
                    'result_text' => $row['result_text'],
                    'sort_order' => $index,
                ]);
            }
        }
    }

    private function getSourceByCode(string $code): Source
    {
        if (! isset($this->sourceCache[$code])) {
            $source = Source::where('code', $code)->firstOrFail();
            $this->sourceCache[$code] = $source;
        }

        return $this->sourceCache[$code];
    }

    private function getItemPropertyId(string $code): ?int
    {
        if (! isset($this->itemPropertyCache[$code])) {
            $property = ItemProperty::where('code', $code)->first();
            $this->itemPropertyCache[$code] = $property?->id;
        }

        return $this->itemPropertyCache[$code];
    }

    private function importPrerequisites(Item $item, ?int $strengthRequirement): void
    {
        // Clear existing prerequisites
        $item->prerequisites()->delete();

        // If no strength requirement, nothing to import
        if (empty($strengthRequirement) || $strengthRequirement <= 0) {
            return;
        }

        // Get STR ability score
        $strAbilityScore = AbilityScore::where('code', 'STR')->first();

        if (! $strAbilityScore) {
            // Should never happen, but fail gracefully
            return;
        }

        // Create prerequisite record
        EntityPrerequisite::create([
            'reference_type' => Item::class,
            'reference_id' => $item->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strAbilityScore->id,
            'minimum_value' => $strengthRequirement,
            'description' => null,
            'group_id' => 1,
        ]);
    }

    public function importFromFile(string $filePath): int
    {
        if (! file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $xmlContent = file_get_contents($filePath);
        $parser = new ItemXmlParser;
        $items = $parser->parse($xmlContent);

        $count = 0;
        foreach ($items as $itemData) {
            $this->import($itemData);
            $count++;
        }

        return $count;
    }
}
