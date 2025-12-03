<?php

namespace App\Services\Importers\Concerns;

use App\Models\EntityItem;
use App\Models\Item;
use App\Services\Parsers\Concerns\ParsesPackContents;
use Illuminate\Support\Facades\Log;

/**
 * Imports pack contents by parsing item descriptions and linking to existing Item records.
 *
 * Used by ItemImporter to link equipment packs (Explorer's Pack, Burglar's Pack, etc.)
 * to their contained items after all items have been imported.
 */
trait ImportsPackContents
{
    use ParsesPackContents;

    /**
     * Item name mapping for special cases where parsed names don't match DB entries.
     *
     * Keys: normalized names from parser
     * Values: exact names in database
     */
    private static array $itemNameMap = [
        'backpack' => 'Backpack',
        'bedroll' => 'Bedroll',
        'torch' => 'Torch',
        'tinderbox' => 'Tinderbox',
        'waterskin' => 'Waterskin',
        'crowbar' => 'Crowbar',
        'hammer' => 'Hammer',
        'piton' => 'Piton',
        'bell' => 'Bell',
        'candle' => 'Candle',
        'chest' => 'Chest',
        'lamp' => 'Lamp',
        'blanket' => 'Blanket',
        'soap' => 'Soap',
        'book' => 'Book',
        'mess kit' => 'Mess Kit',
        'disguise kit' => 'Disguise Kit',
        'hooded lantern' => 'Hooded Lantern',
        'ink pen' => 'Ink Pen',
        'sealing wax' => 'Sealing Wax',
        'fine clothes' => 'Fine Clothes',
        'costume clothes' => 'Costume Clothes',
        'rations (1 day)' => 'Rations (1 day)',
        'hempen rope (50 feet)' => 'Hempen Rope (50 feet)',
        'oil (flask)' => 'Oil (flask)',
        'ink (1-ounce bottle)' => 'Ink (1-ounce bottle)',
        'paper (one sheet)' => 'Paper (one sheet)',
        'parchment (one sheet)' => 'Parchment (one sheet)',
        'perfume (vial)' => 'Perfume (vial)',
        'ball bearings (bag of 1,000)' => 'Ball Bearings (bag of 1,000)',
        'string (10 feet)' => 'String (10 feet)',
        'map or scroll case' => 'Map or Scroll Case',
    ];

    /**
     * Import pack contents for an item.
     *
     * Parses the item's description for "Includes:" section and links
     * to matching Item records in the database.
     */
    public function importPackContents(Item $pack): void
    {
        $description = $pack->description ?? '';

        // Parse contents from description
        $parsedContents = $this->parsePackContents($description);

        if (empty($parsedContents)) {
            return; // Not a pack or no contents found
        }

        // Clear existing pack contents (idempotent import)
        EntityItem::where('reference_type', Item::class)
            ->where('reference_id', $pack->id)
            ->delete();

        // Link parsed contents to items
        foreach ($parsedContents as $content) {
            $item = $this->findItemByNormalizedName($content['name']);

            if ($item === null) {
                Log::warning("Pack content item not found: '{$content['name']}' (pack: {$pack->name})");

                continue;
            }

            EntityItem::create([
                'reference_type' => Item::class,
                'reference_id' => $pack->id,
                'item_id' => $item->id,
                'quantity' => $content['quantity'],
            ]);

            Log::debug("Linked pack content: {$pack->name} -> {$item->name} (qty: {$content['quantity']})");
        }
    }

    /**
     * Find an item by its normalized name.
     *
     * Uses the name map for known items, falls back to case-insensitive DB search.
     */
    private function findItemByNormalizedName(string $normalizedName): ?Item
    {
        // Try name map first
        if (isset(self::$itemNameMap[$normalizedName])) {
            $mappedName = self::$itemNameMap[$normalizedName];

            return Item::where('name', $mappedName)->first();
        }

        // Try exact case-insensitive match
        $item = Item::whereRaw('LOWER(name) = ?', [$normalizedName])->first();

        if ($item) {
            return $item;
        }

        // Try with first letter capitalized
        $capitalizedName = ucwords($normalizedName);
        $item = Item::where('name', $capitalizedName)->first();

        if ($item) {
            return $item;
        }

        return null;
    }

    /**
     * Import pack contents for all equipment packs.
     *
     * Should be called after all items have been imported.
     */
    public function importAllPackContents(): int
    {
        $packNames = [
            "Burglar's Pack",
            "Diplomat's Pack",
            "Dungeoneer's Pack",
            "Entertainer's Pack",
            "Explorer's Pack",
            "Priest's Pack",
            "Scholar's Pack",
        ];

        $imported = 0;

        foreach ($packNames as $packName) {
            $pack = Item::where('name', $packName)->first();

            if ($pack) {
                $this->importPackContents($pack);
                $imported++;
            } else {
                Log::warning("Pack not found: {$packName}");
            }
        }

        return $imported;
    }
}
