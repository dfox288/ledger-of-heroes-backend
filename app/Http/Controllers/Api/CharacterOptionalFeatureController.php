<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CharacterOptionalFeature\StoreCharacterOptionalFeatureRequest;
use App\Http\Resources\CharacterOptionalFeatureResource;
use App\Http\Resources\OptionalFeatureResource;
use App\Models\Character;
use App\Models\CharacterOptionalFeature;
use App\Models\OptionalFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CharacterOptionalFeatureController extends Controller
{
    /**
     * List all optional features selected by the character.
     *
     * Returns invocations, maneuvers, metamagic, fighting styles, etc. that
     * the character has chosen.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/optional-features
     * ```
     */
    public function index(Character $character): AnonymousResourceCollection
    {
        $optionalFeatures = $character->optionalFeatures()
            ->with(['optionalFeature', 'characterClass'])
            ->get();

        return CharacterOptionalFeatureResource::collection($optionalFeatures);
    }

    /**
     * List optional features available for the character to select.
     *
     * Returns features the character is eligible for based on their class,
     * subclass, and level. Excludes features already selected.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/available-optional-features
     * GET /api/v1/characters/1/available-optional-features?feature_type=maneuver
     * ```
     */
    public function available(Character $character): AnonymousResourceCollection
    {
        // Get character's class and subclass info
        $characterClasses = $character->characterClasses()
            ->with(['characterClass', 'subclass'])
            ->get();

        // Get already selected feature IDs
        $selectedIds = $character->optionalFeatures()->pluck('optional_feature_id');

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
     * Get pending optional feature choices.
     *
     * Shows which feature types the character can still select choices for,
     * based on their class counter values.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/optional-feature-choices
     * ```
     */
    public function choices(Character $character): JsonResponse
    {
        $choices = $this->calculatePendingChoices($character);

        return response()->json(['data' => $choices]);
    }

    /**
     * Select an optional feature.
     *
     * Adds an invocation, maneuver, metamagic, fighting style, etc. to the character.
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/optional-features {"optional_feature_id": 123}
     * POST /api/v1/characters/1/optional-features {"optional_feature_id": 456, "class_id": 1, "subclass_name": "Battle Master"}
     * ```
     */
    public function store(StoreCharacterOptionalFeatureRequest $request, Character $character): JsonResponse
    {
        $validated = $request->validated();

        // Get the optional feature to access its properties
        $optionalFeature = OptionalFeature::findOrFail($validated['optional_feature_id']);

        // Set default level_acquired to character's current total level
        $levelAcquired = $validated['level_acquired'] ?? $character->total_level;

        $characterOptionalFeature = CharacterOptionalFeature::create([
            'character_id' => $character->id,
            'optional_feature_id' => $optionalFeature->id,
            'class_id' => $validated['class_id'] ?? null,
            'subclass_name' => $validated['subclass_name'] ?? null,
            'level_acquired' => max(1, $levelAcquired),
        ]);

        $characterOptionalFeature->load(['optionalFeature', 'characterClass']);

        return (new CharacterOptionalFeatureResource($characterOptionalFeature))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Remove an optional feature from the character.
     *
     * Used for retraining features (allowed by some class rules).
     *
     * **Examples:**
     * ```
     * DELETE /api/v1/characters/1/optional-features/123
     * ```
     */
    public function destroy(Character $character, int $optionalFeatureId): Response
    {
        $deleted = $character->optionalFeatures()
            ->where('optional_feature_id', $optionalFeatureId)
            ->delete();

        if ($deleted === 0) {
            abort(404, 'Character does not have this optional feature selected.');
        }

        return response()->noContent();
    }

    /**
     * Calculate pending optional feature choices for a character.
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
        $selectedCounts = $character->optionalFeatures()
            ->with('optionalFeature')
            ->get()
            ->groupBy(fn ($cof) => $cof->optionalFeature->feature_type?->value)
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
                ->filter(fn ($c) => $c->name === $counterName && $c->level <= $classLevel)
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
                'allowed' => $maxCounter->value,
                'selected' => 0,
                'remaining' => 0,
            ];
        }
    }
}
