<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\PrerequisiteResult;
use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\Feat;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Models\Skill;

class PrerequisiteCheckerService
{
    /**
     * Check if a character meets all prerequisites for a feat.
     *
     * Prerequisites with the same non-null group_id use OR logic (any one satisfies the group).
     * Prerequisites with null group_id are treated as individual requirements (AND logic).
     * Prerequisites with different group_ids use AND logic (all groups must be satisfied).
     */
    public function checkFeatPrerequisites(Character $character, Feat $feat): PrerequisiteResult
    {
        $feat->loadMissing('prerequisites.prerequisite');

        $unmet = [];
        $warnings = [];

        // Separate prerequisites with null group_id (individual AND requirements)
        // from those with non-null group_id (OR groups)
        $individualPrereqs = $feat->prerequisites->whereNull('group_id');
        $groupedPrereqs = $feat->prerequisites->whereNotNull('group_id');

        // Process individual prerequisites (AND logic - all must be met)
        foreach ($individualPrereqs as $prereq) {
            $result = $this->checkSinglePrerequisite($character, $prereq);

            if ($result === null) {
                if ($prereq->description) {
                    $warnings[] = "Cannot validate: {$prereq->description}";
                }

                continue;
            }

            if (! $result['met']) {
                $unmet[] = [
                    'type' => $result['type'],
                    'requirement' => $result['requirement'],
                    'current' => $result['current'],
                ];
            }
        }

        // Process grouped prerequisites (OR logic within groups, AND between groups)
        $groups = $groupedPrereqs->groupBy('group_id');

        foreach ($groups as $groupId => $groupPrerequisites) {
            $groupResults = [];
            $groupSatisfied = false;
            $hasValidatablePrereqs = false;

            foreach ($groupPrerequisites as $prereq) {
                $result = $this->checkSinglePrerequisite($character, $prereq);

                if ($result === null) {
                    if ($prereq->description) {
                        $warnings[] = "Cannot validate: {$prereq->description}";
                    }

                    continue;
                }

                $hasValidatablePrereqs = true;
                $groupResults[] = $result;

                // OR logic: if ANY prerequisite in the group is met, the group is satisfied
                if ($result['met']) {
                    $groupSatisfied = true;
                }
            }

            // If the group has validatable prerequisites and none were met, add to unmet
            if ($hasValidatablePrereqs && ! $groupSatisfied) {
                // For OR groups, report all options that could have satisfied it
                $requirements = collect($groupResults)
                    ->map(fn ($r) => $r['requirement'])
                    ->join(' OR ');

                $unmet[] = [
                    'type' => $groupResults[0]['type'] ?? 'Unknown',
                    'requirement' => $requirements,
                    'current' => $groupResults[0]['current'] ?? null,
                ];
            }
        }

        return new PrerequisiteResult(
            met: empty($unmet),
            unmet: $unmet,
            warnings: $warnings,
        );
    }

    /**
     * Check a single prerequisite.
     *
     * @return array{met: bool, type: string, requirement: string, current: string|int|null}|null
     */
    private function checkSinglePrerequisite(Character $character, $prereq): ?array
    {
        if ($prereq->prerequisite_type === null) {
            return null; // Can't validate text-only prerequisites
        }

        return match ($prereq->prerequisite_type) {
            AbilityScore::class => $this->checkAbilityScore($character, $prereq),
            ProficiencyType::class => $this->checkProficiencyType($character, $prereq),
            Race::class => $this->checkRace($character, $prereq),
            Skill::class => $this->checkSkillProficiency($character, $prereq),
            CharacterClass::class => $this->checkCharacterClass($character, $prereq),
            default => null,
        };
    }

    /**
     * Check ability score prerequisite (e.g., STR 13).
     *
     * @return array{met: bool, type: string, requirement: string, current: int|null}
     */
    private function checkAbilityScore(Character $character, $prereq): array
    {
        $abilityScore = $prereq->prerequisite;
        $requiredValue = $prereq->minimum_value;
        $currentValue = $character->getAbilityScore($abilityScore->code);

        return [
            'met' => $currentValue !== null && $currentValue >= $requiredValue,
            'type' => 'AbilityScore',
            'requirement' => "{$abilityScore->name} {$requiredValue}",
            'current' => $currentValue,
        ];
    }

    /**
     * Check proficiency type prerequisite (e.g., Light Armor proficiency).
     *
     * @return array{met: bool, type: string, requirement: string, current: string|null}
     */
    private function checkProficiencyType(Character $character, $prereq): array
    {
        $proficiencyType = $prereq->prerequisite;
        $hasProficiency = $this->characterHasProficiencyType($character, $proficiencyType);

        return [
            'met' => $hasProficiency,
            'type' => 'ProficiencyType',
            'requirement' => "{$proficiencyType->name} proficiency",
            'current' => $hasProficiency ? 'Has proficiency' : null,
        ];
    }

    /**
     * Check if character has a proficiency type (from class, race, or background).
     */
    private function characterHasProficiencyType(Character $character, ProficiencyType $proficiencyType): bool
    {
        $character->loadMissing(['characterClasses.characterClass.proficiencies', 'race.proficiencies', 'background.proficiencies']);

        $targetName = strtolower($proficiencyType->name);

        // Check class proficiencies (all classes, not just primary)
        foreach ($character->characterClasses as $charClass) {
            if ($charClass->characterClass) {
                foreach ($charClass->characterClass->proficiencies as $prof) {
                    if (strtolower($prof->proficiency_name) === $targetName) {
                        return true;
                    }
                }
            }
        }

        // Check race proficiencies
        if ($character->race) {
            foreach ($character->race->proficiencies as $prof) {
                if (strtolower($prof->proficiency_name) === $targetName) {
                    return true;
                }
            }
        }

        // Check background proficiencies
        if ($character->background) {
            foreach ($character->background->proficiencies as $prof) {
                if (strtolower($prof->proficiency_name) === $targetName) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check race prerequisite (e.g., must be Dragonborn).
     *
     * Subraces qualify for their parent race's prerequisites (e.g., High Elf qualifies for Elf).
     *
     * @return array{met: bool, type: string, requirement: string, current: string|null}
     */
    private function checkRace(Character $character, $prereq): array
    {
        $requiredRace = $prereq->prerequisite;
        $character->loadMissing('race.parent');

        $characterRace = $character->race;

        // Check exact match
        $met = $characterRace && $characterRace->id === $requiredRace->id;

        // Check if subrace matches parent race requirement
        // Example: High Elf (subrace) qualifies for Elf (parent race) feats
        if (! $met && $characterRace && $characterRace->parent_race_id === $requiredRace->id) {
            $met = true;
        }

        return [
            'met' => $met,
            'type' => 'Race',
            'requirement' => $requiredRace->name,
            'current' => $characterRace?->name,
        ];
    }

    /**
     * Check skill proficiency prerequisite (e.g., proficient in Acrobatics).
     *
     * @return array{met: bool, type: string, requirement: string, current: string|null}
     */
    private function checkSkillProficiency(Character $character, $prereq): array
    {
        $skill = $prereq->prerequisite;
        $character->loadMissing('proficiencies');

        $hasProficiency = $character->proficiencies
            ->where('skill_slug', $skill->slug)
            ->isNotEmpty();

        return [
            'met' => $hasProficiency,
            'type' => 'Skill',
            'requirement' => "{$skill->name} proficiency",
            'current' => $hasProficiency ? 'Has proficiency' : null,
        ];
    }

    /**
     * Check character class prerequisite (e.g., must be a Warlock for Eldritch Adept).
     *
     * @return array{met: bool, type: string, requirement: string, current: string|null}
     */
    private function checkCharacterClass(Character $character, $prereq): array
    {
        $requiredClass = $prereq->prerequisite;

        // If prerequisite relationship didn't load, we can't validate
        if ($requiredClass === null) {
            return [
                'met' => false,
                'type' => 'CharacterClass',
                'requirement' => 'Unknown class',
                'current' => null,
            ];
        }

        $character->loadMissing('characterClasses.characterClass');

        // CharacterClassPivot uses class_slug, not character_class_id
        $hasClass = $character->characterClasses
            ->contains(fn ($cc) => $cc->class_slug === $requiredClass->slug);

        $currentClasses = $character->characterClasses
            ->map(fn ($cc) => $cc->characterClass?->name)
            ->filter()
            ->join(', ');

        return [
            'met' => $hasClass,
            'type' => 'CharacterClass',
            'requirement' => $requiredClass->name,
            'current' => $currentClasses ?: null,
        ];
    }
}
