<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Monster;
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
     * Returns distinct alignments from the monsters table. These represent the moral
     * and ethical outlook of creatures in D&D 5e.
     *
     * **Examples:**
     * - `GET /api/v1/lookups/alignments` - All alignments
     *
     * **Standard D&D 5e Alignments (9-axis):**
     * - Lawful Good, Neutral Good, Chaotic Good
     * - Lawful Neutral, True Neutral, Chaotic Neutral
     * - Lawful Evil, Neutral Evil, Chaotic Evil
     *
     * **Special Alignments:**
     * - Unaligned (beasts, constructs, oozes)
     * - Any alignment (humanoids, shapechangers)
     * - Varies (creatures with individual choice)
     *
     * **Use Cases:**
     * - Detect Evil and Good spell targeting
     * - Paladin Divine Sense
     * - Character/NPC roleplay guidance
     *
     * @return JsonResponse
     */
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
