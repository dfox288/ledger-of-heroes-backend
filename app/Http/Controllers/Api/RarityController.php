<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Item rarity lookup endpoint.
 *
 * Returns distinct item rarities derived from the items table
 * for populating filter dropdowns in the frontend.
 */
class RarityController extends Controller
{
    /**
     * List all item rarities
     *
     * Returns distinct rarities from the items table. These represent the relative
     * power and availability of magic items in D&D 5e.
     *
     * **Examples:**
     * - `GET /api/v1/lookups/rarities` - All rarities
     *
     * **Standard D&D 5e Rarities (in order):**
     * - Common (minor magic items, readily available)
     * - Uncommon (low-level adventurer items)
     * - Rare (mid-level items, require attunement often)
     * - Very Rare (powerful items, limited availability)
     * - Legendary (extremely powerful, unique items)
     * - Artifact (world-changing items, plot devices)
     *
     * **Use Cases:**
     * - Magic item shop filtering
     * - Treasure hoard generation
     * - Character progression planning
     */
    public function index(): JsonResponse
    {
        // Define the canonical order for rarities
        $rarityOrder = [
            'Common' => 1,
            'Uncommon' => 2,
            'Rare' => 3,
            'Very Rare' => 4,
            'Legendary' => 5,
            'Artifact' => 6,
        ];

        $rarities = Item::query()
            ->whereNotNull('rarity')
            ->where('rarity', '!=', '')
            ->distinct()
            ->pluck('rarity')
            ->map(fn ($rarity) => [
                'slug' => Str::slug($rarity),
                'name' => $rarity,
                'sort_order' => $rarityOrder[$rarity] ?? 99,
            ])
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($item) => [
                'slug' => $item['slug'],
                'name' => $item['name'],
            ]);

        return response()->json(['data' => $rarities]);
    }
}
