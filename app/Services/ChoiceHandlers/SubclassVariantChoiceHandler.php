<?php

declare(strict_types=1);

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Services\CharacterFeatureService;
use Illuminate\Support\Collection;

/**
 * Handles variant choices for subclasses at levels after initial subclass selection.
 *
 * Issue #763: Some subclasses like Path of the Totem Warrior have variant features at
 * multiple levels (L3, L6, L14). The initial variant choice (totem_spirit at L3) is
 * handled by SubclassChoiceHandler during subclass selection. This handler manages
 * the subsequent choices (totem_aspect at L6, totem_attunement at L14).
 */
class SubclassVariantChoiceHandler extends AbstractChoiceHandler
{
    public function __construct(
        private CharacterFeatureService $featureService,
        private SubclassChoiceHandler $subclassChoiceHandler
    ) {}

    public function getType(): string
    {
        return 'subclass_variant';
    }

    public function getChoices(Character $character): Collection
    {
        $choices = collect();

        // Batch load all subclasses and parent classes to prevent N+1 queries
        $subclassSlugs = $character->characterClasses
            ->pluck('subclass_slug')
            ->filter()
            ->unique();

        $classSlugs = $character->characterClasses
            ->pluck('class_slug')
            ->filter()
            ->unique();

        $allSlugs = $subclassSlugs->merge($classSlugs)->unique();
        $classesById = CharacterClass::whereIn('slug', $allSlugs)
            ->get()
            ->keyBy('slug');

        foreach ($character->characterClasses as $characterClass) {
            // Skip if no subclass selected
            if ($characterClass->subclass_slug === null) {
                continue;
            }

            // Get the subclass from batch-loaded collection
            $subclass = $classesById->get($characterClass->subclass_slug);
            if (! $subclass) {
                continue;
            }

            // Get all variant choices for this subclass (without level filter)
            $allVariantChoices = $this->subclassChoiceHandler->getVariantChoicesForSubclass($subclass);

            if (empty($allVariantChoices)) {
                continue;
            }

            // Get current subclass_choices
            $currentChoices = $characterClass->subclass_choices ?? [];

            // Get the subclass level (for filtering what was already handled at selection)
            $parentClass = $classesById->get($characterClass->class_slug);
            $subclassLevel = $parentClass?->subclass_level ?? 3;

            // Find pending variant choices at or below character's current level
            foreach ($allVariantChoices as $choiceGroup => $choiceData) {
                // Skip if already selected
                if (isset($currentChoices[$choiceGroup])) {
                    continue;
                }

                // Get the level for this choice group
                $featureLevel = $this->getChoiceGroupLevel($subclass, $choiceGroup);

                // Skip if character hasn't reached this level yet
                if ($characterClass->level < $featureLevel) {
                    continue;
                }

                // Skip if this was already handled at subclass selection
                // (choice groups at or near subclass level)
                if ($featureLevel <= $subclassLevel + 1) {
                    continue;
                }

                // Build the pending choice
                $options = collect($choiceData['options'])->map(function ($option) {
                    return [
                        'value' => $option['value'],
                        'name' => $option['name'],
                        'description' => $option['description'] ?? null,
                    ];
                })->values()->all();

                $choice = new PendingChoice(
                    id: $this->generateChoiceId('subclass_variant', 'subclass', $subclass->slug, $featureLevel, $choiceGroup),
                    type: 'subclass_variant',
                    subtype: $choiceGroup,
                    source: 'subclass',
                    sourceName: $subclass->name,
                    levelGranted: $featureLevel,
                    required: true,
                    quantity: 1,
                    remaining: 1,
                    selected: [],
                    options: $options,
                    optionsEndpoint: null,
                    metadata: [
                        'class_slug' => $characterClass->class_slug,
                        'subclass_slug' => $subclass->slug,
                        'choice_group' => $choiceGroup,
                    ],
                );

                $choices->push($choice);
            }
        }

        return $choices;
    }

    public function resolve(Character $character, PendingChoice $choice, array $selection): void
    {
        $parsed = $this->parseChoiceId($choice->id);
        $choiceGroup = $parsed['group'];
        $subclassSlug = $parsed['sourceSlug'];

        // Get the selected value
        $selectedValue = $selection['selected'][0] ?? null;

        if ($selectedValue === null) {
            throw new InvalidSelectionException($choice->id, 'empty', 'Selection is required');
        }

        // Get the character class pivot
        $characterClass = $character->characterClasses()
            ->where('subclass_slug', $subclassSlug)
            ->first();

        if (! $characterClass) {
            throw new InvalidSelectionException(
                $choice->id,
                'invalid_subclass',
                "Character does not have subclass {$subclassSlug}"
            );
        }

        // Get the subclass for validation
        $subclass = CharacterClass::where('slug', $subclassSlug)->first();

        if (! $subclass) {
            throw new InvalidSelectionException(
                $choice->id,
                'subclass_not_found',
                "Subclass {$subclassSlug} not found"
            );
        }

        // Validate the selection
        $this->validateVariantChoice($choice->id, $subclass, $choiceGroup, $selectedValue);

        // Merge into existing subclass_choices
        $currentChoices = $characterClass->subclass_choices ?? [];
        $currentChoices[$choiceGroup] = strtolower($selectedValue);

        $characterClass->update(['subclass_choices' => $currentChoices]);

        // Reload relationships
        $character->load('characterClasses');

        // Re-populate features (will now include the newly selected variant)
        $this->featureService->populateFromSubclass($character, $characterClass->class_slug, $subclassSlug);
    }

    public function canUndo(Character $character, PendingChoice $choice): bool
    {
        $parsed = $this->parseChoiceId($choice->id);
        $levelGranted = $parsed['level'];
        $subclassSlug = $parsed['sourceSlug'];

        // Get the character class
        $characterClass = $character->characterClasses()
            ->where('subclass_slug', $subclassSlug)
            ->first();

        if (! $characterClass) {
            return false;
        }

        // Can undo if still at the level the choice was made
        return $characterClass->level === $levelGranted;
    }

    public function undo(Character $character, PendingChoice $choice): void
    {
        $parsed = $this->parseChoiceId($choice->id);
        $choiceGroup = $parsed['group'];
        $subclassSlug = $parsed['sourceSlug'];

        $characterClass = $character->characterClasses()
            ->where('subclass_slug', $subclassSlug)
            ->first();

        if (! $characterClass) {
            return;
        }

        // Remove just this choice group from subclass_choices
        $currentChoices = $characterClass->subclass_choices ?? [];
        unset($currentChoices[$choiceGroup]);

        $characterClass->update([
            'subclass_choices' => empty($currentChoices) ? null : $currentChoices,
        ]);

        // Reload and re-populate features
        $character->load('characterClasses');
        $this->featureService->populateFromSubclass($character, $characterClass->class_slug, $subclassSlug);
    }

    /**
     * Get the feature level for a specific choice group.
     */
    private function getChoiceGroupLevel(CharacterClass $subclass, string $choiceGroup): int
    {
        $feature = $subclass->features()
            ->where('choice_group', $choiceGroup)
            ->orderBy('level')
            ->first();

        return $feature?->level ?? 1;
    }

    /**
     * Validate that the selected value is valid for this choice group.
     */
    private function validateVariantChoice(
        string $choiceId,
        CharacterClass $subclass,
        string $choiceGroup,
        string $selectedValue
    ): void {
        // Get valid options for this choice group
        $validOptions = $subclass->features()
            ->where('choice_group', $choiceGroup)
            ->get()
            ->map(fn ($f) => $f->variant_name)
            ->filter()
            ->values()
            ->all();

        $normalizedValue = strtolower($selectedValue);

        if (! in_array($normalizedValue, $validOptions, true)) {
            throw new InvalidSelectionException(
                $choiceId,
                'invalid_variant_value',
                "Invalid {$choiceGroup} value '{$selectedValue}' for subclass {$subclass->slug}. ".
                'Valid options: '.implode(', ', $validOptions)
            );
        }
    }
}
