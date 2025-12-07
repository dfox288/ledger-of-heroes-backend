<?php

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\ChoiceNotUndoableException;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\FeatureSelection;
use App\Models\OptionalFeature;
use Illuminate\Support\Collection;

class FightingStyleChoiceHandler extends AbstractChoiceHandler
{
    /**
     * Classes that grant fighting styles and their level requirements.
     */
    private const FIGHTING_STYLE_CLASSES = [
        'fighter' => 1,
        'paladin' => 2,
        'ranger' => 2,
    ];

    /**
     * Subclasses that grant additional fighting styles.
     * Format: [subclass_slug => [parent_class_slug, level]]
     */
    private const FIGHTING_STYLE_SUBCLASSES = [
        'champion' => ['fighter', 10],
    ];

    public function getType(): string
    {
        return 'fighting_style';
    }

    public function getChoices(Character $character): Collection
    {
        $choices = collect();

        // Ensure relationships are loaded
        if (! $character->relationLoaded('characterClasses')) {
            $character->load('characterClasses.characterClass');
        }
        if (! $character->relationLoaded('featureSelections')) {
            $character->load('featureSelections');
        }

        // Get all fighting styles already taken
        $takenStyleSlugs = $character->featureSelections
            ->pluck('optional_feature_slug')
            ->filter()
            ->unique()
            ->all();

        // Get all available fighting styles
        $allStyles = OptionalFeature::where('feature_type', 'fighting_style')->get();

        // Check each class for fighting style eligibility
        foreach ($character->characterClasses as $classPivot) {
            $class = $classPivot->relationLoaded('characterClass')
                ? $classPivot->characterClass
                : $classPivot->characterClass()->first();

            if (! $class) {
                continue;
            }

            $classSlug = $class->slug;
            $classLevel = $classPivot->level;

            // Check if this class grants a fighting style at this level
            if (isset(self::FIGHTING_STYLE_CLASSES[$classSlug])) {
                $requiredLevel = self::FIGHTING_STYLE_CLASSES[$classSlug];

                if ($classLevel >= $requiredLevel) {
                    // Check if already selected for this class
                    $hasSelection = $character->featureSelections
                        ->where('class_slug', $class->full_slug)
                        ->filter(function ($selection) use ($allStyles) {
                            return $allStyles->contains('full_slug', $selection->optional_feature_slug);
                        })
                        ->isNotEmpty();

                    if (! $hasSelection) {
                        $choices->push($this->buildPendingChoice(
                            $class,
                            $requiredLevel,
                            $allStyles,
                            $takenStyleSlugs
                        ));
                    }
                }
            }

            // TODO: Check for subclass fighting style grants (Champion Fighter at level 10)
            // This would require checking $classPivot->subclass_slug and comparing against
            // FIGHTING_STYLE_SUBCLASSES, but that's for future implementation
        }

        return $choices;
    }

    public function resolve(Character $character, PendingChoice $choice, array $selection): void
    {
        $parsed = $this->parseChoiceId($choice->id);
        $selected = $selection['selected'] ?? [];

        if (empty($selected)) {
            throw new InvalidSelectionException($choice->id, 'empty', 'Selection cannot be empty');
        }

        // Get the selected fighting style slug (should be exactly 1)
        $optionalFeatureSlug = is_array($selected) ? $selected[0] : $selected;

        // Create the feature selection
        FeatureSelection::create([
            'character_id' => $character->id,
            'optional_feature_slug' => $optionalFeatureSlug,
            'class_slug' => $parsed['sourceSlug'],
            'subclass_name' => null,
            'level_acquired' => $parsed['level'],
        ]);

        // Reload relationship
        $character->load('featureSelections');
    }

    public function canUndo(Character $character, PendingChoice $choice): bool
    {
        return false; // Fighting styles are permanent
    }

    public function undo(Character $character, PendingChoice $choice): void
    {
        throw new ChoiceNotUndoableException($choice->id, 'Fighting style choices are permanent');
    }

    /**
     * Build a PendingChoice for a fighting style selection.
     */
    private function buildPendingChoice(
        $class,
        int $levelGranted,
        Collection $allStyles,
        array $takenStyleSlugs
    ): PendingChoice {
        // Filter out already-taken styles
        $availableStyles = $allStyles->filter(function ($style) use ($takenStyleSlugs) {
            return ! in_array($style->full_slug, $takenStyleSlugs);
        });

        // Build options array
        $options = $availableStyles->map(function ($style) {
            return [
                'type' => 'optional_feature',
                'full_slug' => $style->full_slug,
                'slug' => $style->slug,
                'name' => $style->name,
            ];
        })->values()->all();

        // Build metadata about excluded styles
        $excludedStyles = $allStyles->filter(function ($style) use ($takenStyleSlugs) {
            return in_array($style->full_slug, $takenStyleSlugs);
        })->pluck('name')->all();

        return new PendingChoice(
            id: $this->generateChoiceId('fighting_style', 'class', $class->full_slug, $levelGranted, 'fighting_style'),
            type: 'fighting_style',
            subtype: null,
            source: 'class',
            sourceName: $class->name,
            levelGranted: $levelGranted,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: $options,
            optionsEndpoint: '/api/v1/lookups/optional-features?feature_type=fighting_style',
            metadata: [
                'excluded_styles' => $excludedStyles,
                'reason' => count($excludedStyles) > 0 ? 'Already taken by another class' : null,
            ],
        );
    }
}
