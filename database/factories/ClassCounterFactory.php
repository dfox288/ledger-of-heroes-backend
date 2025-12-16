<?php

namespace Database\Factories;

use App\Models\CharacterClass;
use App\Models\ClassCounter;
use App\Models\Feat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @deprecated Use EntityCounterFactory instead.
 *
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
            'reference_type' => CharacterClass::class,
            'reference_id' => CharacterClass::factory(),
            'level' => fake()->numberBetween(1, 20),
            'counter_name' => ucwords(fake()->words(2, true)),
            'counter_value' => fake()->numberBetween(1, 10),
            'reset_timing' => fake()->randomElement(['S', 'L', null]),
        ];
    }

    /**
     * Configure the factory to handle legacy attributes.
     *
     * This converts class_id/feat_id to reference_type/reference_id for backwards compatibility.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ClassCounter $counter) {
            // Don't modify if we already have a valid reference
        })->afterCreating(function (ClassCounter $counter) {
            // Nothing to do after creation
        });
    }

    /**
     * Create a new factory instance with state transformations.
     * Handles legacy class_id/feat_id attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function state($state): static
    {
        // Transform legacy attributes
        if (is_array($state)) {
            if (isset($state['class_id'])) {
                $state['reference_type'] = CharacterClass::class;
                $state['reference_id'] = $state['class_id'];
                unset($state['class_id']);
            }
            if (isset($state['feat_id'])) {
                $state['reference_type'] = Feat::class;
                $state['reference_id'] = $state['feat_id'];
                unset($state['feat_id']);
            }
        }

        return parent::state($state);
    }

    /**
     * Override create to handle legacy attributes in raw array.
     *
     * @param  array<string, mixed>|callable  $attributes
     */
    public function create($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
    {
        // Transform legacy attributes if passed as array
        if (is_array($attributes)) {
            if (isset($attributes['class_id'])) {
                $attributes['reference_type'] = CharacterClass::class;
                $attributes['reference_id'] = $attributes['class_id'];
                unset($attributes['class_id']);
            }
            if (isset($attributes['feat_id'])) {
                $attributes['reference_type'] = Feat::class;
                $attributes['reference_id'] = $attributes['feat_id'];
                unset($attributes['feat_id']);
            }
        }

        return parent::create($attributes, $parent);
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
            'level' => 1,
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
