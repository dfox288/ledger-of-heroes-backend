<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterClassPivot>
 */
class CharacterClassPivotFactory extends Factory
{
    protected $model = CharacterClassPivot::class;

    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'class_slug' => fn () => CharacterClass::factory()->state(['parent_class_id' => null])->create()->slug,
            'subclass_slug' => null,
            'level' => $this->faker->numberBetween(1, 20),
            'is_primary' => true,
            'order' => 1,
            'hit_dice_spent' => 0,
        ];
    }

    /**
     * Configure as a secondary (multiclass) class.
     */
    public function secondary(int $order = 2): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => false,
            'order' => $order,
        ]);
    }

    /**
     * Set a specific level.
     */
    public function level(int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => $level,
        ]);
    }

    /**
     * Set spent hit dice.
     */
    public function withSpentHitDice(int $spent): static
    {
        return $this->state(fn (array $attributes) => [
            'hit_dice_spent' => $spent,
        ]);
    }
}
