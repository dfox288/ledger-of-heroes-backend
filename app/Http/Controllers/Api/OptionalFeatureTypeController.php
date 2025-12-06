<?php

namespace App\Http\Controllers\Api;

use App\Enums\OptionalFeatureType;
use App\Http\Controllers\Controller;
use App\Http\Resources\OptionalFeatureTypeResource;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Optional feature type lookup endpoint.
 *
 * Returns all OptionalFeatureType enum values with labels
 * for populating filter dropdowns in the frontend.
 */
class OptionalFeatureTypeController extends Controller
{
    /**
     * List all optional feature types
     *
     * Returns all 8 D&D 5e optional feature types. These represent different kinds of class-specific
     * choices available to characters during leveling (e.g., Eldritch Invocations for Warlocks,
     * Fighting Styles for Fighters). Each type is associated with one or more classes.
     *
     * **Examples:**
     * ```
     * GET /api/v1/lookups/optional-feature-types              # All feature types
     * GET /api/v1/lookups/optional-feature-types?q=fighting   # Search by name
     * ```
     *
     * **Optional Feature Types Reference:**
     * - **Eldritch Invocation:** Warlock class features granting supernatural abilities
     * - **Elemental Discipline:** Monk (Way of the Four Elements) subclass features
     * - **Maneuver:** Fighter (Battle Master) subclass features for tactical combat
     * - **Metamagic:** Sorcerer class features to modify spell casting
     * - **Fighting Style:** Multiple classes (Fighter, Paladin, Ranger) combat specialization
     * - **Artificer Infusion:** Artificer class features for magical item creation
     * - **Rune:** Fighter (Rune Knight) subclass features granting magical runes
     * - **Arcane Shot:** Fighter (Arcane Archer) subclass features using magical arrows
     *
     * **Query Parameters:**
     * - `q` (string): Search by name (partial match)
     * - `per_page` (int): Results per page, 1-100 (default: 50)
     *
     * **Use Cases:**
     * - **Character Building:** Filter optional features by type to help players choose features
     * - **Class Feature Selection:** Show available feature types for a specific class
     * - **Frontend Dropdowns:** Populate filter/search dropdowns in UI
     * - **Feature Organization:** Group optional features by their mechanical type
     *
     * @response AnonymousResourceCollection<OptionalFeatureTypeResource>
     */
    #[QueryParameter('q', description: 'Search optional feature types by name', example: 'fighting')]
    public function index(): AnonymousResourceCollection
    {
        $types = collect(OptionalFeatureType::cases());

        return OptionalFeatureTypeResource::collection($types);
    }
}
