<?php

namespace Database\Factories;

use App\Models\CreatureType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CreatureType>
 */
class CreatureTypeFactory extends Factory
{
    protected $model = CreatureType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(2),
            'name' => $this->faker->word(),
            'typically_immune_to_poison' => false,
            'typically_immune_to_charmed' => false,
            'typically_immune_to_frightened' => false,
            'typically_immune_to_exhaustion' => false,
            'requires_sustenance' => true,
            'requires_sleep' => true,
            'description' => $this->faker->sentence(),
        ];
    }

    /**
     * Create an undead creature type.
     */
    public function undead(): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => 'undead',
            'name' => 'Undead',
            'typically_immune_to_poison' => true,
            'typically_immune_to_charmed' => true,
            'typically_immune_to_exhaustion' => true,
            'requires_sustenance' => false,
            'requires_sleep' => false,
        ]);
    }

    /**
     * Create a construct creature type.
     */
    public function construct(): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => 'construct',
            'name' => 'Construct',
            'typically_immune_to_poison' => true,
            'typically_immune_to_charmed' => true,
            'typically_immune_to_frightened' => true,
            'typically_immune_to_exhaustion' => true,
            'requires_sustenance' => false,
            'requires_sleep' => false,
        ]);
    }
}
