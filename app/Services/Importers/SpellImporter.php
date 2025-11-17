<?php

namespace App\Services\Importers;

use App\Models\ClassSpell;
use App\Models\Spell;
use App\Models\SpellSchool;
use App\Models\SourceBook;
use App\Services\Parsers\SpellXmlParser;
use Illuminate\Support\Facades\DB;

class SpellImporter
{
    public function __construct(
        private SpellXmlParser $parser
    ) {}

    public function importFromParsedData(array $data): Spell
    {
        return DB::transaction(function () use ($data) {
            // Get school ID
            $school = SpellSchool::where('code', $data['school_code'])->first();
            if (!$school) {
                throw new \Exception("Unknown spell school: {$data['school_code']}");
            }

            // Get source book ID
            $sourceBook = SourceBook::where('code', $data['source_code'])->first();
            if (!$sourceBook) {
                throw new \Exception("Unknown source book: {$data['source_code']}");
            }

            // Create or update spell
            $spell = Spell::updateOrCreate(
                ['name' => $data['name']],
                [
                    'level' => $data['level'],
                    'school_id' => $school->id,
                    'is_ritual' => $data['is_ritual'],
                    'casting_time' => $data['casting_time'],
                    'range' => $data['range'],
                    'duration' => $data['duration'],
                    'has_verbal_component' => $data['has_verbal_component'],
                    'has_somatic_component' => $data['has_somatic_component'],
                    'has_material_component' => $data['has_material_component'],
                    'material_description' => $data['material_description'] ?? null,
                    'material_cost_gp' => $data['material_cost_gp'] ?? null,
                    'material_consumed' => $data['material_consumed'] ?? false,
                    'description' => $data['description'],
                    'source_book_id' => $sourceBook->id,
                    'source_page' => $data['source_page'],
                ]
            );

            // Clear existing class associations
            ClassSpell::where('spell_id', $spell->id)->delete();

            // Create new class associations
            foreach ($data['classes'] as $classData) {
                ClassSpell::create([
                    'spell_id' => $spell->id,
                    'class_name' => $classData['class_name'],
                    'subclass_name' => $classData['subclass_name'],
                ]);
            }

            return $spell->fresh(['classes']);
        });
    }

    public function importFromXmlFile(string $filePath): int
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        $xml = simplexml_load_file($filePath);
        $count = 0;

        foreach ($xml->spell as $spellElement) {
            $data = $this->parser->parseSpellElement($spellElement);
            $this->importFromParsedData($data);
            $count++;
        }

        return $count;
    }
}
