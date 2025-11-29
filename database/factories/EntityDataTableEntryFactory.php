<?php

namespace Database\Factories;

use App\Models\EntityDataTable;
use App\Models\EntityDataTableEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EntityDataTableEntry>
 */
class EntityDataTableEntryFactory extends Factory
{
    protected $model = EntityDataTableEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rollValue = fake()->numberBetween(1, 6);

        return [
            'entity_data_table_id' => EntityDataTable::factory(),
            'roll_min' => $rollValue,
            'roll_max' => $rollValue,
            'result_text' => fake()->sentence(),
            'sort_order' => 0,
        ];
    }

    /**
     * Set the entry to belong to a specific data table.
     */
    public function forTable(EntityDataTable $table): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_data_table_id' => $table->id,
        ]);
    }
}
