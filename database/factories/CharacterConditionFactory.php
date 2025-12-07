<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\CharacterCondition;
use App\Models\Condition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CharacterCondition>
 */
class CharacterConditionFactory extends Factory
{
    protected $model = CharacterCondition::class;

    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'condition_slug' => fn () => Condition::factory()->create()->full_slug,
            'level' => null,
            'source' => fake()->optional()->sentence(3),
            'duration' => fake()->optional()->randomElement(['1 minute', '1 hour', 'Until cured', 'Until long rest']),
        ];
    }

    public function exhaustion(int $level = 1): static
    {
        return $this->state(fn () => [
            'condition_slug' => Condition::firstOrCreate(
                ['slug' => 'exhaustion'],
                ['name' => 'Exhaustion', 'description' => 'Exhaustion condition', 'full_slug' => 'core:exhaustion']
            )->full_slug,
            'level' => $level,
        ]);
    }
}
