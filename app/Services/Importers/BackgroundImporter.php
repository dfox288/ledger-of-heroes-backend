<?php

namespace App\Services\Importers;

use App\Models\Background;
use App\Models\CharacterTrait;
use App\Models\EntityItem;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use App\Services\Importers\Concerns\ImportsLanguages;
use App\Services\Matching\ItemMatchingService;
use App\Services\Parsers\BackgroundXmlParser;

class BackgroundImporter extends BaseImporter
{
    use ImportsLanguages;

    protected function importEntity(array $data): Background
    {
        // 1. Upsert background using slug as unique key
        $background = Background::updateOrCreate(
            ['slug' => $this->generateSlug($data['name'])],
            ['name' => $data['name']]
        );

        // 2. Clear existing polymorphic relationships
        // Note: Deleting traits will cascade delete their random tables
        // Sources are cleared by ImportsSources trait
        $background->traits()->delete();
        $background->proficiencies()->delete();
        $background->languages()->delete();
        $background->equipment()->delete();

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

        // 5. Import proficiencies (now with is_choice, quantity, and subcategory support)
        foreach ($data['proficiencies'] as $profData) {
            $background->proficiencies()->create([
                'proficiency_name' => $profData['proficiency_name'],
                'proficiency_type' => $profData['proficiency_type'],
                'proficiency_subcategory' => $profData['proficiency_subcategory'] ?? null,
                'proficiency_type_id' => $profData['proficiency_type_id'] ?? null,
                'skill_id' => $profData['skill_id'] ?? null,
                'grants' => $profData['grants'] ?? true,
                'is_choice' => $profData['is_choice'] ?? false,
                'quantity' => $profData['quantity'] ?? 1,
            ]);
        }

        // 6. Import sources using trait
        $this->importEntitySources($background, $data['sources']);

        // 7. Import languages
        $this->importEntityLanguages($background, $data['languages'] ?? []);

        // 8. Import equipment (with item matching)
        $itemMatcher = new ItemMatchingService;
        foreach ($data['equipment'] ?? [] as $equipData) {
            $itemId = $equipData['item_id'] ?? null;
            $itemName = $equipData['item_name'] ?? null;
            $description = null;
            $isChoice = $equipData['is_choice'] ?? false;

            // For choices, store full context in description and DON'T match to specific item
            if ($isChoice && $itemName !== null) {
                $description = $itemName;
                $itemId = null; // Choices should NOT have item_id
            } elseif ($itemId === null && $itemName !== null) {
                // Only match non-choice items
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
                'is_choice' => $isChoice,
                'choice_description' => $equipData['choice_description'] ?? null,
                'proficiency_subcategory' => $equipData['proficiency_subcategory'] ?? null,
                'description' => $description,
            ]);
        }

        // 9. Import ALL embedded random tables (linked to traits, NOT background)
        foreach ($data['random_tables'] ?? [] as $tableData) {
            // Find the trait this table belongs to
            if (! isset($tableData['trait_name']) || ! isset($traits[$tableData['trait_name']])) {
                // Skip tables that don't have a trait association
                continue;
            }

            $trait = $traits[$tableData['trait_name']];

            // Create table linked to the TRAIT (not background)
            $table = RandomTable::create([
                'reference_type' => CharacterTrait::class,
                'reference_id' => $trait->id,
                'table_name' => $tableData['name'],
                'dice_type' => $tableData['dice_type'],
            ]);

            foreach ($tableData['entries'] as $index => $entry) {
                RandomTableEntry::create([
                    'random_table_id' => $table->id,
                    'roll_min' => $entry['roll_min'],
                    'roll_max' => $entry['roll_max'],
                    'result_text' => $entry['result_text'],
                    'sort_order' => $index,
                ]);
            }

            // Link table back to trait via random_table_id
            $trait->update(['random_table_id' => $table->id]);
        }

        // Refresh to load all relationships created during import
        $background->refresh();

        return $background;
    }

    public function getParser(): object
    {
        return new BackgroundXmlParser;
    }
}
