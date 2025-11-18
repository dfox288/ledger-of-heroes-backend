<?php

namespace Database\Factories;

use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RandomTableEntry>
 */
class RandomTableEntryFactory extends Factory
{
    protected $model = RandomTableEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'random_table_id' => RandomTable::factory(),
            'roll_value' => fake()->numberBetween(1, 6),
            'result' => fake()->sentence(),
            'sort_order' => 0,
        ];
    }

    /**
     * Set the entry to belong to a specific random table.
     */
    public function forTable(RandomTable $table): static
    {
        return $this->state(fn (array $attributes) => [
            'random_table_id' => $table->id,
        ]);
    }
}
