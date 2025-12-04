<?php

namespace Database\Factories;

use App\Enums\AbilityScoreMethod;
use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
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
     * Note: Class is assigned via character_classes junction table, not directly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'user_id' => null,
            'experience_points' => 0,
            // All nullable for wizard-style creation
            'race_id' => null,
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
            'armor_class_override' => null,
        ];
    }

    /**
     * Create a complete character (all required fields set).
     * Creates the character and adds a class via the junction table.
     */
    public function complete(): static
    {
        return $this->state(function (array $attributes) {
            $race = Race::whereNull('parent_race_id')->inRandomOrder()->first();
            $background = Background::inRandomOrder()->first();

            return [
                'race_id' => $race?->id,
                'background_id' => $background?->id,
                'strength' => fake()->numberBetween(8, 18),
                'dexterity' => fake()->numberBetween(8, 18),
                'constitution' => fake()->numberBetween(8, 18),
                'intelligence' => fake()->numberBetween(8, 18),
                'wisdom' => fake()->numberBetween(8, 18),
                'charisma' => fake()->numberBetween(8, 18),
            ];
        })->afterCreating(function (Character $character) {
            $class = CharacterClass::whereNull('parent_class_id')->inRandomOrder()->first();
            if ($class) {
                CharacterClassPivot::create([
                    'character_id' => $character->id,
                    'class_id' => $class->id,
                    'level' => 1,
                    'is_primary' => true,
                    'order' => 1,
                    'hit_dice_spent' => 0,
                ]);
            }
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
     * Add a class to the character via the junction table.
     *
     * If level() was called before withClass(), uses that level.
     * Otherwise uses the $level parameter (default 1).
     */
    public function withClass(CharacterClass|int $class, int $level = 1): static
    {
        return $this->afterCreating(function (Character $character) use ($class, $level) {
            $classId = $class instanceof CharacterClass ? $class->id : $class;
            $isPrimary = $character->characterClasses()->count() === 0;
            $order = ($character->characterClasses()->max('order') ?? 0) + 1;

            CharacterClassPivot::create([
                'character_id' => $character->id,
                'class_id' => $classId,
                'level' => $level,
                'is_primary' => $isPrimary,
                'order' => $order,
                'hit_dice_spent' => 0,
            ]);
        });
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
     * Set armor class override.
     */
    public function withArmorClassOverride(int $ac): static
    {
        return $this->state(fn (array $attributes) => [
            'armor_class_override' => $ac,
        ]);
    }

    /**
     * Create character with valid point buy scores.
     * Uses standard allocation: 15, 14, 13, 12, 10, 8 (exactly 27 points).
     */
    public function withPointBuy(): static
    {
        return $this->state(fn (array $attributes) => [
            'ability_score_method' => AbilityScoreMethod::PointBuy,
            'strength' => 15,
            'dexterity' => 14,
            'constitution' => 13,
            'intelligence' => 12,
            'wisdom' => 10,
            'charisma' => 8,
        ]);
    }

    /**
     * Create character with standard array scores.
     * Uses: 15, 14, 13, 12, 10, 8 assigned in order.
     */
    public function withStandardArray(): static
    {
        return $this->state(fn (array $attributes) => [
            'ability_score_method' => AbilityScoreMethod::StandardArray,
            'strength' => 15,
            'dexterity' => 14,
            'constitution' => 13,
            'intelligence' => 12,
            'wisdom' => 10,
            'charisma' => 8,
        ]);
    }

    /**
     * Set character level (updates the primary class level).
     *
     * If no class exists, automatically creates one with a random base class.
     * If no base classes exist in the database, creates one via the factory.
     */
    public function level(int $level): static
    {
        return $this->afterCreating(function (Character $character) use ($level) {
            $primaryClassPivot = $character->characterClasses()->where('is_primary', true)->first();

            if ($primaryClassPivot) {
                // Update existing class level
                $primaryClassPivot->update(['level' => $level]);
            } else {
                // Create a class with the specified level
                $class = CharacterClass::whereNull('parent_class_id')->first();

                // If no classes exist, create one
                if (! $class) {
                    $class = CharacterClass::factory()->create();
                }

                CharacterClassPivot::create([
                    'character_id' => $character->id,
                    'class_id' => $class->id,
                    'level' => $level,
                    'is_primary' => true,
                    'order' => 1,
                    'hit_dice_spent' => 0,
                ]);
            }
        });
    }
}
