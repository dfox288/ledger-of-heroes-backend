<?php

namespace App\Services\Importers;

use App\Models\Background;
use App\Models\CharacterTrait;
use App\Models\EntityItem;
use App\Models\EntityLanguage;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use App\Models\Source;
use App\Services\Matching\ItemMatchingService;
use App\Services\Parsers\ItemTableParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackgroundImporter
{
    public function import(array $data): Background
    {
        return DB::transaction(function () use ($data) {
            // 1. Upsert background using slug as unique key
            $background = Background::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                ['name' => $data['name']]
            );

            // 2. Clear existing polymorphic relationships
            $background->traits()->delete();
            $background->proficiencies()->delete();
            $background->sources()->delete();
            $background->languages()->delete();
            $background->equipment()->delete();
            RandomTable::where('reference_type', Background::class)
                ->where('reference_id', $background->id)
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

                // 4. Import random tables for characteristics trait (old method for backward compatibility)
                if ($traitData['category'] === 'characteristics' && ! empty($traitData['rolls'])) {
                    $this->importRandomTables($trait, $traitData['description'], $traitData['rolls']);
                }
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

            // 6. Import sources
            foreach ($data['sources'] as $sourceData) {
                $source = Source::where('code', $sourceData['code'])->first();

                if ($source) {
                    $background->sources()->create([
                        'source_id' => $source->id,
                        'pages' => $sourceData['pages'],
                    ]);
                }
            }

            // 7. Import languages
            foreach ($data['languages'] ?? [] as $langData) {
                EntityLanguage::create([
                    'reference_type' => Background::class,
                    'reference_id' => $background->id,
                    'language_id' => $langData['language_id'],
                    'is_choice' => $langData['is_choice'] ?? false,
                    'quantity' => $langData['quantity'] ?? 1,
                ]);
            }

            // 8. Import equipment (with item matching)
            $itemMatcher = new ItemMatchingService;
            foreach ($data['equipment'] ?? [] as $equipData) {
                // Attempt to match item by name if item_id not provided
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
                    'is_choice' => $equipData['is_choice'] ?? false,
                    'choice_description' => $equipData['choice_description'] ?? null,
                    'description' => $description,
                ]);
            }

            // 9. Import ALL embedded random tables (not just characteristics)
            foreach ($data['random_tables'] ?? [] as $tableData) {
                $table = RandomTable::create([
                    'reference_type' => Background::class,
                    'reference_id' => $background->id,
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

                // Link table to trait if trait_name is specified
                if (isset($tableData['trait_name']) && isset($traits[$tableData['trait_name']])) {
                    $traits[$tableData['trait_name']]->update(['random_table_id' => $table->id]);
                }
            }

            return $background;
        });
    }

    private function importRandomTables(CharacterTrait $trait, string $text, array $rolls): void
    {
        // Use roll elements to extract tables from the trait description
        // Each roll element has a description and formula (e.g., "Personality Trait", "1d8")

        foreach ($rolls as $rollData) {
            $tableName = $rollData['description'];
            $diceType = $rollData['formula'];

            // Find the table in the text by looking for the pattern "d8 | Personality Trait"
            // or "1d8 | Personality Trait" followed by rows like "1 | ..."
            $pattern = $this->buildTablePattern($diceType, $tableName);

            if (preg_match($pattern, $text, $matches)) {
                // Parse the matched table text
                $parser = new ItemTableParser;
                $parsed = $parser->parse($tableName.":\n".trim($matches[0]));

                $table = $trait->randomTables()->create([
                    'table_name' => $tableName,
                    'dice_type' => $diceType,
                ]);

                foreach ($parsed['rows'] as $index => $row) {
                    $table->entries()->create([
                        'roll_min' => $row['roll_min'],
                        'roll_max' => $row['roll_max'],
                        'result_text' => $row['result_text'],
                        'sort_order' => $index,
                    ]);
                }
            }
        }
    }

    private function buildTablePattern(string $diceType, string $tableName): string
    {
        // Build a regex pattern to match the table
        // Format: "d8 | Personality Trait\n1 | ...\n2 | ..."
        $escapedTableName = preg_quote($tableName, '/');

        // Normalize dice type: "1d8" -> "d8" (the format used in trait text)
        $normalizedDiceType = preg_replace('/^1(d\d+)$/', '$1', $diceType);

        // Match dice notation and table name header, followed by numbered rows
        // Use flexible matching for dice notation (optional leading digit)
        return '/\d*'.preg_quote($normalizedDiceType, '/').'\s*\|\s*'.$escapedTableName.'\s*\n((?:^\d+(?:-\d+)?\s*\|[^\n]+\s*\n?)+)/m';
    }
}
