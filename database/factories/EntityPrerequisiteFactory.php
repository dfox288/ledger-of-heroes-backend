<?php

namespace Database\Factories;

use App\Models\AbilityScore;
use App\Models\EntityPrerequisite;
use App\Models\Feat;
use App\Models\ProficiencyType;
use App\Models\Race;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntityPrerequisiteFactory extends Factory
{
    protected $model = EntityPrerequisite::class;

    public function definition(): array
    {
        return [
            'reference_type' => Feat::class,
            'reference_id' => 1,
            'prerequisite_type' => null,
            'prerequisite_id' => null,
            'minimum_value' => null,
            'description' => null,
            'group_id' => 1,
        ];
    }

    /**
     * Set the entity that HAS this prerequisite.
     */
    public function forEntity(string $entityClass, int $entityId): static
    {
        return $this->state([
            'reference_type' => $entityClass,
            'reference_id' => $entityId,
        ]);
    }

    /**
     * Ability score prerequisite (e.g., "Strength 13 or higher").
     */
    public function abilityScore(string $abilityCode, int $minimumValue): static
    {
        $abilityScore = AbilityScore::where('code', $abilityCode)->first();

        return $this->state([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $abilityScore->id,
            'minimum_value' => $minimumValue,
        ]);
    }

    /**
     * Race prerequisite (e.g., "Elf").
     */
    public function race(int $raceId): static
    {
        return $this->state([
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $raceId,
        ]);
    }

    /**
     * Proficiency prerequisite (e.g., "Proficiency with medium armor").
     */
    public function proficiency(int $proficiencyTypeId): static
    {
        return $this->state([
            'prerequisite_type' => ProficiencyType::class,
            'prerequisite_id' => $proficiencyTypeId,
        ]);
    }

    /**
     * Free-form feature prerequisite (e.g., "Spellcasting feature").
     */
    public function feature(string $description): static
    {
        return $this->state([
            'prerequisite_type' => null,
            'prerequisite_id' => null,
            'description' => $description,
        ]);
    }

    /**
     * Set the group ID for AND/OR logic.
     */
    public function inGroup(int $groupId): static
    {
        return $this->state([
            'group_id' => $groupId,
        ]);
    }
}
