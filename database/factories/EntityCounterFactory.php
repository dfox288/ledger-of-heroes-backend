<?php

namespace Database\Factories;

use App\Models\CharacterClass;
use App\Models\CharacterTrait;
use App\Models\EntityCounter;
use App\Models\Feat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EntityCounter>
 */
class EntityCounterFactory extends Factory
{
    protected $model = EntityCounter::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Default to CharacterClass as reference type
        return [
            'reference_type' => CharacterClass::class,
            'reference_id' => CharacterClass::factory(),
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
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
        ]);
    }

    /**
     * Set the counter to belong to a specific feat.
     */
    public function forFeat(Feat $feat): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'level' => 1, // Feats don't have level progression
        ]);
    }

    /**
     * Set the counter to belong to a specific trait.
     */
    public function forTrait(CharacterTrait $trait): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_type' => CharacterTrait::class,
            'reference_id' => $trait->id,
            'level' => 1, // Traits don't have level progression
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
