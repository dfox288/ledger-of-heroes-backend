<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Monster;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Alignment lookup endpoint.
 *
 * Returns distinct alignments derived from the monsters table
 * for populating filter dropdowns in the frontend.
 */
class AlignmentController extends Controller
{
    /**
     * List all alignments
     *
     * Returns distinct alignments from the monsters table. These represent the moral and ethical
     * outlook of creatures in D&D 5e across two axes: Law-Chaos (Lawful to Chaotic) and Good-Evil
     * (Good to Evil), creating a 3x3 alignment matrix of 9 standard alignments plus special variants.
     *
     * **Common Examples:**
     * ```
     * GET /api/v1/lookups/alignments                # All alignments
     * GET /api/v1/lookups/alignments?q=good         # Search by keyword
     * ```
     *
     * **Standard D&D 5e Alignments (9-axis system):**
     *
     * **Good Alignments (Primarily benevolent):**
     * - **Lawful Good:** Order and goodness (Paladins, Lawful Good clerics, noble knights)
     * - **Neutral Good:** Goodness without rigid structure (Many clerics, some rangers)
     * - **Chaotic Good:** Goodness with freedom and independence (Rogues, chaotic good monks)
     *
     * **Neutral Alignments (Balance between law-chaos and good-evil):**
     * - **Lawful Neutral:** Order without alignment toward good or evil (Monks, Paladins of justice)
     * - **True Neutral:** Balance between all axes; "I'm just doing my thing" (Druids, some wizards)
     * - **Chaotic Neutral:** Individualism without moral compass (Unpredictable, selfish creatures)
     *
     * **Evil Alignments (Primarily malevolent):**
     * - **Lawful Evil:** Organized, structured malevolence (Devils, tyrants, organized crime)
     * - **Neutral Evil:** Evil without structure or chaos; purely selfish (Liches, some giants)
     * - **Chaotic Evil:** Violent, destructive evil (Demons, marauding humanoids, berserkers)
     *
     * **Special Alignments:**
     * - **Unaligned:** Constructs, oozes, beasts without sapience (golems, oozes, animals)
     * - **Any Alignment:** Humanoids that can be any alignment; player choice dependent (e.g., humanoid cultists)
     * - **Varies:** Individual creatures within the type have different alignments (communities, factions)
     *
     * **Use Cases:**
     * - Detect Evil and Good spell targeting (LG, NG, CG vs LE, NE, CE)
     * - Paladin Divine Sense ability filtering
     * - Monster filtering in encounter builders by moral/ethical profile
     * - Character/NPC roleplay guidance and behavior expectations
     * - Party composition analysis and alignment conflicts
     *
     * **Query Parameters:**
     * - `q` (string): Search by name (partial match, case-insensitive)
     * - `per_page` (int): Results per page, 1-100 (default: 50)
     *
     * @return \Illuminate\Http\JsonResponse JSON response with alignment array [slug, name]
     */
    #[QueryParameter('q', description: 'Search by alignment name (partial match)', example: 'good')]
    #[QueryParameter('per_page', description: 'Results per page, 1-100', example: '50')]
    public function index(): JsonResponse
    {
        $alignments = Monster::query()
            ->whereNotNull('alignment')
            ->where('alignment', '!=', '')
            ->distinct()
            ->orderBy('alignment')
            ->pluck('alignment')
            ->map(fn ($alignment) => [
                'slug' => Str::slug($alignment),
                'name' => $alignment,
            ])
            ->values();

        return response()->json(['data' => $alignments]);
    }
}
