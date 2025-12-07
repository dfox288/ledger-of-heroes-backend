<?php

namespace App\Http\Resources;

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
            'item' => $this->when($this->item_slug !== null, fn () => $this->formatEntityWith(
                $this->item,
                ['id', 'name', 'slug', 'armor_class', 'damage_dice', 'weight'],
                ['item_type' => fn ($item) => $item->itemType?->name]
            )),
            'custom_name' => $this->custom_name,
            'custom_description' => $this->custom_description,
            'quantity' => $this->quantity,
            'equipped' => $this->equipped,
            'location' => $this->location,
            'proficiency_status' => $this->when(
                $this->equipped && $this->item_slug !== null,
                fn () => $this->getProficiencyStatus()
            ),
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
}
