<?php

namespace App\Services\Importers;

use App\Models\Background;
use App\Models\CharacterTrait;
use App\Models\Source;
use App\Services\Parsers\ItemTableDetector;
use App\Services\Parsers\ItemTableParser;
use Illuminate\Support\Facades\DB;

class BackgroundImporter
{
    public function import(array $data): Background
    {
        return DB::transaction(function () use ($data) {
            // 1. Upsert background by name
            $background = Background::updateOrCreate(
                ['name' => $data['name']],
                []
            );

            // 2. Clear existing polymorphic relationships
            $background->traits()->delete();
            $background->proficiencies()->delete();
            $background->sources()->delete();

            // 3. Import traits
            foreach ($data['traits'] as $traitData) {
                $trait = $background->traits()->create([
                    'name' => $traitData['name'],
                    'description' => $traitData['description'],
                    'category' => $traitData['category'],
                ]);

                // 4. Import random tables for characteristics trait
                if ($traitData['category'] === 'characteristics' && !empty($traitData['rolls'])) {
                    $this->importRandomTables($trait, $traitData['description'], $traitData['rolls']);
                }
            }

            // 5. Import proficiencies
            foreach ($data['proficiencies'] as $profData) {
                $background->proficiencies()->create($profData);
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
                $parser = new ItemTableParser();
                $parsed = $parser->parse($tableName . ":\n" . trim($matches[0]));

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
        return '/\d*' . preg_quote($normalizedDiceType, '/') . '\s*\|\s*' . $escapedTableName . '\s*\n((?:^\d+(?:-\d+)?\s*\|[^\n]+\s*\n?)+)/m';
    }
}
