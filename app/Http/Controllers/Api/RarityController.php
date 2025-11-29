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
     * power and availability of magic items in D&D 5e, ordered from Common to Artifact.
     *
     * **Magic Item Rarity Scale (D&D 5e Official):**
     * - **Common** - Minor conveniences, cost 50-100 gp, no attunement
     * - **Uncommon** - Low-level utility, cost 101-500 gp, optional attunement
     * - **Rare** - Mid-level power, cost 501-5,000 gp, often requires attunement (levels 5-10)
     * - **Very Rare** - High-level abilities, cost 5,001-50,000 gp, usually requires attunement (levels 11-16)
     * - **Legendary** - Powerful artifacts, cost 50,001+ gp, requires attunement (levels 17+)
     * - **Artifact** - World-defining items, priceless, major campaign implications
     *
     * **Common Examples:**
     * ```
     * GET /api/v1/lookups/rarities              # All rarities (6 standard D&D rarities)
     * ```
     *
     * **Character Level Guidelines:**
     * - Level 1-4: Common, Uncommon items
     * - Level 5-10: Uncommon, Rare items
     * - Level 11-16: Rare, Very Rare items
     * - Level 17+: Legendary, Artifact items
     *
     * **Use Cases:**
     * - Magic item shop filtering by character power level
     * - Treasure hoard generation following DMG guidelines
     * - Campaign loot planning and balance
     * - Item rarity-based access control
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
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
