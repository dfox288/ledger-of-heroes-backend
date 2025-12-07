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
     * @param  array<string>  $skillSlugs  The skill full_slugs the user chose
     *
     * @throws InvalidArgumentException
     */
    public function makeSkillChoice(Character $character, string $source, string $choiceGroup, array $skillSlugs): void
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

        if (count($skillSlugs) !== $quantity) {
            throw new InvalidArgumentException(
                "Must choose exactly {$quantity} skills, got ".count($skillSlugs)
            );
        }

        // Validate all selected skills are valid options
        $validSkillSlugs = $choiceOptions->pluck('skill.full_slug')->filter()->toArray();
        foreach ($skillSlugs as $skillSlug) {
            if (! in_array($skillSlug, $validSkillSlugs)) {
                throw new InvalidArgumentException("Skill slug {$skillSlug} is not a valid option for this choice");
            }
        }

        // Clear existing choices for this source + choice_group before adding new ones
        // This ensures re-submitting replaces rather than adds
        $character->proficiencies()
            ->where('source', $source)
            ->where('choice_group', $choiceGroup)
            ->delete();

        // Create the proficiencies
        foreach ($skillSlugs as $skillSlug) {
            CharacterProficiency::create([
                'character_id' => $character->id,
                'skill_slug' => $skillSlug,
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
     * @param  array<string>  $proficiencyTypeSlugs  The proficiency type full_slugs the user chose
     *
     * @throws InvalidArgumentException
     */
    public function makeProficiencyTypeChoice(Character $character, string $source, string $choiceGroup, array $proficiencyTypeSlugs): void
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

        if (count($proficiencyTypeSlugs) !== $quantity) {
            throw new InvalidArgumentException(
                "Must choose exactly {$quantity} proficiency types, got ".count($proficiencyTypeSlugs)
            );
        }

        // Validate selected proficiency types against choice constraints
        // For subcategory-based choices (e.g., "artisan tools"), validate against category/subcategory
        if ($proficiencySubcategory) {
            // Lookup valid proficiency type slugs from ProficiencyType model
            $validProficiencyTypeSlugs = \App\Models\ProficiencyType::where('category', $proficiencyType)
                ->where('subcategory', $proficiencySubcategory)
                ->pluck('full_slug')
                ->toArray();

            foreach ($proficiencyTypeSlugs as $proficiencyTypeSlug) {
                if (! in_array($proficiencyTypeSlug, $validProficiencyTypeSlugs)) {
                    throw new InvalidArgumentException("Proficiency type slug {$proficiencyTypeSlug} is not a valid option for this choice");
                }
            }
        } else {
            // For specific option choices, validate against the specific proficiency_type slugs in the choice
            $validProficiencyTypeSlugs = $choiceOptions->pluck('proficiencyType.full_slug')->filter()->toArray();
            foreach ($proficiencyTypeSlugs as $proficiencyTypeSlug) {
                if (! in_array($proficiencyTypeSlug, $validProficiencyTypeSlugs)) {
                    throw new InvalidArgumentException("Proficiency type slug {$proficiencyTypeSlug} is not a valid option for this choice");
                }
            }
        }

        // Clear existing choices for this source + choice_group before adding new ones
        $character->proficiencies()
            ->where('source', $source)
            ->where('choice_group', $choiceGroup)
            ->delete();

        // Create the proficiencies
        foreach ($proficiencyTypeSlugs as $proficiencyTypeSlug) {
            CharacterProficiency::create([
                'character_id' => $character->id,
                'proficiency_type_slug' => $proficiencyTypeSlug,
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
            ->with(['skill', 'proficiencyType'])
            ->get();

        foreach ($fixedProficiencies as $proficiency) {
            $skillSlug = $proficiency->skill?->full_slug;
            $proficiencyTypeSlug = $proficiency->proficiencyType?->full_slug;

            // Skip if already exists
            $exists = $character->proficiencies()
                ->where('source', $source)
                ->where(function ($query) use ($skillSlug, $proficiencyTypeSlug) {
                    if ($skillSlug) {
                        $query->where('skill_slug', $skillSlug);
                    } elseif ($proficiencyTypeSlug) {
                        $query->where('proficiency_type_slug', $proficiencyTypeSlug);
                    }
                })
                ->exists();

            if ($exists) {
                continue;
            }

            CharacterProficiency::create([
                'character_id' => $character->id,
                'proficiency_type_slug' => $proficiencyTypeSlug,
                'skill_slug' => $skillSlug,
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
     * @return array<string, array{proficiency_type: string|null, proficiency_subcategory: string|null, quantity: int, remaining: int, selected_skills: array<string>, selected_proficiency_types: array<string>, options: array}>
     */
    private function getChoicesFromEntity($entity, Character $character, string $source): array
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
            $existingSkillSlugsForGroup = $character->proficiencies()
                ->where('source', $source)
                ->where('choice_group', $groupName)
                ->whereNotNull('skill_slug')
                ->pluck('skill_slug')
                ->toArray();

            $existingProfTypeSlugsForGroup = $character->proficiencies()
                ->where('source', $source)
                ->where('choice_group', $groupName)
                ->whereNotNull('proficiency_type_slug')
                ->pluck('proficiency_type_slug')
                ->toArray();

            // Track selected slugs and count
            $selectedSkillSlugs = [];
            $selectedProfTypeSlugs = [];
            $allOptions = [];

            foreach ($options as $option) {
                if ($option->skill) {
                    // Always add to options (don't filter out selected)
                    $allOptions[] = [
                        'type' => 'skill',
                        'skill_slug' => $option->skill->full_slug,
                        'skill' => [
                            'full_slug' => $option->skill->full_slug,
                            'name' => $option->skill->name,
                            'slug' => $option->skill->slug,
                        ],
                    ];

                    // Track if already selected in THIS choice group
                    if (in_array($option->skill->full_slug, $existingSkillSlugsForGroup)) {
                        $selectedSkillSlugs[] = $option->skill->full_slug;
                    }
                } elseif ($option->proficiencyType) {
                    // Always add to options (don't filter out selected)
                    $allOptions[] = [
                        'type' => 'proficiency_type',
                        'proficiency_type_slug' => $option->proficiencyType->full_slug,
                        'proficiency_type' => [
                            'full_slug' => $option->proficiencyType->full_slug,
                            'name' => $option->proficiencyType->name,
                            'slug' => $option->proficiencyType->slug,
                        ],
                    ];

                    // Track if already selected in THIS choice group
                    if (in_array($option->proficiencyType->full_slug, $existingProfTypeSlugsForGroup)) {
                        $selectedProfTypeSlugs[] = $option->proficiencyType->full_slug;
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
                        'proficiency_type_slug' => $profType->full_slug,
                        'proficiency_type' => [
                            'full_slug' => $profType->full_slug,
                            'name' => $profType->name,
                            'slug' => $profType->slug,
                        ],
                    ];

                    // Track if already selected in THIS choice group
                    if (in_array($profType->full_slug, $existingProfTypeSlugsForGroup)) {
                        $selectedProfTypeSlugs[] = $profType->full_slug;
                    }
                }
            }

            $chosenCount = count($selectedSkillSlugs) + count($selectedProfTypeSlugs);
            $remaining = max(0, $quantity - $chosenCount);

            $choices[$groupName] = [
                'proficiency_type' => $proficiencyType,
                'proficiency_subcategory' => $proficiencySubcategory,
                'quantity' => $quantity,
                'remaining' => $remaining,
                'selected_skills' => $selectedSkillSlugs,
                'selected_proficiency_types' => $selectedProfTypeSlugs,
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
                    'proficiency_type_slug' => $proficiency->proficiencyType?->full_slug,
                    'skill_slug' => $proficiency->skill?->full_slug,
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
     * Merge stored and granted proficiencies, deduplicating by skill_slug or proficiency_type_slug.
     * Prefers stored proficiencies (which have an ID).
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $stored
     * @param  \Illuminate\Support\Collection  $granted
     */
    private function mergeAndDeduplicate($stored, $granted): \Illuminate\Support\Collection
    {
        // Create lookup sets for stored proficiencies
        $storedSkillSlugs = $stored->whereNotNull('skill_slug')->pluck('skill_slug')->toArray();
        $storedProfTypeSlugs = $stored->whereNotNull('proficiency_type_slug')->pluck('proficiency_type_slug')->toArray();

        // Filter granted to exclude duplicates
        $filteredGranted = $granted->filter(function ($prof) use ($storedSkillSlugs, $storedProfTypeSlugs) {
            if ($prof->skill_slug !== null) {
                return ! in_array($prof->skill_slug, $storedSkillSlugs);
            }
            if ($prof->proficiency_type_slug !== null) {
                return ! in_array($prof->proficiency_type_slug, $storedProfTypeSlugs);
            }

            return true;
        });

        // Use concat instead of merge to avoid Eloquent Collection key collision
        // (Eloquent Collection's merge uses model keys, and null-ID models all have null keys)
        return $stored->concat($filteredGranted)->values();
    }
}
