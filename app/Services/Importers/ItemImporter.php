<?php

namespace App\Services\Importers;

use App\Models\Item;
use App\Models\ItemType;
use App\Models\ItemRarity;
use App\Models\SourceBook;
use App\Services\Parsers\ItemXmlParser;
use Illuminate\Support\Facades\DB;

class ItemImporter
{
    public function __construct(
        private ItemXmlParser $parser
    ) {}

    public function importFromParsedData(array $data): Item
    {
        return DB::transaction(function () use ($data) {
            // Get item type ID
            $itemType = ItemType::where('code', $data['type_code'])->first();
            if (!$itemType) {
                throw new \Exception("Unknown item type: {$data['type_code']}");
            }

            // Get item rarity ID (nullable)
            $rarity = null;
            if (!empty($data['rarity_code'])) {
                $rarity = ItemRarity::where('code', $data['rarity_code'])->first();
            }

            // Get source book ID
            $sourceBook = SourceBook::where('code', $data['source_code'])->first();
            if (!$sourceBook) {
                throw new \Exception("Unknown source book: {$data['source_code']}");
            }

            // Create or update item
            $item = Item::updateOrCreate(
                ['name' => $data['name']],
                [
                    'item_type_id' => $itemType->id,
                    'rarity_id' => $rarity?->id,
                    'weight_lbs' => $data['weight_lbs'] ?? null,
                    'value_gp' => $data['value_gp'] ?? null,
                    'description' => $data['description'],
                    'source_book_id' => $sourceBook->id,
                    'source_page' => $data['source_page'] ?? null,
                ]
            );

            return $item->fresh();
        });
    }

    public function importFromXmlFile(string $filePath): int
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        $xml = simplexml_load_file($filePath);
        $count = 0;

        foreach ($xml->item as $itemElement) {
            $data = $this->parser->parseItemElement($itemElement);
            $this->importFromParsedData($data);
            $count++;
        }

        return $count;
    }
}
