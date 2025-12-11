<?php

namespace App\Services;

use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\Feat;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Collection;

class AvailableFeatsService
{
    /**
     * Get feats available for a character based on prerequisites.
     *
     * Returns feats the character qualifies for by checking:
     * - Race prerequisites (including parent race for subraces)
     * - Ability score prerequisites (ASI source only - race source excludes these entirely)
     * - Proficiency prerequisites (skills and proficiency types)
     * - OR group logic (same group_id = any one can satisfy)
     * - AND logic (different group_ids = all must be satisfied)
     *
     * @param  string|null  $source  The feat source: 'race' (Variant Human/Custom Lineage) or 'asi' (level 4+ ASI).
     *                               Race source excludes feats with ability score prerequisites (RAW compliant).
     */
    public function getAvailableFeats(Character $character, ?string $source = null): Collection
    {
        // Load necessary relationships
        $character->loadMissing(['race.parent', 'proficiencies']);

        // Get all feats with their prerequisites
        $feats = Feat::with([
            'prerequisites.prerequisite',
            'sources.source',
            'modifiers',
            'proficiencies',
            'spells',
        ])->get();

        return $feats->filter(function (Feat $feat) use ($character, $source) {
            return $this->characterQualifiesForFeat($character, $feat, $source);
        });
    }

    /**
     * Check if a character qualifies for a specific feat.
     *
     * @param  string|null  $source  'race' excludes ability score prerequisites entirely
     */
    protected function characterQualifiesForFeat(Character $character, Feat $feat, ?string $source = null): bool
    {
        $prerequisites = $feat->prerequisites;

        // No prerequisites = always available
        if ($prerequisites->isEmpty()) {
            return true;
        }

        // For race-granted feats (Variant Human, Custom Lineage), exclude feats
        // with ability score prerequisites entirely - they can't be met before
        // ability scores are assigned (RAW compliant, matches D&D Beyond behavior)
        if ($source === 'race') {
            $hasAbilityScorePrereq = $prerequisites->contains(function ($prereq) {
                return $prereq->prerequisite_type === AbilityScore::class;
            });

            if ($hasAbilityScorePrereq) {
                return false;
            }
        }

        // Group prerequisites by group_id
        // Same group_id = OR logic (any one satisfies)
        // Different group_ids = AND logic (all groups must be satisfied)
        $groups = $prerequisites->groupBy('group_id');

        foreach ($groups as $groupId => $groupPrerequisites) {
            // For this group, at least ONE prerequisite must be satisfied (OR logic within group)
            $groupSatisfied = $groupPrerequisites->contains(function ($prerequisite) use ($character) {
                return $this->checkPrerequisite($character, $prerequisite);
            });

            // If any group fails, character doesn't qualify (AND logic between groups)
            if (! $groupSatisfied) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a character meets a single prerequisite.
     */
    protected function checkPrerequisite(Character $character, $prerequisite): bool
    {
        return match ($prerequisite->prerequisite_type) {
            AbilityScore::class => $this->checkAbilityScorePrerequisite($character, $prerequisite),
            Race::class => $this->checkRacePrerequisite($character, $prerequisite),
            ProficiencyType::class => $this->checkProficiencyTypePrerequisite($character, $prerequisite),
            Skill::class => $this->checkSkillPrerequisite($character, $prerequisite),
            CharacterClass::class => $this->checkClassPrerequisite($character, $prerequisite),
            default => false,
        };
    }

    /**
     * Check ability score prerequisite (e.g., STR 13+).
     */
    protected function checkAbilityScorePrerequisite(Character $character, $prerequisite): bool
    {
        if (! $prerequisite->prerequisite instanceof AbilityScore) {
            return false;
        }

        $abilityScore = $prerequisite->prerequisite;
        $characterScore = $character->getAbilityScore($abilityScore->code);

        if ($characterScore === null) {
            return false;
        }

        $minimumValue = $prerequisite->minimum_value ?? 0;

        return $characterScore >= $minimumValue;
    }

    /**
     * Check race prerequisite (including parent race for subraces).
     *
     * Logic: A subrace character qualifies for feats requiring their parent race.
     * Example: High Elf qualifies for feats requiring "Elf".
     *
     * Note: The reverse is NOT true - a parent race does not qualify for
     * feats requiring a specific subrace. This is intentional per D&D 5e rules.
     */
    protected function checkRacePrerequisite(Character $character, $prerequisite): bool
    {
        if (! $prerequisite->prerequisite instanceof Race) {
            return false;
        }

        $requiredRace = $prerequisite->prerequisite;

        if (! $character->race) {
            return false;
        }

        // Check if character's race matches exactly
        if ($character->race->id === $requiredRace->id) {
            return true;
        }

        // Check if character's parent race matches (subrace qualifies for parent race feats)
        // Example: High Elf (subrace) qualifies for Elf (parent race) feats
        if ($character->race->parent_race_id === $requiredRace->id) {
            return true;
        }

        return false;
    }

    /**
     * Check proficiency type prerequisite (e.g., Medium Armor proficiency).
     */
    protected function checkProficiencyTypePrerequisite(Character $character, $prerequisite): bool
    {
        if (! $prerequisite->prerequisite instanceof ProficiencyType) {
            return false;
        }

        $requiredProficiency = $prerequisite->prerequisite;

        // Check if character has this proficiency type
        return $character->proficiencies()
            ->where('proficiency_type_slug', $requiredProficiency->slug)
            ->exists();
    }

    /**
     * Check skill proficiency prerequisite (e.g., Athletics proficiency).
     */
    protected function checkSkillPrerequisite(Character $character, $prerequisite): bool
    {
        if (! $prerequisite->prerequisite instanceof Skill) {
            return false;
        }

        $requiredSkill = $prerequisite->prerequisite;

        // Check if character has this skill proficiency
        return $character->proficiencies()
            ->where('skill_slug', $requiredSkill->slug)
            ->exists();
    }

    /**
     * Check class prerequisite (e.g., Warlock for Eldritch Adept).
     */
    protected function checkClassPrerequisite(Character $character, $prerequisite): bool
    {
        if (! $prerequisite->prerequisite instanceof CharacterClass) {
            return false;
        }

        $requiredClass = $prerequisite->prerequisite;

        // Check if character has this class
        return $character->characterClasses()
            ->whereHas('characterClass', function ($query) use ($requiredClass) {
                $query->where('id', $requiredClass->id);
            })
            ->exists();
    }
}
