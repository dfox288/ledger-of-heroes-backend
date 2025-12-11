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
            'item_slug' => fn () => Item::whereNotNull('slug')->inRandomOrder()->first()?->slug,
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
    public function withItem(Item|string $item): static
    {
        return $this->state([
            'item_slug' => $item instanceof Item ? $item->slug : $item,
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
