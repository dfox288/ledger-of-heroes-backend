<?php

namespace App\Http\Controllers\Api;

use App\Enums\OptionalFeatureType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

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
     * Returns all D&D 5e optional feature types from the OptionalFeatureType enum.
     * These represent different kinds of class-specific choices available to characters.
     *
     * **Examples:**
     * - `GET /api/v1/lookups/optional-feature-types` - All feature types
     *
     * **Standard D&D 5e Optional Feature Types:**
     * - Eldritch Invocation (Warlock)
     * - Elemental Discipline (Monk - Way of the Four Elements)
     * - Maneuver (Fighter - Battle Master)
     * - Metamagic (Sorcerer)
     * - Fighting Style (Fighter, Paladin, Ranger)
     * - Artificer Infusion (Artificer)
     * - Rune (Fighter - Rune Knight)
     * - Arcane Shot (Fighter - Arcane Archer)
     *
     * **Use Cases:**
     * - Character building: Filter optional features by type
     * - Class feature selection: Show available options for a specific class
     * - Frontend dropdowns: Populate filter options
     */
    public function index(): JsonResponse
    {
        $types = collect(OptionalFeatureType::cases())
            ->map(fn (OptionalFeatureType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'default_class' => $type->defaultClassName(),
                'default_subclass' => $type->defaultSubclassName(),
            ])
            ->values();

        return response()->json(['data' => $types]);
    }
}
