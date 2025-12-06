<?php

declare(strict_types=1);

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterClass;
use Illuminate\Support\Collection;

class SubclassChoiceHandler extends AbstractChoiceHandler
{
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
            if (! $class || $characterClass->subclass_id !== null) {
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

                return [
                    'id' => $subclass->id,
                    'name' => $subclass->name,
                    'slug' => $subclass->slug,
                    'description' => $subclass->description,
                    'features_preview' => $features->pluck('feature_name')->values()->all(),
                ];
            })->values()->all();

            $choice = new PendingChoice(
                id: $this->generateChoiceId('subclass', 'class', $class->id, $subclassLevel, 'subclass'),
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
                    'class_id' => $class->id,
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
        $classId = $parsed['sourceId'];
        $subclassId = $selection['subclass_id'] ?? null;

        if ($subclassId === null) {
            throw new InvalidSelectionException($choice->id, 'empty', 'Subclass ID is required');
        }

        // Validate subclass exists and belongs to this class
        $subclass = CharacterClass::where('id', $subclassId)
            ->where('parent_class_id', $classId)
            ->first();

        if (! $subclass) {
            throw new InvalidSelectionException(
                $choice->id,
                'invalid_subclass',
                "Subclass {$subclassId} does not belong to class {$classId}"
            );
        }

        // Update the character class pivot
        $character->characterClasses()
            ->where('class_id', $classId)
            ->update(['subclass_id' => $subclassId]);

        // Reload the relationship
        $character->load('characterClasses.subclass');
    }

    public function canUndo(Character $character, PendingChoice $choice): bool
    {
        $parsed = $this->parseChoiceId($choice->id);
        $classId = $parsed['sourceId'];

        // Get the character class
        $characterClass = $character->characterClasses()
            ->where('class_id', $classId)
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
        $classId = $parsed['sourceId'];

        // Clear subclass_id on the pivot
        $character->characterClasses()
            ->where('class_id', $classId)
            ->update(['subclass_id' => null]);

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
}
