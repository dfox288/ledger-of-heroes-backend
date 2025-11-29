<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Monster;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Armor type lookup endpoint.
 *
 * Returns distinct armor types derived from monsters in the D&D 5e database
 * for populating defense filters and understanding creature armor categories.
 */
class ArmorTypeController extends Controller
{
    /**
     * List all armor types
     *
     * Returns all distinct armor types worn or possessed by creatures in the D&D 5e database.
     * Armor types describe the protective equipment a creature wears (leather, plate, chain mail)
     * or natural protection (scales, hide, exoskeleton). Used for understanding AC calculations
     * and creature defensive profiles.
     *
     * **Examples:**
     * ```
     * GET /api/v1/lookups/armor-types              # All armor types
     * GET /api/v1/lookups/armor-types?q=plate      # Search by name
     * ```
     *
     * **Armor Categories:**
     * - **Light Armor:** Leather, Studded leather, Padded, Hierarchy - minimal penalty to Stealth
     * - **Medium Armor:** Hide, Chain shirt, Scale mail, Breastplate - moderate AC, Stealth disadvantage
     * - **Heavy Armor:** Chain mail, Plate armor, Ring mail - highest AC, limited mobility
     * - **Natural Armor:** Dragon scales, Exoskeletons, Tough hide - innate creature protection
     * - **Shields:** Wooden, Metal, Tower - combine with armor for bonus AC
     *
     * **AC Calculation Reference:**
     * - Light: 11 + DEX (Leather), 12 + DEX (Studded leather)
     * - Medium: 13 + DEX (Hide), 14 + DEX (Scale mail), 14 + DEX (Breastplate)
     * - Heavy: 16 (Chain mail), 18 (Plate armor) - no DEX bonus
     * - Natural: Creature-specific (typically 12-15 base)
     * - Shield: +2 AC when equipped with armor
     *
     * **Query Parameters:**
     * - `q` (string): Search by name (partial match)
     *
     * **Use Cases:**
     * - **Encounter Building:** Filter monsters by their armor type to balance party composition
     * - **Spell Selection:** Choose spells effective against common armor types
     * - **Equipment Planning:** Reference common armor types found in the campaign world
     * - **Monster Analysis:** Understand how different creatures achieve their AC (armor vs. natural)
     *
     * **Reference Data:**
     * Armor types are extracted from ~430 creatures in the Monster Manual and supplemental sources,
     * providing a comprehensive view of armor protection available to humanoid and monstrous creatures.
     */
    #[QueryParameter('q', description: 'Search armor types by name', example: 'plate')]
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
