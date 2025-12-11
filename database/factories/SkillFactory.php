<?php

namespace Database\Factories;

use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Skill>
 */
class SkillFactory extends Factory
{
    protected $model = Skill::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $slug = Str::slug($name);

        return [
            'name' => ucwords($name),
            'slug' => 'test:'.$slug,
            'ability_score_id' => null,
        ];
    }

    /**
     * Create a skill with a specific name.
     */
    public function withName(string $name): static
    {
        $slug = Str::slug($name);

        return $this->state(fn (array $attributes) => [
            'name' => $name,
            'slug' => 'test:'.$slug,
        ]);
    }
}
