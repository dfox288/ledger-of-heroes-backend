<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Character;
use App\Models\CharacterCounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterCounter>
 */
class CharacterCounterFactory extends Factory
{
    protected $model = CharacterCounter::class;

    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'source_type' => 'class',
            'source_slug' => 'phb:barbarian',
            'counter_name' => $this->faker->unique()->word().' Counter',
            'current_uses' => null, // null = full
            'max_uses' => 3,
            'reset_timing' => 'L', // L = long rest
        ];
    }

    /**
     * Create an unlimited counter.
     */
    public function unlimited(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_uses' => -1,
            'current_uses' => null,
        ]);
    }

    /**
     * Create a counter from a feat.
     */
    public function fromFeat(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => 'feat',
            'source_slug' => 'phb:lucky',
            'counter_name' => 'Luck Points',
        ]);
    }

    /**
     * Create a counter from a race.
     */
    public function fromRace(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => 'race',
            'source_slug' => 'phb:tiefling',
            'counter_name' => 'Infernal Legacy',
        ]);
    }
}
