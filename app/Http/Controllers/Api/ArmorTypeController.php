<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Monster;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Armor type lookup endpoint.
 *
 * Returns distinct armor types derived from the monsters table
 * for populating filter dropdowns in the frontend.
 */
class ArmorTypeController extends Controller
{
    /**
     * List all armor types
     *
     * Returns distinct armor types from the monsters table. These describe what type
     * of armor or natural protection a creature has.
     *
     * **Examples:**
     * - `GET /api/v1/lookups/armor-types` - All armor types
     *
     * **Common Armor Types:**
     * - Natural armor (dragons, beasts)
     * - Plate armor, Chain mail, Leather armor (humanoids)
     * - Scale mail, Ring mail, Studded leather
     * - Shield (often combined with armor)
     *
     * **Use Cases:**
     * - Monster filtering by defense type
     * - Understanding AC calculations
     * - Equipment planning for encounters
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $armorTypes = Monster::query()
            ->whereNotNull('armor_type')
            ->where('armor_type', '!=', '')
            ->distinct()
            ->orderBy('armor_type')
            ->pluck('armor_type')
            ->map(fn ($armorType) => [
                'slug' => Str::slug($armorType),
                'name' => $armorType,
            ])
            ->values();

        return response()->json(['data' => $armorTypes]);
    }
}
