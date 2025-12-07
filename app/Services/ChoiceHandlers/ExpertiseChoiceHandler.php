<?php

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use Illuminate\Support\Collection;

class ExpertiseChoiceHandler extends AbstractChoiceHandler
{
    public function getType(): string
    {
        return 'expertise';
    }

    public function getChoices(Character $character): Collection
    {
        $choices = collect();

        // Check each class for expertise opportunities
        foreach ($character->characterClasses as $characterClass) {
            $class = $characterClass->characterClass;
            $level = $characterClass->level;
            $classSlug = $class->slug;

            // Get expertise levels for this class
            $expertiseLevels = $this->getExpertiseLevels($classSlug, $level);

            foreach ($expertiseLevels as $expertiseLevel => $quantity) {
                $choiceGroup = "expertise_{$expertiseLevel}";

                // Check if this choice is already complete
                $existingExpertise = $character->proficiencies()
                    ->where('source', 'class')
                    ->where('choice_group', $choiceGroup)
                    ->get();

                if ($existingExpertise->count() >= $quantity) {
                    // Choice is complete
                    continue;
                }

                // Get available options (proficiencies without expertise)
                $options = $this->getAvailableOptions($character, $classSlug);

                if ($options->isEmpty()) {
                    // No available options (shouldn't happen, but handle gracefully)
                    continue;
                }

                // Get selected expertise for this choice group
                $selected = $existingExpertise->pluck('id')->map('strval')->all();

                $choice = new PendingChoice(
                    id: $this->generateChoiceId('expertise', 'class', $class->full_slug, $expertiseLevel, $choiceGroup),
                    type: 'expertise',
                    subtype: null,
                    source: 'class',
                    sourceName: $class->name,
                    levelGranted: $expertiseLevel,
                    required: true,
                    quantity: $quantity,
                    remaining: $quantity - $existingExpertise->count(),
                    selected: $selected,
                    options: $options->all(),
                    optionsEndpoint: null,
                    metadata: [
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
        $source = $parsed['source'];

        $selected = $selection['selected'] ?? [];
        if (empty($selected)) {
            throw new InvalidSelectionException($choice->id, 'empty', 'Selection cannot be empty');
        }

        // Find the proficiencies to update
        $proficiencies = $character->proficiencies()
            ->whereIn('id', $selected)
            ->get();

        if ($proficiencies->count() !== count($selected)) {
            throw new InvalidSelectionException(
                $choice->id,
                'invalid_proficiencies',
                'One or more selected proficiencies do not exist'
            );
        }

        // Update expertise flag on each proficiency
        foreach ($proficiencies as $proficiency) {
            $proficiency->update([
                'expertise' => true,
                'source' => $source,
                'choice_group' => $choiceGroup,
            ]);
        }

        $character->load('proficiencies');
    }

    public function canUndo(Character $character, PendingChoice $choice): bool
    {
        return true;
    }

    public function undo(Character $character, PendingChoice $choice): void
    {
        $parsed = $this->parseChoiceId($choice->id);

        // Clear expertise for this source + choice group
        $character->proficiencies()
            ->where('source', $parsed['source'])
            ->where('choice_group', $parsed['group'])
            ->update([
                'expertise' => false,
                'source' => null,
                'choice_group' => null,
            ]);

        $character->load('proficiencies');
    }

    /**
     * Get expertise levels and quantities for a class.
     *
     * @return array<int, int> Array of [level => quantity]
     */
    private function getExpertiseLevels(string $classSlug, int $currentLevel): array
    {
        $expertiseLevels = [];

        if ($classSlug === 'rogue') {
            // Rogue gets expertise at levels 1 and 6
            if ($currentLevel >= 1) {
                $expertiseLevels[1] = 2;
            }
            if ($currentLevel >= 6) {
                $expertiseLevels[6] = 2;
            }
        } elseif ($classSlug === 'bard') {
            // Bard gets expertise at levels 3 and 10
            if ($currentLevel >= 3) {
                $expertiseLevels[3] = 2;
            }
            if ($currentLevel >= 10) {
                $expertiseLevels[10] = 2;
            }
        }

        return $expertiseLevels;
    }

    /**
     * Get available proficiencies for expertise (proficiencies without expertise).
     *
     * @return Collection<int, array>
     */
    private function getAvailableOptions(Character $character, string $classSlug): Collection
    {
        $proficiencies = $character->proficiencies()->get();

        // Filter to proficiencies without expertise
        $available = $proficiencies->filter(function ($proficiency) use ($classSlug) {
            // Skip if already has expertise
            if ($proficiency->expertise) {
                return false;
            }

            // For Bards, only allow skills
            if ($classSlug === 'bard') {
                return $proficiency->skill_id !== null;
            }

            // For Rogues, allow both skills and tools
            return $proficiency->skill_id !== null || $proficiency->proficiency_type_id !== null;
        });

        // Map to option format
        return $available->map(function ($proficiency) {
            if ($proficiency->skill_id !== null) {
                return [
                    'type' => 'skill',
                    'id' => $proficiency->id,
                    'skill_id' => $proficiency->skill_id,
                    'slug' => $proficiency->skill->slug ?? null,
                    'name' => $proficiency->skill->name ?? null,
                ];
            } else {
                return [
                    'type' => 'proficiency_type',
                    'id' => $proficiency->id,
                    'proficiency_type_id' => $proficiency->proficiency_type_id,
                    'slug' => $proficiency->proficiencyType->slug ?? null,
                    'name' => $proficiency->proficiencyType->name ?? null,
                ];
            }
        })->values();
    }
}
