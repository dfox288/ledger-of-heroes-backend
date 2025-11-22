<?php

namespace App\Services\Importers;

use App\Models\AbilityScore;
use App\Models\DamageType;
use App\Models\Item;
use App\Models\ItemAbility;
use App\Models\ItemProperty;
use App\Models\ItemType;
use App\Models\Proficiency;
use App\Models\Source;
use App\Services\Importers\Concerns\CachesLookupTables;
use App\Services\Importers\Concerns\ImportsArmorModifiers;
use App\Services\Importers\Concerns\ImportsEntitySpells;
use App\Services\Importers\Concerns\ImportsModifiers;
use App\Services\Importers\Concerns\ImportsPrerequisites;
use App\Services\Importers\Concerns\ImportsRandomTablesFromText;
use App\Services\Parsers\Concerns\ParsesItemSavingThrows;
use App\Services\Parsers\Concerns\ParsesItemSpells;
use App\Services\Parsers\ItemXmlParser;

class ItemImporter extends BaseImporter
{
    use CachesLookupTables;
    use ImportsArmorModifiers;
    use ImportsEntitySpells;
    use ImportsModifiers;
    use ImportsPrerequisites;
    use ImportsRandomTablesFromText;
    use ParsesItemSavingThrows;
    use ParsesItemSpells;

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
                'charges_max' => $itemData['charges_max'] ?? null,
                'recharge_formula' => $itemData['recharge_formula'] ?? null,
                'recharge_timing' => $itemData['recharge_timing'] ?? null,
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

        // Import AC modifier for shields and armor (consolidated method)
        $this->importArmorAcModifier($item, $itemData['type_code'], $itemData['armor_class'] ?? 0);

        // Import abilities
        $this->importAbilities($item, $itemData['abilities']);

        // Import random tables from description text
        $this->importRandomTables($item, $itemData['description']);

        // Import prerequisites from strength_requirement
        $this->importPrerequisites($item, $itemData['strength_requirement']);

        // Import spells (from description text)
        $this->importSpells($item, $itemData['description']);

        // Import saving throws (from description text)
        $this->importSavingThrows($item, $itemData['description']);

        return $item;
    }

    private function importSources(Item $item, array $sources): void
    {
        // Use trait method with deduplication enabled
        // Deduplicates by source code and merges page numbers
        // Example: XGE p.137, XGE p.83 → XGE p.137, 83
        $this->importEntitySources($item, $sources, deduplicate: true);
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
        // Delegate to the generalized trait method
        $this->importRandomTablesFromText($item, $description);
    }

    private function importPrerequisites(Item $item, ?int $strengthRequirement): void
    {
        // If no strength requirement, clear any existing prerequisites
        if (empty($strengthRequirement) || $strengthRequirement <= 0) {
            $item->prerequisites()->delete();

            return;
        }

        // Use trait method for creating strength prerequisite
        $this->createStrengthPrerequisite($item, $strengthRequirement);
    }

    private function importSpells(Item $item, string $description): void
    {
        // Parse spells from description
        $parsedSpells = $this->parseItemSpells($description);

        if (empty($parsedSpells)) {
            return; // No spells found
        }

        // Transform parsed spells into format expected by ImportsEntitySpells trait
        $spellsData = array_map(function ($spellData) {
            return [
                'spell_name' => $spellData['spell_name'],
                'pivot_data' => [
                    'charges_cost_min' => $spellData['charges_cost_min'],
                    'charges_cost_max' => $spellData['charges_cost_max'],
                    'charges_cost_formula' => $spellData['charges_cost_formula'],
                    'usage_limit' => $spellData['usage_limit'] ?? null,
                ],
            ];
        }, $parsedSpells);

        // Delegate to the generalized trait method
        $this->importEntitySpells($item, $spellsData);
    }

    private function importSavingThrows(Item $item, string $description): void
    {
        // Parse saving throw from description
        $saveData = $this->parseItemSavingThrow($description);

        if (! $saveData) {
            return; // No saving throw found
        }

        // Look up ability score
        $abilityScore = $this->cachedFind(AbilityScore::class, 'code', $saveData['ability_code']);

        if (! $abilityScore) {
            \Log::warning("Ability score not found: {$saveData['ability_code']} (for item: {$item->name})");

            return;
        }

        // Create or update saving throw
        \DB::table('entity_saving_throws')->updateOrInsert(
            [
                'entity_type' => Item::class,
                'entity_id' => $item->id,
                'ability_score_id' => $abilityScore->id,
            ],
            [
                'dc' => $saveData['dc'],
                'save_effect' => $saveData['save_effect'],
                'is_initial_save' => $saveData['is_initial_save'],
                'save_modifier' => 'none',
                'updated_at' => now(),
            ]
        );
    }

    protected function getParser(): object
    {
        return new ItemXmlParser;
    }
}
