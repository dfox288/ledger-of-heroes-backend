<?php

namespace Database\Factories;

use App\Enums\DataTableType;
use App\Models\CharacterTrait;
use App\Models\EntityDataTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EntityDataTable>
 */
class EntityDataTableFactory extends Factory
{
    protected $model = EntityDataTable::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Default to CharacterTrait as reference type
        $trait = CharacterTrait::factory()->create();

        return [
            'reference_type' => CharacterTrait::class,
            'reference_id' => $trait->id,
            'table_name' => fake()->words(3, true),
            'dice_type' => fake()->randomElement(['d4', 'd6', 'd8', 'd10', 'd12', 'd20']),
            'table_type' => DataTableType::RANDOM,
            'description' => fake()->sentence(),
        ];
    }

    /**
     * Set the data table to belong to a specific entity.
     */
    public function forEntity(string $referenceType, int $referenceId): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Set the table type.
     */
    public function ofType(DataTableType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'table_type' => $type,
        ]);
    }
}
