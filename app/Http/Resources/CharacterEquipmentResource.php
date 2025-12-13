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
            /** @var int Equipment entry ID */
            'id' => $this->id,
            /** @var array{id: int, name: string, slug: string, armor_class: int|null, damage_dice: string|null, weight: float|null, requires_attunement: bool, item_type: string|null}|null Item details (null for custom items) */
            'item' => $this->when($this->item_slug !== null, fn () => $this->item
                ? $this->formatEntityWith(
                    $this->item,
                    ['id', 'name', 'slug', 'armor_class', 'damage_dice', 'weight', 'requires_attunement'],
                    ['item_type' => fn ($item) => $item->itemType?->name]
                )
                : null  // Dangling reference - item_slug set but item doesn't exist
            ),
            /** @var string|null Item slug reference (null for custom items) */
            'item_slug' => $this->item_slug,
            /** @var bool True if item_slug is set but item no longer exists in database */
            'is_dangling' => $this->item_slug !== null && $this->item === null,
            /** @var string|null Custom item name (for non-database items) */
            'custom_name' => $this->custom_name,
            /** @var string|null Custom item description or metadata JSON */
            'custom_description' => $this->custom_description,
            /** @var int Item quantity (stackable items) */
            'quantity' => $this->quantity,
            /** @var bool Whether item is currently equipped */
            'equipped' => $this->equipped,
            /** @var string Equipment slot: main_hand, off_hand, armor, head, neck, cloak, belt, hands, ring_1, ring_2, feet, backpack */
            'location' => $this->location,
            /** @var bool Whether character is attuned to this magic item */
            'is_attuned' => $this->is_attuned,
            /** @var array{is_proficient: bool, reason: string|null}|null Proficiency check result (only for equipped items) */
            'proficiency_status' => $this->when(
                $this->equipped && $this->item !== null,
                fn () => $this->getProficiencyStatus()
            ),
            /** @var string Item group for inventory organization: Weapons, Armor, Potions, etc. */
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
     * Custom items and dangling references default to Miscellaneous.
     */
    private function getItemGroup(): string
    {
        // Custom items or dangling references go to Miscellaneous
        if ($this->item_slug === null || $this->item === null) {
            return ItemGroup::MISCELLANEOUS->value;
        }

        return ItemGroup::fromItemType($this->item->itemType?->name)->value;
    }
}
