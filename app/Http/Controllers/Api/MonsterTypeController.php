<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Monster;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Monster type lookup endpoint.
 *
 * Returns distinct creature types derived from the monsters table
 * for populating filter dropdowns in the frontend.
 */
class MonsterTypeController extends Controller
{
    /**
     * List all monster types
     *
     * Returns distinct creature types from the monsters table. These are the 15 standard
     * D&D 5e creature types used for classification, spells like "Protection from Evil",
     * and ranger favored enemies.
     *
     * **Examples:**
     * - `GET /api/v1/lookups/monster-types` - All creature types
     *
     * **Standard D&D 5e Creature Types:**
     * - Aberration, Beast, Celestial, Construct, Dragon
     * - Elemental, Fey, Fiend, Giant, Humanoid
     * - Monstrosity, Ooze, Plant, Undead
     *
     * **Use Cases:**
     * - Ranger favored enemy selection
     * - Cleric/Paladin spell targeting (Turn Undead, etc.)
     * - Monster encounter filtering
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $types = Monster::query()
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->map(fn ($type) => [
                'slug' => Str::slug($type),
                'name' => $type,
            ])
            ->values();

        return response()->json(['data' => $types]);
    }
}
