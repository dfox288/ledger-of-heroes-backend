<?php

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\OptionalFeature;
use Illuminate\Support\Collection;

class OptionalFeatureChoiceHandler extends AbstractChoiceHandler
{
    /**
     * Map counter names to optional feature types.
     *
     * @var array<string, string>
     */
    private const COUNTER_TO_FEATURE_TYPE = [
        'Maneuvers Known' => 'maneuver',
        'Metamagic Known' => 'metamagic',
        'Infusions Known' => 'artificer_infusion',
        'Fighting Styles Known' => 'fighting_style',
        'Runes Known' => 'rune',
        'Arcane Shots Known' => 'arcane_shot',
        'Elemental Disciplines Known' => 'elemental_discipline',
        'Eldritch Invocations Known' => 'eldritch_invocation',
    ];

    public function getType(): string
    {
        return 'optional_feature';
    }

    public function getChoices(Character $character): Collection
    {
        $choices = collect();

        // Get character's classes with their counters
        $characterClasses = $character->characterClasses()
            ->with(['characterClass.counters', 'subclass.counters'])
            ->get();

        foreach ($characterClasses as $charClass) {
            $classLevel = $charClass->level;
            $className = $charClass->characterClass->name;
            $classSlug = $charClass->class_slug;
            $subclassName = $charClass->subclass?->name;

            // Check base class counters
            $this->processClassCounters(
                $charClass->characterClass->counters ?? collect(),
                $classLevel,
                $className,
                $classSlug,
                null,
                null, // No subclass slug for base class counters
                $character,
                $choices
            );

            // Check subclass counters
            if ($charClass->subclass) {
                $this->processClassCounters(
                    $charClass->subclass->counters ?? collect(),
                    $classLevel,
                    $className,
                    $classSlug,
                    $subclassName,
                    $charClass->subclass->slug, // Pass subclass slug for accurate feature lookups
                    $character,
                    $choices
                );
            }
        }

        return $choices;
    }

    public function resolve(Character $character, PendingChoice $choice, array $selection): void
    {
        // Accept both optional_feature_slug (legacy) and selected[0] (standardized) formats
        $optionalFeatureSlug = $selection['optional_feature_slug'] ?? $selection['selected'][0] ?? null;

        if (! $optionalFeatureSlug) {
            throw new InvalidSelectionException(
                $choice->id,
                'missing',
                'optional_feature_slug is required'
            );
        }

        // Validate optional feature exists
        $optionalFeature = OptionalFeature::where('slug', $optionalFeatureSlug)
            ->first();
        if (! $optionalFeature) {
            throw new InvalidSelectionException(
                $choice->id,
                'invalid',
                "Optional feature {$optionalFeatureSlug} not found"
            );
        }

        // Get class_slug from metadata (preferred) or parse from choice id
        // Choice IDs are generated with slug-based sourceSlug since the slug migration
        $parsed = $this->parseChoiceId($choice->id);
        $classSlug = $choice->metadata['class_slug'] ?? $parsed['sourceSlug'];

        // Create the feature selection record
        $character->featureSelections()->create([
            'optional_feature_slug' => $optionalFeature->slug,
            'class_slug' => $classSlug,
            'subclass_name' => $choice->metadata['subclass_name'] ?? null,
            'level_acquired' => $parsed['level'] ?? $character->total_level,
        ]);
    }

    public function canUndo(Character $character, PendingChoice $choice): bool
    {
        // Optional features can be swapped/retrained
        return true;
    }

    public function undo(Character $character, PendingChoice $choice): void
    {
        $optionalFeatureSlug = $choice->metadata['optional_feature_slug'] ?? null;

        if (! $optionalFeatureSlug) {
            return;
        }

        // Delete the feature selection record
        $character->featureSelections()
            ->where('optional_feature_slug', $optionalFeatureSlug)
            ->delete();

        $character->load('featureSelections');
    }

    /**
     * Process counters from a class/subclass to find choice allowances.
     */
    private function processClassCounters(
        Collection $counters,
        int $classLevel,
        string $className,
        string $classSlug,
        ?string $subclassName,
        ?string $subclassSlug,
        Character $character,
        Collection &$choices
    ): void {
        foreach (self::COUNTER_TO_FEATURE_TYPE as $counterName => $featureType) {
            // Find the highest counter value at or below the character's class level
            $relevantCounters = $counters
                ->filter(fn ($c) => $c->counter_name === $counterName && $c->level <= $classLevel)
                ->sortByDesc('level');

            if ($relevantCounters->isEmpty()) {
                continue;
            }

            $maxCounter = $relevantCounters->first();

            // Count how many of this feature type the character has already selected
            $selected = $this->countSelectedFeatures($character, $featureType);

            // Calculate remaining choices
            $allowed = $maxCounter->counter_value;
            $remaining = max(0, $allowed - $selected);

            // Build inline options (fix for #622 - exclude already-selected features)
            $options = $this->buildInlineOptions(
                $featureType,
                $classSlug,
                $subclassName,
                $subclassSlug,
                $character->total_level,
                $character
            );

            // Create unique choice ID
            $choiceId = $this->generateChoiceId(
                'optional_feature',
                'class',
                $classSlug,
                $maxCounter->level,
                "{$featureType}_1"
            );

            $choice = new PendingChoice(
                id: $choiceId,
                type: 'optional_feature',
                subtype: $featureType,
                source: 'class',
                sourceName: $className,
                levelGranted: $maxCounter->level,
                required: true,
                quantity: $allowed,
                remaining: $remaining,
                selected: [],
                options: $options,
                optionsEndpoint: null,
                metadata: [
                    'class_slug' => $classSlug,
                    'subclass_name' => $subclassName,
                    'counter_name' => $counterName,
                ],
            );

            $choices->push($choice);
        }
    }

    /**
     * Count how many features of a given type the character has selected.
     */
    private function countSelectedFeatures(Character $character, string $featureType): int
    {
        return $character->featureSelections()
            ->with('optionalFeature')
            ->get()
            ->filter(fn ($fs) => $fs->optionalFeature !== null)
            ->filter(fn ($fs) => $fs->optionalFeature->feature_type?->value === $featureType)
            ->count();
    }

    /**
     * Build inline options array with filtered features.
     *
     * Fix for #622: Returns features filtered by type, class, level, and
     * excludes already-selected features to prevent duplicate selections.
     *
     * @return array<int, array{slug: string, name: string, description: string|null, level_requirement: int|null, prerequisite_text: string|null}>
     */
    private function buildInlineOptions(
        string $featureType,
        string $classSlug,
        ?string $subclassName,
        ?string $subclassSlug,
        int $characterLevel,
        Character $character
    ): array {
        // Get already-selected feature slugs for this character
        $selectedSlugs = $character->featureSelections()
            ->pluck('optional_feature_slug')
            ->toArray();

        // Determine which class slug to use for filtering.
        // Optional features like maneuvers may be associated with the subclass directly
        // (e.g., phb:fighter-battle-master), not the parent class (phb:fighter).
        // When we have a subclass slug, check both the subclass and parent class.
        $classSlugsToCheck = $subclassSlug
            ? [$subclassSlug, $classSlug]
            : [$classSlug];

        // Build query with same filters as the old endpoint
        $query = OptionalFeature::query()
            ->where('feature_type', $featureType)
            ->whereHas('classes', fn ($q) => $q->whereIn('classes.slug', $classSlugsToCheck))
            ->where(fn ($q) => $q
                ->whereNull('level_requirement')
                ->orWhere('level_requirement', '<=', $characterLevel)
            );

        // Filter by subclass name if applicable (for features with specific subclass restrictions)
        if ($subclassName) {
            // Include features that either match this subclass OR have no subclass restriction
            $query->where(fn ($q) => $q
                ->whereHas('classPivots', fn ($pq) => $pq->where('subclass_name', $subclassName))
                ->orWhereHas('classPivots', fn ($pq) => $pq->whereNull('subclass_name'))
            );
        }

        // Exclude already-selected features (#622 fix)
        if (! empty($selectedSlugs)) {
            $query->whereNotIn('slug', $selectedSlugs);
        }

        // Return formatted options array
        return $query->get()->map(fn (OptionalFeature $feature) => [
            'slug' => $feature->slug,
            'name' => $feature->name,
            'description' => $feature->description,
            'level_requirement' => $feature->level_requirement,
            'prerequisite_text' => $feature->prerequisite_text,
        ])->values()->toArray();
    }
}
