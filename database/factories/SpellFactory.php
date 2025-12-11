<?php

namespace Database\Factories;

use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Spell>
 */
class SpellFactory extends Factory
{
    protected $model = Spell::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $schools = ['A', 'C', 'D', 'EN', 'EV', 'I', 'N', 'T'];
        $schoolCode = fake()->randomElement($schools);
        $school = SpellSchool::where('code', $schoolCode)->first();

        $name = fake()->unique()->words(3, true);
        $slug = Str::slug($name);

        return [
            'slug' => 'test:'.$slug,
            'name' => $name,
            'level' => fake()->numberBetween(0, 9),
            'spell_school_id' => $school->id,
            'casting_time' => fake()->randomElement(['1 action', '1 bonus action', '1 reaction', '1 minute', '10 minutes']),
            'range' => fake()->randomElement(['Self', 'Touch', '30 feet', '60 feet', '120 feet', '1 mile']),
            'components' => fake()->randomElement(['V', 'S', 'M', 'V, S', 'V, M', 'S, M', 'V, S, M']),
            'material_components' => fake()->boolean(30) ? fake()->sentence() : null,
            'duration' => fake()->randomElement(['Instantaneous', 'Concentration, up to 1 minute', 'Up to 1 hour', '8 hours', '24 hours']),
            'needs_concentration' => fake()->boolean(30),
            'is_ritual' => fake()->boolean(20),
            'description' => fake()->paragraphs(2, true),
            'higher_levels' => fake()->boolean(40) ? fake()->paragraph() : null,
        ];
    }

    /**
     * Indicate that the spell is a cantrip (level 0).
     */
    public function cantrip(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 0,
        ]);
    }

    /**
     * Indicate that the spell requires concentration.
     */
    public function concentration(): static
    {
        return $this->state(fn (array $attributes) => [
            'needs_concentration' => true,
            'duration' => 'Concentration, up to 1 minute',
        ]);
    }

    /**
     * Indicate that the spell is a ritual.
     */
    public function ritual(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_ritual' => true,
        ]);
    }
}
