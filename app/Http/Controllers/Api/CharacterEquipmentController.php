<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CharacterEquipment\StoreEquipmentRequest;
use App\Http\Requests\CharacterEquipment\UpdateEquipmentRequest;
use App\Http\Resources\CharacterEquipmentResource;
use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\Item;
use App\Services\EquipmentManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CharacterEquipmentController extends Controller
{
    public function __construct(
        private EquipmentManagerService $equipmentManager
    ) {}

    /**
     * List all equipment for a character.
     */
    public function index(Character $character): AnonymousResourceCollection
    {
        $equipment = $character->equipment()
            ->with('item.itemType')
            ->get();

        return CharacterEquipmentResource::collection($equipment);
    }

    /**
     * Add item to character inventory.
     */
    public function store(StoreEquipmentRequest $request, Character $character): JsonResponse
    {
        if ($request->item_id) {
            // Database item
            $item = Item::findOrFail($request->item_id);
            $equipment = $this->equipmentManager->addItem(
                $character,
                $item,
                $request->quantity ?? 1
            );
            $equipment->load('item.itemType');
        } else {
            // Custom/freetext item
            $equipment = $character->equipment()->create([
                'item_id' => null,
                'custom_name' => $request->custom_name,
                'custom_description' => $request->custom_description,
                'quantity' => $request->quantity ?? 1,
                'equipped' => false,
                'location' => 'backpack',
            ]);
        }

        return (new CharacterEquipmentResource($equipment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update equipment (equip/unequip, change quantity).
     */
    public function update(
        UpdateEquipmentRequest $request,
        Character $character,
        CharacterEquipment $equipment
    ): CharacterEquipmentResource {
        // Verify equipment belongs to character
        if ($equipment->character_id !== $character->id) {
            abort(404);
        }

        if ($request->has('equipped')) {
            if ($request->equipped) {
                // Custom items cannot be equipped
                if ($equipment->isCustomItem()) {
                    abort(422, 'Custom items cannot be equipped.');
                }
                $this->equipmentManager->equipItem($equipment);
            } else {
                $this->equipmentManager->unequipItem($equipment);
            }
        }

        if ($request->has('quantity')) {
            $equipment->update(['quantity' => $request->quantity]);
        }

        if ($equipment->item_id) {
            $equipment->load('item.itemType');
        }

        return new CharacterEquipmentResource($equipment);
    }

    /**
     * Remove item from inventory.
     */
    public function destroy(Character $character, CharacterEquipment $equipment): Response
    {
        // Verify equipment belongs to character
        if ($equipment->character_id !== $character->id) {
            abort(404);
        }

        $this->equipmentManager->removeItem($equipment);

        return response()->noContent();
    }
}
