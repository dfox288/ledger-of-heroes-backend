<?php

namespace Database\Factories;

use App\Enums\AbilityScoreMethod;
use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\Race;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Character>
 */
class CharacterFactory extends Factory
{
    protected $model = Character::class;

    /**
     * Define the model's default state.
     *
     * Wizard-style creation: all optional fields are nullable by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'user_id' => null,
            'level' => 1,
            'experience_points' => 0,
            // All nullable for wizard-style creation
            'race_id' => null,
            'class_id' => null,
            'background_id' => null,
            'strength' => null,
            'dexterity' => null,
            'constitution' => null,
            'intelligence' => null,
            'wisdom' => null,
            'charisma' => null,
            'ability_score_method' => AbilityScoreMethod::Manual,
            'max_hit_points' => null,
            'current_hit_points' => null,
            'temp_hit_points' => 0,
            'armor_class' => null,
        ];
    }

    /**
     * Create a complete character (all required fields set).
     */
    public function complete(): static
    {
        return $this->state(function (array $attributes) {
            $race = Race::whereNull('parent_race_id')->inRandomOrder()->first();
            $class = CharacterClass::whereNull('parent_class_id')->inRandomOrder()->first();
            $background = Background::inRandomOrder()->first();

            return [
                'race_id' => $race?->id,
                'class_id' => $class?->id,
                'background_id' => $background?->id,
                'strength' => fake()->numberBetween(8, 18),
                'dexterity' => fake()->numberBetween(8, 18),
                'constitution' => fake()->numberBetween(8, 18),
                'intelligence' => fake()->numberBetween(8, 18),
                'wisdom' => fake()->numberBetween(8, 18),
                'charisma' => fake()->numberBetween(8, 18),
            ];
        });
    }

    /**
     * Set ability scores explicitly.
     */
    public function withAbilityScores(array $scores): static
    {
        return $this->state(fn (array $attributes) => [
            'strength' => $scores['strength'] ?? $scores['STR'] ?? 10,
            'dexterity' => $scores['dexterity'] ?? $scores['DEX'] ?? 10,
            'constitution' => $scores['constitution'] ?? $scores['CON'] ?? 10,
            'intelligence' => $scores['intelligence'] ?? $scores['INT'] ?? 10,
            'wisdom' => $scores['wisdom'] ?? $scores['WIS'] ?? 10,
            'charisma' => $scores['charisma'] ?? $scores['CHA'] ?? 10,
        ]);
    }

    /**
     * Set character level.
     */
    public function level(int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => $level,
        ]);
    }

    /**
     * Set character race.
     */
    public function withRace(Race|int $race): static
    {
        $raceId = $race instanceof Race ? $race->id : $race;

        return $this->state(fn (array $attributes) => [
            'race_id' => $raceId,
        ]);
    }

    /**
     * Set character class.
     */
    public function withClass(CharacterClass|int $class): static
    {
        $classId = $class instanceof CharacterClass ? $class->id : $class;

        return $this->state(fn (array $attributes) => [
            'class_id' => $classId,
        ]);
    }

    /**
     * Set character background.
     */
    public function withBackground(Background|int $background): static
    {
        $backgroundId = $background instanceof Background ? $background->id : $background;

        return $this->state(fn (array $attributes) => [
            'background_id' => $backgroundId,
        ]);
    }

    /**
     * Set hit points.
     */
    public function withHitPoints(int $max, ?int $current = null): static
    {
        return $this->state(fn (array $attributes) => [
            'max_hit_points' => $max,
            'current_hit_points' => $current ?? $max,
        ]);
    }

    /**
     * Set armor class.
     */
    public function withArmorClass(int $ac): static
    {
        return $this->state(fn (array $attributes) => [
            'armor_class' => $ac,
        ]);
    }
}
