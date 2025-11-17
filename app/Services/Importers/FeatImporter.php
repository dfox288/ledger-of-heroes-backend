<?php

namespace App\Services\Importers;

use App\Models\Feat;
use App\Models\SourceBook;
use App\Services\Parsers\FeatXmlParser;
use Illuminate\Support\Facades\DB;

class FeatImporter
{
    public function __construct(
        private FeatXmlParser $parser
    ) {}

    public function importFromParsedData(array $data): Feat
    {
        return DB::transaction(function () use ($data) {
            $sourceBook = SourceBook::where('code', $data['source_code'])->first();
            if (!$sourceBook) {
                throw new \Exception("Unknown source book: {$data['source_code']}");
            }

            $feat = Feat::updateOrCreate(
                ['name' => $data['name']],
                [
                    'description' => $data['description'],
                    'source_book_id' => $sourceBook->id,
                    'source_page' => $data['source_page'] ?? null,
                ]
            );

            $feat->modifiers()->delete();

            foreach ($data['modifiers'] as $modifierData) {
                $feat->modifiers()->create($modifierData);
            }

            return $feat->fresh(['modifiers']);
        });
    }

    public function importFromXmlFile(string $filePath): int
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        $xml = simplexml_load_file($filePath);
        $count = 0;

        foreach ($xml->feat as $featElement) {
            $data = $this->parser->parseFeatElement($featElement);
            $this->importFromParsedData($data);
            $count++;
        }

        return $count;
    }
}
