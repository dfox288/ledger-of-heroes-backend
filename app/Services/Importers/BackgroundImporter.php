<?php

namespace App\Services\Importers;

use App\Models\Background;
use App\Models\SourceBook;
use App\Services\Parsers\BackgroundXmlParser;
use Illuminate\Support\Facades\DB;

class BackgroundImporter
{
    public function __construct(
        private BackgroundXmlParser $parser
    ) {}

    public function importFromParsedData(array $data): Background
    {
        return DB::transaction(function () use ($data) {
            $sourceBook = SourceBook::where('code', $data['source_code'])->first();
            if (!$sourceBook) {
                throw new \Exception("Unknown source book: {$data['source_code']}");
            }

            $background = Background::updateOrCreate(
                ['name' => $data['name']],
                [
                    'source_book_id' => $sourceBook->id,
                    'source_page' => $data['source_page'] ?? null,
                ]
            );

            $background->traits()->delete();
            $background->proficiencies()->delete();

            foreach ($data['traits'] as $traitData) {
                $background->traits()->create($traitData);
            }

            foreach ($data['proficiencies'] as $proficiencyData) {
                $background->proficiencies()->create($proficiencyData);
            }

            return $background->fresh(['traits', 'proficiencies']);
        });
    }

    public function importFromXmlFile(string $filePath): int
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        $xml = simplexml_load_file($filePath);
        $count = 0;

        foreach ($xml->background as $backgroundElement) {
            $data = $this->parser->parseBackgroundElement($backgroundElement);
            $this->importFromParsedData($data);
            $count++;
        }

        return $count;
    }
}
