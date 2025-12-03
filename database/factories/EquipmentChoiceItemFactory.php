<?php

namespace Database\Factories;

use App\Models\EntityItem;
use App\Models\EquipmentChoiceItem;
use App\Models\Item;
use App\Models\ProficiencyType;
use Illuminate\Database\Eloquent\Factories\Factory;

class EquipmentChoiceItemFactory extends Factory
{
    protected $model = EquipmentChoiceItem::class;

    public function definition(): array
    {
        return [
            'entity_item_id' => EntityItem::factory(),
            'proficiency_type_id' => null,
            'item_id' => null,
            'quantity' => 1,
            'sort_order' => 0,
        ];
    }

    public function forEntityItem(EntityItem $entityItem): self
    {
        return $this->state([
            'entity_item_id' => $entityItem->id,
        ]);
    }

    public function withCategory(string $slug): self
    {
        return $this->state(fn () => [
            'proficiency_type_id' => ProficiencyType::where('slug', $slug)->first()?->id,
            'item_id' => null,
        ]);
    }

    public function withItem(Item $item): self
    {
        return $this->state([
            'proficiency_type_id' => null,
            'item_id' => $item->id,
        ]);
    }

    public function withQuantity(int $quantity): self
    {
        return $this->state([
            'quantity' => $quantity,
        ]);
    }
}
