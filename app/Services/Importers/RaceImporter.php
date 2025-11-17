<?php

namespace App\Services\Importers;

use App\Models\Race;
use App\Models\Size;
use App\Models\SourceBook;
use App\Models\CharacterTrait;
use App\Models\Modifier;
use App\Models\Proficiency;
use App\Services\Parsers\RaceXmlParser;
use Illuminate\Support\Facades\DB;

class RaceImporter
{
    public function __construct(
        private RaceXmlParser $parser
    ) {}

    public function importFromParsedData(array $data): Race
    {
        return DB::transaction(function () use ($data) {
            // Get size ID
            $size = Size::where('code', $data['size_code'])->first();
            if (!$size) {
                throw new \Exception("Unknown size: {$data['size_code']}");
            }

            // Get source book ID
            $sourceBook = SourceBook::where('code', $data['source_code'])->first();
            if (!$sourceBook) {
                throw new \Exception("Unknown source book: {$data['source_code']}");
            }

            // Create or update race
            $race = Race::updateOrCreate(
                ['name' => $data['name']],
                [
                    'size_id' => $size->id,
                    'speed' => $data['speed'],
                    'source_book_id' => $sourceBook->id,
                    'source_page' => $data['source_page'] ?? null,
                ]
            );

            // Clear existing polymorphic relationships
            $race->traits()->delete();
            $race->modifiers()->delete();
            $race->proficiencies()->delete();

            // Create traits
            foreach ($data['traits'] as $traitData) {
                $race->traits()->create($traitData);
            }

            // Create modifiers
            foreach ($data['modifiers'] as $modifierData) {
                $race->modifiers()->create($modifierData);
            }

            // Create proficiencies
            foreach ($data['proficiencies'] as $proficiencyData) {
                $race->proficiencies()->create($proficiencyData);
            }

            return $race->fresh(['traits', 'modifiers', 'proficiencies']);
        });
    }

    public function importFromXmlFile(string $filePath): int
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        $xml = simplexml_load_file($filePath);
        $count = 0;

        foreach ($xml->race as $raceElement) {
            $data = $this->parser->parseRaceElement($raceElement);
            $this->importFromParsedData($data);
            $count++;
        }

        return $count;
    }
}
