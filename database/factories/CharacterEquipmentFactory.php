<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterEquipment>
 */
class CharacterEquipmentFactory extends Factory
{
    protected $model = CharacterEquipment::class;

    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'item_id' => Item::inRandomOrder()->first()?->id ?? 1,
            'quantity' => 1,
            'equipped' => false,
            'location' => 'backpack',
        ];
    }

    /**
     * Mark the item as equipped.
     */
    public function equipped(): static
    {
        return $this->state([
            'equipped' => true,
            'location' => 'equipped',
        ]);
    }

    /**
     * Set specific item.
     */
    public function withItem(Item|int $item): static
    {
        return $this->state([
            'item_id' => $item instanceof Item ? $item->id : $item,
        ]);
    }

    /**
     * Set quantity.
     */
    public function quantity(int $quantity): static
    {
        return $this->state([
            'quantity' => $quantity,
        ]);
    }
}
