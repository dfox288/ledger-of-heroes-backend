<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\CharacterProficiency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CharacterProficiency>
 */
class CharacterProficiencyFactory extends Factory
{
    protected $model = CharacterProficiency::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'proficiency_type_slug' => null,
            'skill_slug' => null,
            'source' => fake()->randomElement(['class', 'race', 'background', 'manual']),
            'choice_group' => null,
            'expertise' => false,
        ];
    }

    /**
     * Create a skill proficiency.
     */
    public function skill(string $skillSlug): static
    {
        return $this->state(fn (array $attributes) => [
            'skill_slug' => $skillSlug,
            'proficiency_type_slug' => null,
        ]);
    }

    /**
     * Create a type proficiency.
     */
    public function type(string $typeSlug): static
    {
        return $this->state(fn (array $attributes) => [
            'proficiency_type_slug' => $typeSlug,
            'skill_slug' => null,
        ]);
    }

    /**
     * Add expertise.
     */
    public function withExpertise(): static
    {
        return $this->state(fn (array $attributes) => [
            'expertise' => true,
        ]);
    }
}
