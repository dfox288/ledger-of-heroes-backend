<?php

namespace Database\Factories;

use App\Models\Sense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sense>
 */
class SenseFactory extends Factory
{
    protected $model = Sense::class;

    public function definition(): array
    {
        $senseTypes = ['darkvision', 'blindsight', 'tremorsense', 'truesight'];
        $slug = $this->faker->unique()->randomElement($senseTypes);

        return [
            'slug' => $slug,
            'name' => ucfirst($slug),
        ];
    }

    /**
     * Create a darkvision sense.
     */
    public function darkvision(): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => 'darkvision',
            'name' => 'Darkvision',
        ]);
    }

    /**
     * Create a blindsight sense.
     */
    public function blindsight(): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => 'blindsight',
            'name' => 'Blindsight',
        ]);
    }
}
