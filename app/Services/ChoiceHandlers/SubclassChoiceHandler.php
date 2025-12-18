<?php

declare(strict_types=1);

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Services\CharacterFeatureService;
use Illuminate\Support\Collection;

class SubclassChoiceHandler extends AbstractChoiceHandler
{
    public function __construct(
        private CharacterFeatureService $featureService
    ) {}

    public function getType(): string
    {
        return 'subclass';
    }

    public function getChoices(Character $character): Collection
    {
        $choices = collect();

        // Iterate through all character classes
        foreach ($character->characterClasses as $characterClass) {
            $class = $characterClass->characterClass;

            // Skip if no class or already has subclass selected
            if (! $class || $characterClass->subclass_slug !== null) {
                continue;
            }

            // Get subclass level for this class
            $subclassLevel = $class->subclass_level;

            // Skip if no subclass level defined or not yet at that level
            if ($subclassLevel === null || $characterClass->level < $subclassLevel) {
                continue;
            }

            // Load subclasses if not already loaded
            if (! $class->relationLoaded('subclasses')) {
                $class->load('subclasses.features');
            }

            // Skip if no subclasses available
            if ($class->subclasses->isEmpty()) {
                continue;
            }

            // Build options array
            $options = $class->subclasses->map(function ($subclass) use ($subclassLevel) {
                // Get features at the subclass level for preview
                $features = $subclass->features()
                    ->where('level', $subclassLevel)
                    ->whereNull('parent_feature_id')
                    ->get();

                $option = [
                    'slug' => $subclass->slug,
                    'name' => $subclass->name,
                    'description' => $subclass->description,
                    'features_preview' => $features->pluck('feature_name')->values()->all(),
                ];

                // Issue #752: Add variant choices for subclasses with choice_group features
                // (e.g., Circle of the Land terrain variants)
                $variantChoices = $this->getVariantChoicesForSubclass($subclass);
                if (! empty($variantChoices)) {
                    $option['variant_choices'] = $variantChoices;
                }

                return $option;
            })->values()->all();

            $choice = new PendingChoice(
                id: $this->generateChoiceId('subclass', 'class', $class->slug, $subclassLevel, 'subclass'),
                type: 'subclass',
                subtype: null,
                source: 'class',
                sourceName: $class->name,
                levelGranted: $subclassLevel,
                required: true,
                quantity: 1,
                remaining: 1,
                selected: [],
                options: $options,
                optionsEndpoint: null,
                metadata: [
                    'class_slug' => $class->slug,
                    'subclass_feature_name' => $this->getSubclassFeatureName($class),
                ],
            );

            $choices->push($choice);
        }

        return $choices;
    }

    public function resolve(Character $character, PendingChoice $choice, array $selection): void
    {
        $parsed = $this->parseChoiceId($choice->id);
        $classSlug = $parsed['sourceSlug'];
        // Accept both subclass_slug (legacy) and selected[0] (standardized) formats
        $subclassSlug = $selection['subclass_slug'] ?? $selection['selected'][0] ?? null;

        if ($subclassSlug === null) {
            throw new InvalidSelectionException($choice->id, 'empty', 'Subclass slug is required');
        }

        // Get the parent class
        $parentClass = CharacterClass::where('slug', $classSlug)->first();
        if (! $parentClass) {
            throw new InvalidSelectionException($choice->id, 'invalid_class', "Class {$classSlug} not found");
        }

        // Validate subclass exists and belongs to this class
        $subclass = CharacterClass::where('slug', $subclassSlug)
            ->where('parent_class_id', $parentClass->id)
            ->first();

        if (! $subclass) {
            throw new InvalidSelectionException(
                $choice->id,
                'invalid_subclass',
                "Subclass {$subclassSlug} does not belong to class {$classSlug}"
            );
        }

        // Issue #752: Extract variant choices (e.g., terrain for Circle of the Land)
        $variantChoices = $selection['variant_choices'] ?? null;

        // Update the character class pivot
        $updateData = ['subclass_slug' => $subclassSlug];
        if ($variantChoices !== null) {
            $updateData['subclass_choices'] = $variantChoices;
        }

        $character->characterClasses()
            ->where('class_slug', $classSlug)
            ->update($updateData);

        // Reload the relationship
        $character->load('characterClasses.subclass');

        // Assign subclass features to the character
        $this->featureService->populateFromSubclass($character, $classSlug, $subclassSlug);
    }

    public function canUndo(Character $character, PendingChoice $choice): bool
    {
        $parsed = $this->parseChoiceId($choice->id);
        $classSlug = $parsed['sourceSlug'];

        // Get the character class
        $characterClass = $character->characterClasses()
            ->where('class_slug', $classSlug)
            ->first();

        if (! $characterClass) {
            return false;
        }

        // Can undo if still at the level the subclass was granted
        return $characterClass->level === $choice->levelGranted;
    }

    public function undo(Character $character, PendingChoice $choice): void
    {
        $parsed = $this->parseChoiceId($choice->id);
        $classSlug = $parsed['sourceSlug'];

        // Get the current subclass slug before clearing it
        $characterClass = $character->characterClasses()
            ->where('class_slug', $classSlug)
            ->first();

        if ($characterClass && $characterClass->subclass_slug) {
            // Remove subclass features from the character (only for this subclass)
            $this->featureService->clearSubclassFeatures($character, $characterClass->subclass_slug);

            // Clear subclass_slug and subclass_choices on the pivot (Issue #752)
            $characterClass->update([
                'subclass_slug' => null,
                'subclass_choices' => null,
            ]);
        }

        // Reload the relationship
        $character->load('characterClasses');
    }

    /**
     * Get the subclass feature name based on class.
     */
    private function getSubclassFeatureName(CharacterClass $class): string
    {
        return match (strtolower($class->name)) {
            'cleric' => 'Divine Domain',
            'sorcerer' => 'Sorcerous Origin',
            'warlock' => 'Otherworldly Patron',
            'druid' => 'Druid Circle',
            'wizard' => 'Arcane Tradition',
            'barbarian' => 'Primal Path',
            'bard' => 'Bard College',
            'fighter' => 'Martial Archetype',
            'monk' => 'Monastic Tradition',
            'paladin' => 'Sacred Oath',
            'ranger' => 'Ranger Archetype',
            'rogue' => 'Roguish Archetype',
            default => 'Subclass',
        };
    }

    /**
     * Get variant choices for a subclass that has choice_group features.
     *
     * Issue #752: Some subclasses have variant features that require an additional choice:
     * - Circle of the Land: terrain (Arctic, Coast, Desert, etc.)
     * - Path of the Totem Warrior: totem animals (Bear, Eagle, Wolf) at multiple levels
     *
     * @return array<string, array{required: bool, label: string, options: array}> Variant choices keyed by choice_group
     */
    private function getVariantChoicesForSubclass(CharacterClass $subclass): array
    {
        // Get all features with a choice_group (variant features)
        $variantFeatures = $subclass->features()
            ->whereNotNull('choice_group')
            ->get();

        if ($variantFeatures->isEmpty()) {
            return [];
        }

        // Group by choice_group
        $grouped = $variantFeatures->groupBy('choice_group');

        $variantChoices = [];
        foreach ($grouped as $choiceGroup => $features) {
            // Build options for this choice group
            $options = $features->map(function ($feature) {
                // Extract variant name from feature name: "Arctic (Circle of the Land)" -> "arctic"
                $variantName = $feature->variant_name; // Uses accessor

                return [
                    'value' => $variantName,
                    'name' => ucfirst($variantName ?? $feature->feature_name),
                    'description' => $feature->description,
                    'spells' => $feature->spells->pluck('name')->values()->all(),
                ];
            })->values()->all();

            $variantChoices[$choiceGroup] = [
                'required' => true,
                'label' => $this->getVariantChoiceLabel($choiceGroup),
                'options' => $options,
            ];
        }

        return $variantChoices;
    }

    /**
     * Get a human-readable label for a variant choice group.
     */
    private function getVariantChoiceLabel(string $choiceGroup): string
    {
        return match ($choiceGroup) {
            'terrain' => 'Choose your terrain',
            'totem_spirit' => 'Choose your totem spirit',
            'totem_aspect' => 'Choose your totem aspect',
            'totem_attunement' => 'Choose your totemic attunement',
            default => 'Choose your '.str_replace('_', ' ', $choiceGroup),
        };
    }
}
