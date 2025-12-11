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

        // Check subclass feature choices
        $primaryClassPivot = $character->characterClasses->first();
        if ($primaryClassPivot && $primaryClassPivot->subclass) {
            // Eager load features with proficiencies to avoid N+1 queries
            $subclass = $primaryClassPivot->subclass->load('features.proficiencies.skill', 'features.proficiencies.proficiencyType');
            $subclassChoices = [];
            foreach ($subclass->features as $feature) {
                $featureChoices = $this->getChoicesFromEntity(
                    $feature,
                    $character,
                    'subclass_feature'
                );
                foreach ($featureChoices as $group => $data) {
                    $key = $feature->feature_name.':'.$group;
                    $subclassChoices[$key] = $data;
                }
            }
            if (! empty($subclassChoices)) {
                $choices['subclass_feature'] = $subclassChoices;
            }
        }

        return $choices;
    }

    /**
     * Make a skill choice for a character.
     *
     * @param  array<string>  $skillSlugs  The skill slugs the user chose
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
            CharacterSource::SUBCLASS_FEATURE => $this->getSubclassFeatureEntity($character, $choiceGroup),
            default => null,
        };

        if (! $entity) {
            throw new InvalidArgumentException("Character has no {$source} assigned");
        }

        // For subclass_feature, extract base choice group for DB lookup
        // (choice_group in DB is "feature_skill_choice_1", not "FeatureName:feature_skill_choice_1")
        $lookupChoiceGroup = $sourceEnum === CharacterSource::SUBCLASS_FEATURE
            ? $this->extractBaseChoiceGroup($choiceGroup)
            : $choiceGroup;

        // Get the choice options from the entity
        $choiceOptions = $entity->proficiencies()
            ->where('is_choice', true)
            ->where('choice_group', $lookupChoiceGroup)
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
        $validSkillSlugs = $choiceOptions->pluck('skill.slug')->filter()->toArray();
        foreach ($skillSlugs as $skillSlug) {
            if (! in_array($skillSlug, $validSkillSlugs)) {
                throw new InvalidArgumentException("Skill slug {$skillSlug} is not a valid option for this choice");
            }
        }

        // For subclass_feature, use base choice group for storage to match read queries
        // Other sources use the choice group as-is
        $storageChoiceGroup = $sourceEnum === CharacterSource::SUBCLASS_FEATURE
            ? $lookupChoiceGroup
            : $choiceGroup;

        // Clear existing choices for this source + choice_group before adding new ones
        // This ensures re-submitting replaces rather than adds
        $character->proficiencies()
            ->where('source', $source)
            ->where('choice_group', $storageChoiceGroup)
            ->delete();

        // Create the proficiencies
        foreach ($skillSlugs as $skillSlug) {
            CharacterProficiency::create([
                'character_id' => $character->id,
                'skill_slug' => $skillSlug,
                'source' => $source,
                'choice_group' => $storageChoiceGroup,
            ]);
        }

        // Refresh the relationship
        $character->load('proficiencies');
    }

    /**
     * Make a proficiency type choice for a character (tools, weapons, armor, etc.).
     *
     * @param  array<string>  $proficiencyTypeSlugs  The proficiency type slugs the user chose
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
            CharacterSource::SUBCLASS_FEATURE => $this->getSubclassFeatureEntity($character, $choiceGroup),
            default => null,
        };

        if (! $entity) {
            throw new InvalidArgumentException("Character has no {$source} assigned");
        }

        // For subclass_feature, extract base choice group for DB lookup
        // (choice_group in DB is "feature_skill_choice_1", not "FeatureName:feature_skill_choice_1")
        $lookupChoiceGroup = $sourceEnum === CharacterSource::SUBCLASS_FEATURE
            ? $this->extractBaseChoiceGroup($choiceGroup)
            : $choiceGroup;

        // Get the choice definition from the entity
        $choiceOptions = $entity->proficiencies()
            ->where('is_choice', true)
            ->where('choice_group', $lookupChoiceGroup)
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
                ->pluck('slug')
                ->toArray();

            foreach ($proficiencyTypeSlugs as $proficiencyTypeSlug) {
                if (! in_array($proficiencyTypeSlug, $validProficiencyTypeSlugs)) {
                    throw new InvalidArgumentException("Proficiency type slug {$proficiencyTypeSlug} is not a valid option for this choice");
                }
            }
        } else {
            // For specific option choices, validate against the specific proficiency_type slugs in the choice
            $validProficiencyTypeSlugs = $choiceOptions->pluck('proficiencyType.slug')->filter()->toArray();
            foreach ($proficiencyTypeSlugs as $proficiencyTypeSlug) {
                if (! in_array($proficiencyTypeSlug, $validProficiencyTypeSlugs)) {
                    throw new InvalidArgumentException("Proficiency type slug {$proficiencyTypeSlug} is not a valid option for this choice");
                }
            }
        }

        // For subclass_feature, use base choice group for storage to match read queries
        // Other sources use the choice group as-is
        $storageChoiceGroup = $sourceEnum === CharacterSource::SUBCLASS_FEATURE
            ? $lookupChoiceGroup
            : $choiceGroup;

        // Clear existing choices for this source + choice_group before adding new ones
        $character->proficiencies()
            ->where('source', $source)
            ->where('choice_group', $storageChoiceGroup)
            ->delete();

        // Create the proficiencies
        foreach ($proficiencyTypeSlugs as $proficiencyTypeSlug) {
            CharacterProficiency::create([
                'character_id' => $character->id,
                'proficiency_type_slug' => $proficiencyTypeSlug,
                'source' => $source,
                'choice_group' => $storageChoiceGroup,
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
            $skillSlug = $proficiency->skill?->slug;
            $proficiencyTypeSlug = $proficiency->proficiencyType?->slug;

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
     * Get subclass feature entity by choice group.
     *
     * The choice group for subclass_feature is formatted as "FeatureName (Subclass):base_choice_group"
     * We need to extract just the base_choice_group for the lookup.
     */
    private function getSubclassFeatureEntity(Character $character, string $choiceGroup): ?\App\Models\ClassFeature
    {
        $subclass = $character->characterClasses->first()?->subclass;
        if (! $subclass) {
            return null;
        }

        $baseChoiceGroup = $this->extractBaseChoiceGroup($choiceGroup);

        return $subclass->getFeatureByProficiencyChoiceGroup($baseChoiceGroup);
    }

    /**
     * Extract the base choice group from a potentially prefixed choice group.
     *
     * For subclass_feature, choice groups are formatted as "FeatureName (Subclass):base_choice_group"
     * This extracts just the "base_choice_group" part.
     */
    private function extractBaseChoiceGroup(string $choiceGroup): string
    {
        if (str_contains($choiceGroup, ':')) {
            return substr($choiceGroup, strrpos($choiceGroup, ':') + 1);
        }

        return $choiceGroup;
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
                        'skill_slug' => $option->skill->slug,
                        'skill' => [
                            'slug' => $option->skill->slug,
                            'name' => $option->skill->name,
                        ],
                    ];

                    // Track if already selected in THIS choice group
                    if (in_array($option->skill->slug, $existingSkillSlugsForGroup)) {
                        $selectedSkillSlugs[] = $option->skill->slug;
                    }
                } elseif ($option->proficiencyType) {
                    // Always add to options (don't filter out selected)
                    $allOptions[] = [
                        'type' => 'proficiency_type',
                        'proficiency_type_slug' => $option->proficiencyType->slug,
                        'proficiency_type' => [
                            'slug' => $option->proficiencyType->slug,
                            'name' => $option->proficiencyType->name,
                        ],
                    ];

                    // Track if already selected in THIS choice group
                    if (in_array($option->proficiencyType->slug, $existingProfTypeSlugsForGroup)) {
                        $selectedProfTypeSlugs[] = $option->proficiencyType->slug;
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
                        'proficiency_type_slug' => $profType->slug,
                        'proficiency_type' => [
                            'slug' => $profType->slug,
                            'name' => $profType->name,
                        ],
                    ];

                    // Track if already selected in THIS choice group
                    if (in_array($profType->slug, $existingProfTypeSlugsForGroup)) {
                        $selectedProfTypeSlugs[] = $profType->slug;
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
     * Collect granted (fixed) proficiencies from class, subclass, race, and background.
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

        // From subclass (e.g., Life Domain grants heavy armor)
        $primaryClassPivot = $character->characterClasses->first();
        if ($primaryClassPivot && $primaryClassPivot->subclass) {
            $proficiencies = $proficiencies->merge(
                $this->getEntityGrantedProficiencies($primaryClassPivot->subclass, 'class')
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
            ->whereNotIn('proficiency_type', ['saving_throw', 'multiclass_requirement'])
            ->with(['skill', 'proficiencyType'])
            ->get()
            ->map(function ($proficiency) use ($source) {
                // Get proficiency type - either from relationship or by looking up by name
                $proficiencyType = $proficiency->proficiencyType;
                $proficiencyTypeSlug = $proficiencyType?->slug;

                // If no linked proficiency type but we have a name, look it up
                // This handles imported data that uses proficiency_name text instead of proficiency_type_id FK
                if (! $proficiencyTypeSlug && $proficiency->proficiency_name) {
                    $proficiencyType = \App\Models\ProficiencyType::where('name', 'like', $proficiency->proficiency_name)->first();
                    $proficiencyTypeSlug = $proficiencyType?->slug;
                }

                // Create a CharacterProficiency-like object with null ID to indicate it's granted
                $charProf = new CharacterProficiency([
                    'proficiency_type_slug' => $proficiencyTypeSlug,
                    'skill_slug' => $proficiency->skill?->slug,
                    'source' => $source,
                    'expertise' => false,
                ]);
                // Manually set ID to null (not persisted)
                $charProf->id = null;
                // Copy relationships
                $charProf->setRelations([
                    'skill' => $proficiency->skill,
                    'proficiencyType' => $proficiencyType,
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

        // Track already-seen slugs within granted proficiencies to deduplicate
        // (e.g., when class and subclass both grant heavy armor)
        $seenSkillSlugs = $storedSkillSlugs;
        $seenProfTypeSlugs = $storedProfTypeSlugs;

        // Filter granted to exclude duplicates (from stored AND from earlier granted)
        $filteredGranted = $granted->filter(function ($prof) use (&$seenSkillSlugs, &$seenProfTypeSlugs) {
            if ($prof->skill_slug !== null) {
                if (in_array($prof->skill_slug, $seenSkillSlugs)) {
                    return false;
                }
                $seenSkillSlugs[] = $prof->skill_slug;

                return true;
            }
            if ($prof->proficiency_type_slug !== null) {
                if (in_array($prof->proficiency_type_slug, $seenProfTypeSlugs)) {
                    return false;
                }
                $seenProfTypeSlugs[] = $prof->proficiency_type_slug;

                return true;
            }

            return true;
        });

        // Use concat instead of merge to avoid Eloquent Collection key collision
        // (Eloquent Collection's merge uses model keys, and null-ID models all have null keys)
        return $stored->concat($filteredGranted)->values();
    }
}
