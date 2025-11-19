<?php

namespace Database\Factories;

use App\Models\EntityItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntityItemFactory extends Factory
{
    protected $model = EntityItem::class;

    public function definition(): array
    {
        return [
            'reference_type' => 'App\\Models\\Background',
            'reference_id' => 1,
            'item_id' => null,
            'quantity' => 1,
            'is_choice' => false,
            'choice_description' => null,
        ];
    }

    public function forEntity(string $entityType, int $entityId): self
    {
        return $this->state([
            'reference_type' => $entityType,
            'reference_id' => $entityId,
        ]);
    }

    public function withItem(int $itemId, int $quantity = 1): self
    {
        return $this->state([
            'item_id' => $itemId,
            'quantity' => $quantity,
        ]);
    }

    public function asChoice(string $description): self
    {
        return $this->state([
            'is_choice' => true,
            'choice_description' => $description,
        ]);
    }
}
