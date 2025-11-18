<?php

namespace Database\Factories;

use App\Models\CharacterTrait;
use App\Models\RandomTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RandomTable>
 */
class RandomTableFactory extends Factory
{
    protected $model = RandomTable::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Default to CharacterTrait as reference type
        $trait = CharacterTrait::factory()->create();

        return [
            'reference_type' => CharacterTrait::class,
            'reference_id' => $trait->id,
            'table_name' => fake()->words(3, true),
            'dice_type' => fake()->randomElement(['d4', 'd6', 'd8', 'd10', 'd12', 'd20']),
            'description' => fake()->sentence(),
        ];
    }

    /**
     * Set the random table to belong to a specific entity.
     */
    public function forEntity(string $referenceType, int $referenceId): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }
}
