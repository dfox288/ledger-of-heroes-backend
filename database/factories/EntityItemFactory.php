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
            // Note: is_choice, choice_description removed - choices now live in entity_choices table
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

    /**
     * @deprecated Choices now live in entity_choices table. Use EntityChoiceFactory instead.
     */
    public function asChoice(string $description = ''): self
    {
        // This method is deprecated - choices should be created using EntityChoiceFactory
        return $this;
    }
}
