<?php

namespace App\Http\Resources;

use App\Enums\ItemGroup;
use App\Http\Resources\Concerns\FormatsRelatedModels;
use App\Services\ProficiencyCheckerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CharacterEquipment
 */
class CharacterEquipmentResource extends JsonResource
{
    use FormatsRelatedModels;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item' => $this->when($this->item_slug !== null, fn () => $this->item
                ? $this->formatEntityWith(
                    $this->item,
                    ['id', 'name', 'slug', 'armor_class', 'damage_dice', 'weight', 'requires_attunement'],
                    ['item_type' => fn ($item) => $item->itemType?->name]
                )
                : null  // Dangling reference - item_slug set but item doesn't exist
            ),
            'item_slug' => $this->item_slug,
            'is_dangling' => $this->item_slug !== null && $this->item === null,
            'custom_name' => $this->custom_name,
            'custom_description' => $this->custom_description,
            'quantity' => $this->quantity,
            'equipped' => $this->equipped,
            'location' => $this->location,
            'is_attuned' => $this->is_attuned,
            'proficiency_status' => $this->when(
                $this->equipped && $this->item !== null,
                fn () => $this->getProficiencyStatus()
            ),
            'group' => $this->getItemGroup(),
        ];
    }

    /**
     * Get the proficiency status for this equipped item.
     *
     * @return array<string, mixed>
     */
    private function getProficiencyStatus(): array
    {
        $checker = app(ProficiencyCheckerService::class);

        return $checker->checkEquipmentProficiency(
            $this->character,
            $this->item
        )->toArray();
    }

    /**
     * Get the display group for this item.
     *
     * Groups items by type for frontend inventory organization.
     * Custom items default to Miscellaneous.
     */
    private function getItemGroup(): string
    {
        // Custom items go to Miscellaneous
        if ($this->item_slug === null) {
            return ItemGroup::MISCELLANEOUS->value;
        }

        // Dangling references go to Miscellaneous
        if ($this->item === null) {
            return ItemGroup::MISCELLANEOUS->value;
        }

        // Get group from item type
        $itemTypeName = $this->item->itemType?->name;

        return ItemGroup::fromItemType($itemTypeName)->value;
    }
}
