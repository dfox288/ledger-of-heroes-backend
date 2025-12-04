<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\CharacterLanguage;
use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CharacterLanguage>
 */
class CharacterLanguageFactory extends Factory
{
    protected $model = CharacterLanguage::class;

    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'language_id' => Language::factory(),
            'source' => fake()->randomElement(['race', 'background', 'feat']),
        ];
    }

    public function fromRace(): static
    {
        return $this->state(fn () => ['source' => 'race']);
    }

    public function fromBackground(): static
    {
        return $this->state(fn () => ['source' => 'background']);
    }

    public function fromFeat(): static
    {
        return $this->state(fn () => ['source' => 'feat']);
    }
}
