<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Character\Choice\StoreFeatureSelectionRequest;
use App\Http\Resources\FeatureSelectionResource;
use App\Http\Resources\OptionalFeatureResource;
use App\Models\Character;
use App\Models\FeatureSelection;
use App\Models\OptionalFeature;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class FeatureSelectionController extends Controller
{
    /**
     * List all feature selections for the character
     *
     * Returns all feature selections the character has made, including eldritch invocations,
     * maneuvers, metamagic options, fighting styles, and other class-granted choices.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/feature-selections
     * ```
     *
     * **Feature Selection Types (8 total):**
     * | Type | Label | Primary Class | Subclass |
     * |------|-------|---------------|----------|
     * | `eldritch_invocation` | Eldritch Invocation | Warlock | - |
     * | `elemental_discipline` | Elemental Discipline | Monk | Way of the Four Elements |
     * | `maneuver` | Maneuver | Fighter | Battle Master |
     * | `metamagic` | Metamagic | Sorcerer | - |
     * | `fighting_style` | Fighting Style | Multiple | - |
     * | `artificer_infusion` | Artificer Infusion | Artificer | - |
     * | `rune` | Rune | Fighter | Rune Knight |
     * | `arcane_shot` | Arcane Shot | Fighter | Arcane Archer |
     *
     * **Response includes:**
     * - Full optional feature details (name, description, type)
     * - Class/subclass association
     * - Level the feature was acquired
     */
    public function index(Character $character): AnonymousResourceCollection
    {
        $featureSelections = $character->featureSelections()
            ->with(['optionalFeature', 'characterClass'])
            ->orderBy('id')
            ->get();

        return FeatureSelectionResource::collection($featureSelections);
    }

    /**
     * List available feature selections for the character
     *
     * Returns features the character is eligible for based on their class,
     * subclass, and level. Excludes features already selected.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/available-feature-selections
     * GET /api/v1/characters/1/available-feature-selections?feature_type=maneuver
     * GET /api/v1/characters/1/available-feature-selections?feature_type=eldritch_invocation
     * GET /api/v1/characters/1/available-feature-selections?feature_type=metamagic
     * ```
     *
     * **Filtering by feature_type:**
     * Use the `feature_type` query parameter to filter by type:
     * - `eldritch_invocation` - Warlock invocations
     * - `elemental_discipline` - Monk (Way of Four Elements) disciplines
     * - `maneuver` - Fighter (Battle Master) maneuvers
     * - `metamagic` - Sorcerer metamagic options
     * - `fighting_style` - Fighting styles (Fighter, Paladin, Ranger, etc.)
     * - `artificer_infusion` - Artificer infusions
     * - `rune` - Fighter (Rune Knight) runes
     * - `arcane_shot` - Fighter (Arcane Archer) shots
     *
     * **Eligibility Rules:**
     * - Feature's required class must match character's class
     * - Feature's subclass requirement (if any) must match character's subclass
     * - Feature's level requirement must be <= character's total level
     * - Feature must not already be selected by this character
     */
    #[QueryParameter('feature_type', description: 'Filter by feature type', example: 'maneuver')]
    public function available(Character $character): AnonymousResourceCollection
    {
        // Get character's class and subclass info
        $characterClasses = $character->characterClasses()
            ->with(['characterClass', 'subclass'])
            ->get();

        // Get already selected feature slugs
        $selectedSlugs = $character->featureSelections()->pluck('optional_feature_slug');

        // Build query for available features
        $query = OptionalFeature::with(['classes', 'classPivots', 'sources.source'])
            ->whereNotIn('full_slug', $selectedSlugs);

        // Filter by classes character has
        $classSlugs = $characterClasses->pluck('class_slug')->toArray();
        $subclassNames = $characterClasses->pluck('subclass.name')->filter()->toArray();

        $query->where(function ($q) use ($classSlugs, $subclassNames) {
            // Features available to the character's base classes
            $q->whereHas('classes', function ($classQuery) use ($classSlugs) {
                $classQuery->whereIn('classes.full_slug', $classSlugs);
            });

            // Or features for specific subclasses the character has
            if (! empty($subclassNames)) {
                $q->orWhereHas('classPivots', function ($pivotQuery) use ($subclassNames) {
                    $pivotQuery->whereIn('subclass_name', $subclassNames);
                });
            }
        });

        // Filter by level (features with level_requirement <= character total level)
        $totalLevel = $character->total_level;
        $query->where(function ($q) use ($totalLevel) {
            $q->whereNull('level_requirement')
                ->orWhere('level_requirement', '<=', $totalLevel);
        });

        // Optional: filter by feature type
        if (request()->has('feature_type')) {
            $query->where('feature_type', request('feature_type'));
        }

        $features = $query->orderBy('name')->get();

        return OptionalFeatureResource::collection($features);
    }

    /**
     * Add a feature selection
     *
     * Adds an invocation, maneuver, metamagic, fighting style, etc. to the character.
     * Validates class eligibility and level requirements automatically.
     *
     * @x-flow gameplay-level-up
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/feature-selections
     *
     * # Select a Battle Master maneuver
     * {"optional_feature_slug": "phb:trip-attack", "class_slug": "phb:fighter", "subclass_name": "Battle Master"}
     *
     * # Select a Warlock eldritch invocation (class/subclass optional if unambiguous)
     * {"optional_feature_slug": "phb:agonizing-blast"}
     *
     * # Select with specific level acquired
     * {"optional_feature_slug": "phb:riposte", "level_acquired": 3}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `optional_feature_slug` | string | Yes | Full slug of the optional feature to select |
     * | `class_slug` | string | No | Full slug of the class granting this feature |
     * | `subclass_name` | string | No | Name of the subclass (max 100 chars) |
     * | `level_acquired` | integer | No | Level when acquired (1-20, defaults to current total level) |
     *
     * **Validation:**
     * - Feature must exist
     * - Character cannot already have this feature (no duplicates)
     * - Character must have a class eligible for this feature
     * - Character's level must meet feature's level_requirement
     *
     * **Error Responses:**
     * - 422 if feature already selected: "This character has already selected this feature."
     * - 422 if level too low: "This feature requires level {N}. Character is level {M}."
     * - 422 if class ineligible: "This character does not have the required class or subclass for this feature."
     */
    public function store(StoreFeatureSelectionRequest $request, Character $character): JsonResponse
    {
        $validated = $request->validated();

        // Get the optional feature to access its properties
        $optionalFeature = OptionalFeature::where('full_slug', $validated['optional_feature_slug'])->firstOrFail();

        // Set default level_acquired to character's current total level
        $levelAcquired = $validated['level_acquired'] ?? $character->total_level;

        $featureSelection = FeatureSelection::create([
            'character_id' => $character->id,
            'optional_feature_slug' => $optionalFeature->full_slug,
            'class_slug' => $validated['class_slug'] ?? null,
            'subclass_name' => $validated['subclass_name'] ?? null,
            'level_acquired' => max(1, $levelAcquired),
        ]);

        $featureSelection->load(['optionalFeature', 'characterClass']);

        return (new FeatureSelectionResource($featureSelection))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Remove a feature selection from the character
     *
     * Used for retraining features (allowed by some class rules, typically at level-up).
     * For example, Battle Masters can swap one maneuver for another when they gain a level.
     * Accepts either optional feature ID or slug.
     *
     * **Examples:**
     * ```
     * DELETE /api/v1/characters/1/feature-selections/123                  # Remove by ID
     * DELETE /api/v1/characters/1/feature-selections/agonizing-blast      # Remove by slug
     * ```
     *
     * **Retraining Rules (by type):**
     * - **Maneuvers** - Battle Masters can replace 1 maneuver at each Fighter level-up
     * - **Eldritch Invocations** - Warlocks can replace 1 invocation at each Warlock level-up
     * - **Metamagic** - Sorcerers can swap 1 option when gaining Sorcerer levels (at certain levels)
     * - **Fighting Styles** - Generally cannot be retrained (feature swaps via multiclass)
     *
     * **Note:** This API allows any removal for flexibility. Implement retraining rules in your UI.
     *
     * @param  Character  $character  The character
     * @param  string  $featureIdOrSlug  ID or slug of the optional feature to remove
     * @return Response 204 on success
     */
    public function destroy(Character $character, string $featureIdOrSlug): Response
    {
        $optionalFeature = is_numeric($featureIdOrSlug)
            ? OptionalFeature::findOrFail($featureIdOrSlug)
            : OptionalFeature::where('full_slug', $featureIdOrSlug)->orWhere('slug', $featureIdOrSlug)->firstOrFail();

        $deleted = $character->featureSelections()
            ->where('optional_feature_slug', $optionalFeature->full_slug)
            ->delete();

        if ($deleted === 0) {
            abort(404, 'Character does not have this feature selected.');
        }

        return response()->noContent();
    }
}
