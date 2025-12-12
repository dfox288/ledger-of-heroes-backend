<?php

namespace App\Services\Importers;

use App\Enums\DataTableType;
use App\Models\Background;
use App\Models\CharacterTrait;
use App\Models\EntityChoice;
use App\Models\EntityDataTable;
use App\Models\EntityDataTableEntry;
use App\Models\EntityItem;
use App\Models\Item;
use App\Services\Importers\Concerns\ImportsLanguages;
use App\Services\Matching\ItemMatchingService;
use App\Services\Parsers\BackgroundXmlParser;
use App\Services\Parsers\Traits\ParsesChoices;

class BackgroundImporter extends BaseImporter
{
    use ImportsLanguages;
    use ParsesChoices;

    protected function importEntity(array $data): Background
    {
        // Generate source-prefixed slug
        $sources = $data['sources'] ?? [];
        $slug = $this->generateSlug($data['name'], $sources);

        // 1. Upsert background using slug as unique key
        $background = Background::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $data['name'],
            ]
        );

        // 2. Clear existing polymorphic relationships
        // Note: Deleting traits will cascade delete their random tables
        // Sources are cleared by ImportsSources trait
        $background->traits()->delete();
        $background->proficiencies()->delete();
        $background->languages()->delete();
        $background->equipment()->delete();

        // Also clear equipment choices
        EntityChoice::where('reference_type', Background::class)
            ->where('reference_id', $background->id)
            ->where('choice_type', 'equipment')
            ->delete();

        // 3. Import traits
        $traits = [];
        foreach ($data['traits'] as $traitData) {
            $trait = $background->traits()->create([
                'name' => $traitData['name'],
                'description' => $traitData['description'],
                'category' => $traitData['category'],
            ]);
            $traits[$traitData['name']] = $trait;
        }

        // 5. Import proficiencies using trait (handles choices automatically)
        $proficienciesData = array_map(function ($profData) {
            return [
                'type' => $profData['proficiency_type'],
                'name' => $profData['proficiency_name'],
                'proficiency_type_id' => $profData['proficiency_type_id'] ?? null,
                'proficiency_subcategory' => $profData['proficiency_subcategory'] ?? null,
                'skill_id' => $profData['skill_id'] ?? null,
                'grants' => $profData['grants'] ?? true,
                'is_choice' => $profData['is_choice'] ?? false,
                'quantity' => $profData['quantity'] ?? 1,
            ];
        }, $data['proficiencies']);
        $this->importEntityProficiencies($background, $proficienciesData);

        // 6. Import sources using trait
        $this->importEntitySources($background, $data['sources']);

        // 7. Import languages
        $this->importEntityLanguages($background, $data['languages'] ?? []);

        // 8. Import equipment (with item matching)
        $this->importBackgroundEquipment($background, $data['equipment'] ?? []);

        // 9. Import ALL embedded random tables (linked to traits, NOT background)
        foreach ($data['random_tables'] ?? [] as $tableData) {
            // Find the trait this table belongs to
            if (! isset($tableData['trait_name']) || ! isset($traits[$tableData['trait_name']])) {
                // Skip tables that don't have a trait association
                continue;
            }

            $trait = $traits[$tableData['trait_name']];

            // Create table linked to the TRAIT (not background)
            $table = EntityDataTable::create([
                'reference_type' => CharacterTrait::class,
                'reference_id' => $trait->id,
                'table_name' => $tableData['name'],
                'dice_type' => $tableData['dice_type'],
                'table_type' => DataTableType::RANDOM,
            ]);

            foreach ($tableData['entries'] as $index => $entry) {
                EntityDataTableEntry::create([
                    'entity_data_table_id' => $table->id,
                    'roll_min' => $entry['roll_min'],
                    'roll_max' => $entry['roll_max'],
                    'result_text' => $entry['result_text'],
                    'sort_order' => $index,
                ]);
            }

            // Link table back to trait via entity_data_table_id
            $trait->update(['entity_data_table_id' => $table->id]);
        }

        // Refresh to load all relationships created during import
        $background->refresh();

        return $background;
    }

    /**
     * Import background equipment.
     *
     * - Fixed equipment goes to entity_items table
     * - Equipment choices go to entity_choices table
     */
    private function importBackgroundEquipment(Background $background, array $equipmentData): void
    {
        $itemMatcher = new ItemMatchingService;
        $choiceIndex = 0;

        foreach ($equipmentData as $equipData) {
            $isChoice = $equipData['is_choice'] ?? false;

            if ($isChoice) {
                // Equipment choice - create EntityChoice record
                $choiceIndex++;
                $choiceGroup = $equipData['choice_group'] ?? 'equipment_choice_'.$choiceIndex;
                $choiceOption = $equipData['choice_option'] ?? 1;
                $description = $equipData['choice_description'] ?? $equipData['item_name'] ?? null;

                // Try to match item for the slug
                $itemSlug = null;
                $categorySlug = null;
                if (! empty($equipData['item_name'])) {
                    $matchedItem = $itemMatcher->matchItem($equipData['item_name']);
                    if ($matchedItem) {
                        $itemSlug = $matchedItem->slug;
                    }
                }

                // If proficiency_subcategory is set, it's a category choice (e.g., "any artisan's tools")
                if (! empty($equipData['proficiency_subcategory'])) {
                    $categorySlug = $equipData['proficiency_subcategory'];
                    $itemSlug = null; // Category choice, not specific item
                }

                $this->createEquipmentChoice(
                    referenceType: Background::class,
                    referenceId: $background->id,
                    choiceGroup: $choiceGroup,
                    choiceOption: $choiceOption,
                    itemSlug: $itemSlug,
                    categorySlug: $categorySlug,
                    description: $description,
                    levelGranted: 1,
                    constraints: null
                );

                continue;
            }

            // Fixed equipment - create EntityItem record
            $itemId = $equipData['item_id'] ?? null;
            $itemName = $equipData['item_name'] ?? null;
            $description = null;

            if ($itemId === null && $itemName !== null) {
                $matchedItem = $itemMatcher->matchItem($itemName);
                if ($matchedItem) {
                    $itemId = $matchedItem->id;
                } else {
                    // No match found - store in description field
                    $description = $itemName;
                }
            }

            EntityItem::create([
                'reference_type' => Background::class,
                'reference_id' => $background->id,
                'item_id' => $itemId,
                'quantity' => $equipData['quantity'] ?? 1,
                'description' => $description,
            ]);
        }
    }

    public function getParser(): object
    {
        return new BackgroundXmlParser;
    }
}
