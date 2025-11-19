<?php

namespace Database\Factories;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassFeature>
 */
class ClassFeatureFactory extends Factory
{
    protected $model = ClassFeature::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => CharacterClass::factory(),
            'level' => fake()->numberBetween(1, 20),
            'feature_name' => ucwords(fake()->words(fake()->numberBetween(2, 3), true)),
            'is_optional' => false,
            'description' => fake()->paragraph(),
            'sort_order' => 0,
        ];
    }

    /**
     * Set the feature to belong to a specific class.
     */
    public function forClass(CharacterClass $class): static
    {
        return $this->state(fn (array $attributes) => [
            'class_id' => $class->id,
        ]);
    }

    /**
     * Mark the feature as optional.
     */
    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_optional' => true,
        ]);
    }

    /**
     * Set a specific level for the feature.
     */
    public function atLevel(int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => $level,
        ]);
    }
}
