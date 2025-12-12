<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreatureTypeResource;
use App\Models\CreatureType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CreatureTypeController extends Controller
{
    /**
     * List all D&D 5e creature types
     *
     * Returns all creature types used to classify monsters in D&D 5th Edition.
     * Includes immunity flags and sustenance requirements typical for each type.
     *
     * **Examples:**
     * ```
     * GET /api/v1/lookups/creature-types    # All 15 creature types
     * ```
     *
     * **D&D 5e Creature Types (15):**
     * - Aberration, Beast, Celestial, Construct, Dragon
     * - Elemental, Fey, Fiend, Giant, Humanoid
     * - Monstrosity, Ooze, Plant, Swarm, Undead
     *
     * **Response Fields:**
     * - `typically_immune_to_*`: Common immunities for this creature type
     * - `requires_sustenance`: Whether creatures of this type need food/water
     * - `requires_sleep`: Whether creatures of this type need rest
     *
     * **Use Cases:**
     * - Monster filtering: "Show all undead monsters"
     * - Rules reference: "What immunities do constructs typically have?"
     * - Character creation: "What creature types can I play as?"
     */
    public function index(): AnonymousResourceCollection
    {
        return CreatureTypeResource::collection(
            CreatureType::orderBy('name')->get()
        );
    }
}
