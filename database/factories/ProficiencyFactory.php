<?php

namespace Database\Factories;

use App\Models\Proficiency;
use App\Models\Race;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Proficiency>
 */
class ProficiencyFactory extends Factory
{
    protected $model = Proficiency::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Default to Race as reference type
        $race = Race::factory()->create();

        return [
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'proficiency_type' => 'other',
            'skill_id' => null,
            'item_id' => null,
            'ability_score_id' => null,
            'proficiency_name' => fake()->words(2, true),
            'is_choice' => false,
            'quantity' => 1,
        ];
    }

    /**
     * Create a skill proficiency.
     */
    public function skill(?string $skillName = null): static
    {
        return $this->state(function (array $attributes) use ($skillName) {
            $name = $skillName ?? fake()->randomElement([
                'Acrobatics', 'Athletics', 'Stealth', 'Perception', 'Investigation',
            ]);
            $skill = Skill::where('name', $name)->first();

            return [
                'proficiency_type' => 'skill',
                'skill_id' => $skill->id,
                'proficiency_name' => null,
            ];
        });
    }

    /**
     * Set the proficiency to belong to a specific entity.
     */
    public function forEntity(string $referenceType, int $referenceId): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Create a choice proficiency (e.g., "one type of artisan's tools").
     */
    public function asChoice(int $quantity = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'is_choice' => true,
            'quantity' => $quantity,
        ]);
    }
}
