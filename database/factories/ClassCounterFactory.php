<?php

namespace Database\Factories;

use App\Models\CharacterClass;
use App\Models\ClassCounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassCounter>
 */
class ClassCounterFactory extends Factory
{
    protected $model = ClassCounter::class;

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
            'counter_name' => ucwords(fake()->words(2, true)),
            'counter_value' => fake()->numberBetween(1, 10),
            'reset_timing' => fake()->randomElement(['S', 'L', null]),
        ];
    }

    /**
     * Set the counter to belong to a specific class.
     */
    public function forClass(CharacterClass $class): static
    {
        return $this->state(fn (array $attributes) => [
            'class_id' => $class->id,
        ]);
    }

    /**
     * Set a specific level for the counter.
     */
    public function atLevel(int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => $level,
        ]);
    }

    /**
     * Set the counter to reset on short rest.
     */
    public function shortRest(): static
    {
        return $this->state(fn (array $attributes) => [
            'reset_timing' => 'S',
        ]);
    }

    /**
     * Set the counter to reset on long rest.
     */
    public function longRest(): static
    {
        return $this->state(fn (array $attributes) => [
            'reset_timing' => 'L',
        ]);
    }

    /**
     * Set the counter to not reset.
     */
    public function noReset(): static
    {
        return $this->state(fn (array $attributes) => [
            'reset_timing' => null,
        ]);
    }
}
