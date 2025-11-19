<?php

namespace Database\Factories;

use App\Models\EntityCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EntityCondition>
 */
class EntityConditionFactory extends Factory
{
    protected $model = EntityCondition::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_type' => 'App\Models\Feat',
            'reference_id' => 1,
            'condition_id' => null,
            'effect_type' => fake()->randomElement(['advantage', 'disadvantage', 'immunity', 'resistance']),
            'description' => fake()->sentence(),
        ];
    }

    /**
     * Create a condition for a specific entity.
     */
    public function forEntity(string $entityClass, int $entityId): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_type' => $entityClass,
            'reference_id' => $entityId,
        ]);
    }
}
