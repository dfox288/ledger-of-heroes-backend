<?php

namespace Database\Factories;

use App\Models\CharacterTrait;
use App\Models\Race;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CharacterTrait>
 */
class CharacterTraitFactory extends Factory
{
    protected $model = CharacterTrait::class;

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
            'name' => fake()->words(3, true),
            'category' => fake()->randomElement(['description', 'feature', 'special']),
            'description' => fake()->paragraphs(2, true),
            'sort_order' => 0,
            'random_table_id' => null,
        ];
    }

    /**
     * Set the trait to belong to a specific entity.
     */
    public function forEntity(string $referenceType, int $referenceId): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }
}
