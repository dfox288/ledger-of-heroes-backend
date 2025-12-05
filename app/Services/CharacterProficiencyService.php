<?php

namespace App\Services;

use App\Enums\CharacterSource;
use App\Models\Character;
use App\Models\CharacterProficiency;
use App\Services\Concerns\PopulatesFromEntity;
use InvalidArgumentException;

class CharacterProficiencyService
{
    use PopulatesFromEntity;

    /**
     * Populate fixed (non-choice) proficiencies from the character's primary class.
     */
    public function populateFromClass(Character $character): void
    {
        $primaryClass = $character->primary_class;
        if (! $primaryClass) {
            return;
        }

        $this->populateFromEntity($character, $primaryClass, 'class');
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

        // Check class choices (use primary class)
        $primaryClass = $character->primary_class;
        if ($primaryClass) {
            $choices['class'] = $this->getChoicesFromEntity(
                $primaryClass,
                $character,
                'class'
            );
        }

        // Check race choices
        if ($character->race) {
            $choices['race'] = $this->getChoicesFromEntity(
                $character->race,
                $character,
                'race'
            );
        }

        // Check background choices
        if ($character->background) {
            $choices['background'] = $this->getChoicesFromEntity(
                $character->background,
                $character,
                'background'
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
        // Validate source using enum
        $sourceEnum = CharacterSource::tryFrom($source);
        $validSources = CharacterSource::forProficiencies();

        if (! $sourceEnum || ! in_array($sourceEnum, $validSources)) {
            throw new InvalidArgumentException("Invalid source: {$source}");
        }

        // Get the source entity
        $entity = match ($sourceEnum) {
            CharacterSource::CHARACTER_CLASS => $character->primary_class,
            CharacterSource::RACE => $character->race,
            CharacterSource::BACKGROUND => $character->background,
            default => null,
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
     * Make a proficiency type choice for a character (tools, weapons, armor, etc.).
     *
     * @param  array<int>  $proficiencyTypeIds  The proficiency type IDs the user chose
     *
     * @throws InvalidArgumentException
     */
    public function makeProficiencyTypeChoice(Character $character, string $source, string $choiceGroup, array $proficiencyTypeIds): void
    {
        // Validate source using enum
        $sourceEnum = CharacterSource::tryFrom($source);
        $validSources = CharacterSource::forProficiencies();

        if (! $sourceEnum || ! in_array($sourceEnum, $validSources)) {
            throw new InvalidArgumentException("Invalid source: {$source}");
        }

        // Get the source entity
        $entity = match ($sourceEnum) {
            CharacterSource::CHARACTER_CLASS => $character->primary_class,
            CharacterSource::RACE => $character->race,
            CharacterSource::BACKGROUND => $character->background,
            default => null,
        };

        if (! $entity) {
            throw new InvalidArgumentException("Character has no {$source} assigned");
        }

        // Get the choice definition from the entity
        $choiceOptions = $entity->proficiencies()
            ->where('is_choice', true)
            ->where('choice_group', $choiceGroup)
            ->get();

        if ($choiceOptions->isEmpty()) {
            throw new InvalidArgumentException("No choice group '{$choiceGroup}' found for {$source}");
        }

        $firstOption = $choiceOptions->first();
        $quantity = $firstOption->quantity ?? 1;
        $proficiencyType = $firstOption->proficiency_type;
        $proficiencySubcategory = $firstOption->proficiency_subcategory;

        if (count($proficiencyTypeIds) !== $quantity) {
            throw new InvalidArgumentException(
                "Must choose exactly {$quantity} proficiency types, got ".count($proficiencyTypeIds)
            );
        }

        // Validate selected proficiency types against choice constraints
        // For subcategory-based choices (e.g., "artisan tools"), validate against category/subcategory
        if ($proficiencySubcategory) {
            // Lookup valid proficiency type IDs from ProficiencyType model
            $validProficiencyTypeIds = \App\Models\ProficiencyType::where('category', $proficiencyType)
                ->where('subcategory', $proficiencySubcategory)
                ->pluck('id')
                ->toArray();

            foreach ($proficiencyTypeIds as $proficiencyTypeId) {
                if (! in_array($proficiencyTypeId, $validProficiencyTypeIds)) {
                    throw new InvalidArgumentException("Proficiency type ID {$proficiencyTypeId} is not a valid option for this choice");
                }
            }
        } else {
            // For specific option choices, validate against the specific proficiency_type_ids in the choice
            $validProficiencyTypeIds = $choiceOptions->pluck('proficiency_type_id')->filter()->toArray();
            foreach ($proficiencyTypeIds as $proficiencyTypeId) {
                if (! in_array($proficiencyTypeId, $validProficiencyTypeIds)) {
                    throw new InvalidArgumentException("Proficiency type ID {$proficiencyTypeId} is not a valid option for this choice");
                }
            }
        }

        // Clear existing choices for this source + choice_group before adding new ones
        $character->proficiencies()
            ->where('source', $source)
            ->where('choice_group', $choiceGroup)
            ->delete();

        // Create the proficiencies
        foreach ($proficiencyTypeIds as $proficiencyTypeId) {
            CharacterProficiency::create([
                'character_id' => $character->id,
                'proficiency_type_id' => $proficiencyTypeId,
                'source' => $source,
                'choice_group' => $choiceGroup,
            ]);
        }

        // Refresh the relationship
        $character->load('proficiencies');
    }

    /**
     * Populate fixed proficiencies from an entity (class, race, or background).
     * Implementation of PopulatesFromEntity trait's abstract method.
     */
    protected function populateFromEntity(Character $character, $entity, string $source): void
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
     * @return array<string, array{proficiency_type: string|null, proficiency_subcategory: string|null, quantity: int, remaining: int, selected_skills: array<int>, selected_proficiency_types: array<int>, options: array}>
     */
    private function getChoicesFromEntity($entity, Character $character, string $source): array
    {
        $choices = [];

        // Get existing character proficiencies for this source
        $existingSkillIds = $character->proficiencies()
            ->where('source', $source)
            ->whereNotNull('skill_id')
            ->pluck('skill_id')
            ->toArray();

        $existingProfTypeIds = $character->proficiencies()
            ->where('source', $source)
            ->whereNotNull('proficiency_type_id')
            ->pluck('proficiency_type_id')
            ->toArray();

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

            $firstOption = $options->first();

            // Defensive check - shouldn't happen since groupBy creates groups from existing items
            if (! $firstOption) {
                continue;
            }

            $quantity = $firstOption->quantity ?? 1;

            // Extract proficiency_type and proficiency_subcategory from the first option
            // These fields tell the frontend what kind of choice this is
            $proficiencyType = $firstOption->proficiency_type;
            $proficiencySubcategory = $firstOption->proficiency_subcategory;

            // Get existing selections for THIS specific choice group
            $existingSkillIdsForGroup = $character->proficiencies()
                ->where('source', $source)
                ->where('choice_group', $groupName)
                ->whereNotNull('skill_id')
                ->pluck('skill_id')
                ->toArray();

            $existingProfTypeIdsForGroup = $character->proficiencies()
                ->where('source', $source)
                ->where('choice_group', $groupName)
                ->whereNotNull('proficiency_type_id')
                ->pluck('proficiency_type_id')
                ->toArray();

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

                    // Track if already selected in THIS choice group
                    if (in_array($option->skill_id, $existingSkillIdsForGroup)) {
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

                    // Track if already selected in THIS choice group
                    if (in_array($option->proficiency_type_id, $existingProfTypeIdsForGroup)) {
                        $selectedProfTypeIds[] = $option->proficiency_type_id;
                    }
                }
            }

            // For subcategory-based choices (like "artisan tools"), populate options
            // from the proficiency_types lookup table
            if ($proficiencyType && $proficiencySubcategory && empty($allOptions)) {
                $lookupProficiencyTypes = \App\Models\ProficiencyType::where('category', $proficiencyType)
                    ->where('subcategory', $proficiencySubcategory)
                    ->orderBy('name')
                    ->get();

                foreach ($lookupProficiencyTypes as $profType) {
                    $allOptions[] = [
                        'type' => 'proficiency_type',
                        'proficiency_type_id' => $profType->id,
                        'proficiency_type' => [
                            'id' => $profType->id,
                            'name' => $profType->name,
                            'slug' => $profType->slug,
                        ],
                    ];

                    // Track if already selected in THIS choice group
                    if (in_array($profType->id, $existingProfTypeIdsForGroup)) {
                        $selectedProfTypeIds[] = $profType->id;
                    }
                }
            }

            $chosenCount = count($selectedSkillIds) + count($selectedProfTypeIds);
            $remaining = max(0, $quantity - $chosenCount);

            $choices[$groupName] = [
                'proficiency_type' => $proficiencyType,
                'proficiency_subcategory' => $proficiencySubcategory,
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
     * Get all proficiencies for a character, including granted proficiencies from class, race, and background.
     *
     * This aggregates:
     * - Stored proficiencies from character_proficiencies table
     * - Granted fixed proficiencies from primary class
     * - Granted fixed proficiencies from race (including parent race for subraces)
     * - Granted fixed proficiencies from background
     *
     * Proficiencies are deduplicated, preferring stored ones (which have an ID).
     *
     * @return \Illuminate\Support\Collection
     */
    public function getCharacterProficiencies(Character $character)
    {
        // Load relationships we'll need
        $character->loadMissing(['characterClasses.characterClass', 'race.parent', 'background']);

        // Get stored proficiencies
        $storedProficiencies = $character->proficiencies()
            ->with(['skill', 'proficiencyType'])
            ->get();

        // Collect granted proficiencies from all sources
        $grantedProficiencies = $this->collectGrantedProficiencies($character);

        // Merge and deduplicate, preferring stored ones
        return $this->mergeAndDeduplicate($storedProficiencies, $grantedProficiencies);
    }

    /**
     * Collect granted (fixed) proficiencies from class, race, and background.
     */
    private function collectGrantedProficiencies(Character $character): \Illuminate\Support\Collection
    {
        $proficiencies = collect();

        // From primary class
        $primaryClass = $character->primary_class;
        if ($primaryClass) {
            $proficiencies = $proficiencies->merge(
                $this->getEntityGrantedProficiencies($primaryClass, 'class')
            );
        }

        // From race (including parent if subrace)
        if ($character->race) {
            $race = $character->race;
            if ($race->is_subrace && $race->parent) {
                $proficiencies = $proficiencies->merge(
                    $this->getEntityGrantedProficiencies($race->parent, 'race')
                );
            }
            $proficiencies = $proficiencies->merge(
                $this->getEntityGrantedProficiencies($race, 'race')
            );
        }

        // From background
        if ($character->background) {
            $proficiencies = $proficiencies->merge(
                $this->getEntityGrantedProficiencies($character->background, 'background')
            );
        }

        return $proficiencies;
    }

    /**
     * Get fixed (non-choice) proficiencies from an entity and convert to CharacterProficiency-like objects.
     *
     * @param  mixed  $entity  The source entity (CharacterClass, Race, Background)
     * @param  string  $source  The source identifier ('class', 'race', 'background')
     */
    private function getEntityGrantedProficiencies($entity, string $source): \Illuminate\Support\Collection
    {
        return $entity->proficiencies()
            ->where('is_choice', false)
            ->with(['skill', 'proficiencyType'])
            ->get()
            ->map(function ($proficiency) use ($source) {
                // Create a CharacterProficiency-like object with null ID to indicate it's granted
                $charProf = new CharacterProficiency([
                    'proficiency_type_id' => $proficiency->proficiency_type_id,
                    'skill_id' => $proficiency->skill_id,
                    'source' => $source,
                    'expertise' => false,
                ]);
                // Manually set ID to null (not persisted)
                $charProf->id = null;
                // Copy relationships
                $charProf->setRelations([
                    'skill' => $proficiency->skill,
                    'proficiencyType' => $proficiency->proficiencyType,
                ]);

                return $charProf;
            });
    }

    /**
     * Merge stored and granted proficiencies, deduplicating by skill_id or proficiency_type_id.
     * Prefers stored proficiencies (which have an ID).
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $stored
     * @param  \Illuminate\Support\Collection  $granted
     */
    private function mergeAndDeduplicate($stored, $granted): \Illuminate\Support\Collection
    {
        // Create lookup sets for stored proficiencies
        $storedSkillIds = $stored->whereNotNull('skill_id')->pluck('skill_id')->toArray();
        $storedProfTypeIds = $stored->whereNotNull('proficiency_type_id')->pluck('proficiency_type_id')->toArray();

        // Filter granted to exclude duplicates
        $filteredGranted = $granted->filter(function ($prof) use ($storedSkillIds, $storedProfTypeIds) {
            if ($prof->skill_id !== null) {
                return ! in_array($prof->skill_id, $storedSkillIds);
            }
            if ($prof->proficiency_type_id !== null) {
                return ! in_array($prof->proficiency_type_id, $storedProfTypeIds);
            }

            return true;
        });

        // Use concat instead of merge to avoid Eloquent Collection key collision
        // (Eloquent Collection's merge uses model keys, and null-ID models all have null keys)
        return $stored->concat($filteredGranted)->values();
    }
}
