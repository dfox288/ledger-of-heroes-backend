<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterProficiency;
use App\Models\Proficiency;
use InvalidArgumentException;

class CharacterProficiencyService
{
    /**
     * Populate fixed (non-choice) proficiencies from the character's primary class.
     */
    public function populateFromClass(Character $character): void
    {
        $primaryClass = $character->primary_class;
        if (! $primaryClass) {
            return;
        }

        $this->populateFixedProficiencies($character, $primaryClass, 'class');
    }

    /**
     * Populate fixed (non-choice) proficiencies from the character's race.
     */
    public function populateFromRace(Character $character): void
    {
        if (! $character->race_id) {
            return;
        }

        $this->populateFixedProficiencies($character, $character->race, 'race');
    }

    /**
     * Populate fixed (non-choice) proficiencies from the character's background.
     */
    public function populateFromBackground(Character $character): void
    {
        if (! $character->background_id) {
            return;
        }

        $this->populateFixedProficiencies($character, $character->background, 'background');
    }

    /**
     * Populate all fixed proficiencies from class, race, and background.
     */
    public function populateAll(Character $character): void
    {
        $this->populateFromClass($character);
        $this->populateFromRace($character);
        $this->populateFromBackground($character);
    }

    /**
     * Get pending proficiency choices that need user input.
     *
     * @return array<string, array<string, array{quantity: int, remaining: int, options: array}>>
     */
    public function getPendingChoices(Character $character): array
    {
        $choices = [];

        // Get existing character proficiencies
        $existingSkillIds = $character->proficiencies()
            ->whereNotNull('skill_id')
            ->pluck('skill_id')
            ->toArray();

        $existingProfTypeIds = $character->proficiencies()
            ->whereNotNull('proficiency_type_id')
            ->pluck('proficiency_type_id')
            ->toArray();

        // Check class choices (use primary class)
        $primaryClass = $character->primary_class;
        if ($primaryClass) {
            $choices['class'] = $this->getChoicesFromEntity(
                $primaryClass,
                $existingSkillIds,
                $existingProfTypeIds
            );
        }

        // Check race choices
        if ($character->race) {
            $choices['race'] = $this->getChoicesFromEntity(
                $character->race,
                $existingSkillIds,
                $existingProfTypeIds
            );
        }

        // Check background choices
        if ($character->background) {
            $choices['background'] = $this->getChoicesFromEntity(
                $character->background,
                $existingSkillIds,
                $existingProfTypeIds
            );
        }

        return $choices;
    }

    /**
     * Make a skill choice for a character.
     *
     * @param  array<int>  $skillIds  The skill IDs the user chose
     *
     * @throws InvalidArgumentException
     */
    public function makeSkillChoice(Character $character, string $source, string $choiceGroup, array $skillIds): void
    {
        // Get the source entity
        $entity = match ($source) {
            'class' => $character->primary_class,
            'race' => $character->race,
            'background' => $character->background,
            default => throw new InvalidArgumentException("Invalid source: {$source}"),
        };

        if (! $entity) {
            throw new InvalidArgumentException("Character has no {$source} assigned");
        }

        // Get the choice options from the entity
        $choiceOptions = $entity->proficiencies()
            ->where('is_choice', true)
            ->where('choice_group', $choiceGroup)
            ->where('proficiency_type', 'skill')
            ->get();

        if ($choiceOptions->isEmpty()) {
            throw new InvalidArgumentException("No choice group '{$choiceGroup}' found for {$source}");
        }

        // Get required quantity
        $quantity = $choiceOptions->first()->quantity ?? 1;

        if (count($skillIds) !== $quantity) {
            throw new InvalidArgumentException(
                "Must choose exactly {$quantity} skills, got ".count($skillIds)
            );
        }

        // Validate all selected skills are valid options
        $validSkillIds = $choiceOptions->pluck('skill_id')->toArray();
        foreach ($skillIds as $skillId) {
            if (! in_array($skillId, $validSkillIds)) {
                throw new InvalidArgumentException("Skill ID {$skillId} is not a valid option for this choice");
            }
        }

        // Clear existing choices for this source + choice_group before adding new ones
        // This ensures re-submitting replaces rather than adds
        $character->proficiencies()
            ->where('source', $source)
            ->where('choice_group', $choiceGroup)
            ->delete();

        // Create the proficiencies
        foreach ($skillIds as $skillId) {
            CharacterProficiency::create([
                'character_id' => $character->id,
                'skill_id' => $skillId,
                'source' => $source,
                'choice_group' => $choiceGroup,
            ]);
        }

        // Refresh the relationship
        $character->load('proficiencies');
    }

    /**
     * Populate fixed proficiencies from an entity (class, race, or background).
     */
    private function populateFixedProficiencies(Character $character, $entity, string $source): void
    {
        $fixedProficiencies = $entity->proficiencies()
            ->where('is_choice', false)
            ->get();

        foreach ($fixedProficiencies as $proficiency) {
            // Skip if already exists
            $exists = $character->proficiencies()
                ->where('source', $source)
                ->where(function ($query) use ($proficiency) {
                    if ($proficiency->skill_id) {
                        $query->where('skill_id', $proficiency->skill_id);
                    } elseif ($proficiency->proficiency_type_id) {
                        $query->where('proficiency_type_id', $proficiency->proficiency_type_id);
                    }
                })
                ->exists();

            if ($exists) {
                continue;
            }

            CharacterProficiency::create([
                'character_id' => $character->id,
                'proficiency_type_id' => $proficiency->proficiency_type_id,
                'skill_id' => $proficiency->skill_id,
                'source' => $source,
                'expertise' => false,
            ]);
        }

        // Refresh the relationship
        $character->load('proficiencies');
    }

    /**
     * Get choice groups from an entity.
     *
     * @return array<string, array{quantity: int, remaining: int, selected_skills: array<int>, selected_proficiency_types: array<int>, options: array}>
     */
    private function getChoicesFromEntity($entity, array $existingSkillIds, array $existingProfTypeIds): array
    {
        $choices = [];

        $choiceProficiencies = $entity->proficiencies()
            ->where('is_choice', true)
            ->with(['skill', 'proficiencyType'])
            ->get();

        // Group by choice_group
        $grouped = $choiceProficiencies->groupBy('choice_group');

        foreach ($grouped as $groupName => $options) {
            if (! $groupName) {
                continue;
            }

            $quantity = $options->first()->quantity ?? 1;

            // Track selected IDs and count
            $selectedSkillIds = [];
            $selectedProfTypeIds = [];
            $allOptions = [];

            foreach ($options as $option) {
                if ($option->skill_id) {
                    // Always add to options (don't filter out selected)
                    $allOptions[] = [
                        'type' => 'skill',
                        'skill_id' => $option->skill_id,
                        'skill' => $option->skill ? [
                            'id' => $option->skill->id,
                            'name' => $option->skill->name,
                            'slug' => $option->skill->slug,
                        ] : null,
                    ];

                    // Track if already selected
                    if (in_array($option->skill_id, $existingSkillIds)) {
                        $selectedSkillIds[] = $option->skill_id;
                    }
                } elseif ($option->proficiency_type_id) {
                    // Always add to options (don't filter out selected)
                    $allOptions[] = [
                        'type' => 'proficiency_type',
                        'proficiency_type_id' => $option->proficiency_type_id,
                        'proficiency_type' => $option->proficiencyType ? [
                            'id' => $option->proficiencyType->id,
                            'name' => $option->proficiencyType->name,
                            'slug' => $option->proficiencyType->slug,
                        ] : null,
                    ];

                    // Track if already selected
                    if (in_array($option->proficiency_type_id, $existingProfTypeIds)) {
                        $selectedProfTypeIds[] = $option->proficiency_type_id;
                    }
                }
            }

            $chosenCount = count($selectedSkillIds) + count($selectedProfTypeIds);
            $remaining = max(0, $quantity - $chosenCount);

            $choices[$groupName] = [
                'quantity' => $quantity,
                'remaining' => $remaining,
                'selected_skills' => $selectedSkillIds,
                'selected_proficiency_types' => $selectedProfTypeIds,
                'options' => $allOptions,
            ];
        }

        return $choices;
    }

    /**
     * Clear all proficiencies for a character from a specific source.
     */
    public function clearProficiencies(Character $character, string $source): void
    {
        $character->proficiencies()
            ->where('source', $source)
            ->delete();

        $character->load('proficiencies');
    }

    /**
     * Get all proficiencies for a character.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCharacterProficiencies(Character $character)
    {
        return $character->proficiencies()
            ->with(['skill', 'proficiencyType'])
            ->get();
    }
}
