<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\PrerequisiteResult;
use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\Feat;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Models\Skill;

class PrerequisiteCheckerService
{
    /**
     * Check if a character meets all prerequisites for a feat.
     */
    public function checkFeatPrerequisites(Character $character, Feat $feat): PrerequisiteResult
    {
        $feat->loadMissing('prerequisites.prerequisite');

        $unmet = [];
        $warnings = [];

        foreach ($feat->prerequisites as $prereq) {
            $result = $this->checkSinglePrerequisite($character, $prereq);

            if ($result === null) {
                // Null means we can't validate this prerequisite (text-only)
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
     * @return array{met: bool, type: string, requirement: string, current: string|null}
     */
    private function checkRace(Character $character, $prereq): array
    {
        $requiredRace = $prereq->prerequisite;
        $character->loadMissing('race');

        $characterRace = $character->race;
        $met = $characterRace && $characterRace->id === $requiredRace->id;

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
            ->where('skill_id', $skill->id)
            ->isNotEmpty();

        return [
            'met' => $hasProficiency,
            'type' => 'Skill',
            'requirement' => "{$skill->name} proficiency",
            'current' => $hasProficiency ? 'Has proficiency' : null,
        ];
    }
}
