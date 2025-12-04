<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LookupResource;
use App\Models\Monster;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/**
 * Monster type lookup endpoint.
 *
 * Returns distinct creature types from the monsters table for populating
 * filter dropdowns in the frontend and supporting character/encounter building.
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
     * ```
     * GET /api/v1/lookups/monster-types              # All creature types
     * GET /api/v1/lookups/monster-types?q=fiend      # Search by name
     * ```
     *
     * **Standard D&D 5e Creature Types:**
     * - **Classic:** Aberration, Beast, Celestial, Construct, Dragon
     * - **Humanoid:** Humanoid, Giant, Fey, Elemental, Fiend
     * - **Undead & Other:** Monstrosity, Ooze, Plant, Undead
     *
     * **Query Parameters:**
     * - `q` (string): Search by name (partial match)
     * - `per_page` (int): Results per page, 1-100 (default: 50)
     *
     * **Use Cases:**
     * - Ranger favored enemy selection (Favored Enemy class feature)
     * - Cleric/Paladin spell targeting (Turn Undead, Protection from Evil, etc.)
     * - Monster encounter filtering and encounter building
     * - Spell effect verification (many spells target specific creature types)
     */
    #[QueryParameter('q', description: 'Search creature types by name', example: 'fiend')]
    public function index(): AnonymousResourceCollection
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

        return LookupResource::collection($types);
    }
}
