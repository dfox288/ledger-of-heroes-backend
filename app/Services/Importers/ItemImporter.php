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
use App\Models\Proficiency;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use App\Models\Source;
use App\Services\Importers\Concerns\CachesLookupTables;
use App\Services\Importers\Concerns\ImportsModifiers;
use App\Services\Parsers\ItemTableDetector;
use App\Services\Parsers\ItemTableParser;
use App\Services\Parsers\ItemXmlParser;

class ItemImporter extends BaseImporter
{
    use CachesLookupTables;
    use ImportsModifiers;

    protected function importEntity(array $itemData): Item
    {
        // Lookup foreign keys
        $itemTypeId = $this->cachedFindId(ItemType::class, 'code', $itemData['type_code']);
        $damageTypeId = ! empty($itemData['damage_type_code'])
            ? $this->cachedFindId(DamageType::class, 'code', $itemData['damage_type_code'])
            : null;

        // Create or update item
        $item = Item::updateOrCreate(
            ['slug' => $this->generateSlug($itemData['name'])],
            [
                'name' => $itemData['name'],
                'item_type_id' => $itemTypeId,
                'detail' => $itemData['detail'] ?? null,
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
        $this->importEntityModifiers($item, $itemData['modifiers']);

        // Import shield AC modifier (for all shields with AC values)
        $this->importShieldAcModifier($item, $itemData);

        // Import armor AC modifier (for all armor with AC values)
        $this->importArmorAcModifier($item, $itemData);

        // Import abilities
        $this->importAbilities($item, $itemData['abilities']);

        // Import random tables from description text
        $this->importRandomTables($item, $itemData['description']);

        // Import prerequisites from strength_requirement
        $this->importPrerequisites($item, $itemData['strength_requirement']);

        return $item;
    }

    private function importSources(Item $item, array $sources): void
    {
        // Clear existing sources
        $item->sources()->delete();

        // Deduplicate sources by source_id and merge page numbers
        // Example: XGE p.137, XGE p.83 → XGE p.137, 83
        $sourcesByCode = [];
        foreach ($sources as $sourceData) {
            $code = $sourceData['code'];
            if (! isset($sourcesByCode[$code])) {
                $sourcesByCode[$code] = [];
            }
            if (! empty($sourceData['pages'])) {
                $sourcesByCode[$code][] = $sourceData['pages'];
            }
        }

        foreach ($sourcesByCode as $code => $pagesList) {
            $source = $this->cachedFind(Source::class, 'code', $code);

            EntitySource::create([
                'reference_type' => Item::class,
                'reference_id' => $item->id,
                'source_id' => $source->id,
                'pages' => implode(', ', $pagesList),
            ]);
        }
    }

    private function importProperties(Item $item, array $propertyCodes): void
    {
        // Clear existing properties
        $item->properties()->detach();

        $propertyIds = [];
        foreach ($propertyCodes as $code) {
            $propertyId = $this->cachedFindId(ItemProperty::class, 'code', $code, useFail: false);
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

    /**
     * Import shield AC modifier.
     *
     * For shields with AC values, create a base AC bonus modifier in addition to the armor_class column.
     * This provides dual storage for backward compatibility while making shields consistent with
     * magic items that use modifiers.
     *
     * Uses 'ac_bonus' category to distinguish from magic enchantments ('ac_magic').
     *
     * Examples:
     * - Regular Shield (AC=2): Creates modifier(ac_bonus, 2)
     * - Shield +1 (AC=2): Creates modifier(ac_bonus, 2) for base + modifier(ac_magic, 1) from XML
     * - Shield +2 (AC=2): Creates modifier(ac_bonus, 2) for base + modifier(ac_magic, 2) from XML
     *
     * @param  Item  $item  The imported item
     * @param  array  $itemData  The parsed item data
     */
    private function importShieldAcModifier(Item $item, array $itemData): void
    {
        // Only process items with type_code 'S' (Shield) and armor_class > 0
        if ($itemData['type_code'] !== 'S' || empty($itemData['armor_class']) || $itemData['armor_class'] <= 0) {
            return;
        }

        // Check if base AC bonus modifier already exists (avoid duplicates on re-import)
        $hasBaseModifier = $item->modifiers()
            ->where('modifier_category', 'ac_bonus')
            ->where('value', $itemData['armor_class'])
            ->exists();

        if ($hasBaseModifier) {
            return; // Already has base modifier, skip
        }

        // Create base AC bonus modifier
        $item->modifiers()->create([
            'modifier_category' => 'ac_bonus',
            'value' => $itemData['armor_class'],
        ]);
    }

    /**
     * Import armor AC base modifier.
     *
     * For armor with AC values, create a base AC modifier that represents the armor's base AC.
     * Also stores metadata about DEX modifier applicability based on armor type.
     *
     * Uses 'ac_base' category to distinguish from additive bonuses (shields/magic).
     *
     * D&D 5e AC Rules:
     * - Light Armor (LA): AC = base + full DEX modifier
     * - Medium Armor (MA): AC = base + DEX modifier (max +2)
     * - Heavy Armor (HA): AC = base only (no DEX modifier)
     *
     * Examples:
     * - Leather Armor (LA, AC=11): ac_base(11) with "dex_modifier: full"
     * - Half Plate (MA, AC=15): ac_base(15) with "dex_modifier: max_2"
     * - Plate Armor (HA, AC=18): ac_base(18) with "dex_modifier: none"
     *
     * @param  Item  $item  The imported item
     * @param  array  $itemData  The parsed item data
     */
    private function importArmorAcModifier(Item $item, array $itemData): void
    {
        // Only process armor types: LA (Light), MA (Medium), HA (Heavy)
        if (! in_array($itemData['type_code'], ['LA', 'MA', 'HA'])) {
            return;
        }

        // Only process if armor has AC value
        if (empty($itemData['armor_class']) || $itemData['armor_class'] <= 0) {
            return;
        }

        // Check if base AC modifier already exists (avoid duplicates on re-import)
        $hasBaseModifier = $item->modifiers()
            ->where('modifier_category', 'ac_base')
            ->where('value', $itemData['armor_class'])
            ->exists();

        if ($hasBaseModifier) {
            return; // Already has base modifier, skip
        }

        // Determine DEX modifier applicability based on armor type
        $dexModifier = match ($itemData['type_code']) {
            'LA' => 'full',      // Light armor: full DEX modifier
            'MA' => 'max_2',     // Medium armor: DEX modifier capped at +2
            'HA' => 'none',      // Heavy armor: no DEX modifier
            default => null,
        };

        // Create base AC modifier with DEX modifier metadata
        $item->modifiers()->create([
            'modifier_category' => 'ac_base',
            'value' => $itemData['armor_class'],
            'condition' => $dexModifier ? "dex_modifier: {$dexModifier}" : null,
        ]);
    }

    protected function getParser(): object
    {
        return new ItemXmlParser;
    }
}
