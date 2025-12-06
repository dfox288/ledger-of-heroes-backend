<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FeatureSelection\StoreFeatureSelectionRequest;
use App\Http\Resources\ChoicesResource;
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
     *
     * @response AnonymousResourceCollection<FeatureSelectionResource>
     */
    public function index(Character $character): AnonymousResourceCollection
    {
        $featureSelections = $character->featureSelections()
            ->with(['optionalFeature', 'characterClass'])
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
     *
     * @response AnonymousResourceCollection<OptionalFeatureResource>
     */
    #[QueryParameter('feature_type', description: 'Filter by feature type', example: 'maneuver')]
    public function available(Character $character): AnonymousResourceCollection
    {
        // Get character's class and subclass info
        $characterClasses = $character->characterClasses()
            ->with(['characterClass', 'subclass'])
            ->get();

        // Get already selected feature IDs
        $selectedIds = $character->featureSelections()->pluck('optional_feature_id');

        // Build query for available features
        $query = OptionalFeature::with(['classes', 'classPivots', 'sources.source'])
            ->whereNotIn('id', $selectedIds);

        // Filter by classes character has
        $classIds = $characterClasses->pluck('class_id')->toArray();
        $subclassNames = $characterClasses->pluck('subclass.name')->filter()->toArray();

        $query->where(function ($q) use ($classIds, $subclassNames) {
            // Features available to the character's base classes
            $q->whereHas('classes', function ($classQuery) use ($classIds) {
                $classQuery->whereIn('classes.id', $classIds);
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
     * Get pending feature selection choices
     *
     * Shows which feature types the character can still select choices for,
     * based on their class counter values. This helps track if a character
     * has remaining maneuvers, invocations, metamagic, etc. to choose.
     *
     * @x-flow gameplay-level-up
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/feature-selection-choices
     * ```
     *
     * **Counter to Feature Type Mapping:**
     * | Counter Name | Feature Type |
     * |--------------|--------------|
     * | Maneuvers Known | `maneuver` |
     * | Metamagic Known | `metamagic` |
     * | Infusions Known | `artificer_infusion` |
     * | Fighting Styles Known | `fighting_style` |
     * | Runes Known | `rune` |
     * | Arcane Shots Known | `arcane_shot` |
     * | Elemental Disciplines Known | `elemental_discipline` |
     * | Eldritch Invocations Known | `eldritch_invocation` |
     *
     * **Response Structure:**
     * Returns an array of choice slots, each containing:
     * - `feature_type` - The type of feature (e.g., "maneuver")
     * - `class_name` - Which class grants these choices
     * - `subclass_name` - Subclass if applicable (null otherwise)
     * - `allowed` - Total choices available at current level
     * - `selected` - Number already chosen
     * - `remaining` - Choices still available (allowed - selected)
     *
     * **Use Case:**
     * A level 3 Battle Master Fighter gets 3 maneuvers. If they've selected 2,
     * this endpoint shows: `{"feature_type": "maneuver", "allowed": 3, "selected": 2, "remaining": 1}`
     */
    public function choices(Character $character): ChoicesResource
    {
        $choices = $this->calculatePendingChoices($character);

        return new ChoicesResource($choices);
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
     * {"optional_feature_id": 123, "class_id": 5, "subclass_name": "Battle Master"}
     *
     * # Select a Warlock eldritch invocation (class/subclass optional if unambiguous)
     * {"optional_feature_id": 456}
     *
     * # Select with specific level acquired
     * {"optional_feature_id": 789, "level_acquired": 3}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `optional_feature_id` | integer | Yes | ID of the optional feature to select |
     * | `class_id` | integer | No | ID of the class granting this feature |
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
     *
     * @response 201 FeatureSelectionResource
     * @response 404 array{message: string} Feature not found
     * @response 422 array{message: string, errors: array{optional_feature_id?: string[], class_id?: string[], subclass_name?: string[], level_acquired?: string[]}}
     */
    public function store(StoreFeatureSelectionRequest $request, Character $character): JsonResponse
    {
        $validated = $request->validated();

        // Get the optional feature to access its properties
        $optionalFeature = OptionalFeature::findOrFail($validated['optional_feature_id']);

        // Set default level_acquired to character's current total level
        $levelAcquired = $validated['level_acquired'] ?? $character->total_level;

        $featureSelection = FeatureSelection::create([
            'character_id' => $character->id,
            'optional_feature_id' => $optionalFeature->id,
            'class_id' => $validated['class_id'] ?? null,
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
     *
     * @response 204 No content on success
     * @response 404 array{message: string} Character does not have this feature selected or feature not found
     */
    public function destroy(Character $character, string $featureIdOrSlug): Response
    {
        $optionalFeature = is_numeric($featureIdOrSlug)
            ? OptionalFeature::findOrFail($featureIdOrSlug)
            : OptionalFeature::where('slug', $featureIdOrSlug)->firstOrFail();

        $deleted = $character->featureSelections()
            ->where('optional_feature_id', $optionalFeature->id)
            ->delete();

        if ($deleted === 0) {
            abort(404, 'Character does not have this feature selected.');
        }

        return response()->noContent();
    }

    /**
     * Calculate pending feature selection choices for a character.
     *
     * Compares class counter values (e.g., "Maneuvers Known") against
     * selected features to determine remaining choices.
     *
     * @return array<int, array{feature_type: string, class_name: string, subclass_name: ?string, allowed: int, selected: int, remaining: int}>
     */
    private function calculatePendingChoices(Character $character): array
    {
        $choices = [];

        // Map counter names to feature types
        $counterToFeatureType = [
            'Maneuvers Known' => 'maneuver',
            'Metamagic Known' => 'metamagic',
            'Infusions Known' => 'artificer_infusion',
            'Fighting Styles Known' => 'fighting_style',
            'Runes Known' => 'rune',
            'Arcane Shots Known' => 'arcane_shot',
            'Elemental Disciplines Known' => 'elemental_discipline',
            'Eldritch Invocations Known' => 'eldritch_invocation',
        ];

        // Get character's classes with their counters
        $characterClasses = $character->characterClasses()
            ->with(['characterClass.counters', 'subclass.counters'])
            ->get();

        foreach ($characterClasses as $charClass) {
            $classLevel = $charClass->level;

            // Check base class counters
            $this->processClassCounters(
                $charClass->characterClass->counters ?? collect(),
                $classLevel,
                $counterToFeatureType,
                $charClass->characterClass->name,
                null,
                $choices
            );

            // Check subclass counters
            if ($charClass->subclass) {
                $this->processClassCounters(
                    $charClass->subclass->counters ?? collect(),
                    $classLevel,
                    $counterToFeatureType,
                    $charClass->characterClass->name,
                    $charClass->subclass->name,
                    $choices
                );
            }
        }

        // Count selected features by type
        // Filter out orphaned records where optionalFeature was deleted
        $selectedCounts = $character->featureSelections()
            ->with('optionalFeature')
            ->get()
            ->filter(fn ($fs) => $fs->optionalFeature !== null)
            ->groupBy(fn ($fs) => $fs->optionalFeature->feature_type?->value)
            ->map(fn ($group) => $group->count());

        // Calculate remaining choices
        foreach ($choices as &$choice) {
            $selected = $selectedCounts->get($choice['feature_type'], 0);
            $choice['selected'] = $selected;
            $choice['remaining'] = max(0, $choice['allowed'] - $selected);
        }

        return array_values($choices);
    }

    /**
     * Process counters from a class/subclass to find choice allowances.
     */
    private function processClassCounters(
        $counters,
        int $classLevel,
        array $counterToFeatureType,
        string $className,
        ?string $subclassName,
        array &$choices
    ): void {
        foreach ($counterToFeatureType as $counterName => $featureType) {
            // Find the highest counter value at or below the character's level
            $relevantCounters = $counters
                ->filter(fn ($c) => $c->counter_name === $counterName && $c->level <= $classLevel)
                ->sortByDesc('level');

            if ($relevantCounters->isEmpty()) {
                continue;
            }

            $maxCounter = $relevantCounters->first();
            $key = "{$className}:{$subclassName}:{$featureType}";

            $choices[$key] = [
                'feature_type' => $featureType,
                'class_name' => $className,
                'subclass_name' => $subclassName,
                'allowed' => $maxCounter->counter_value,
                'selected' => 0,
                'remaining' => 0,
            ];
        }
    }
}
