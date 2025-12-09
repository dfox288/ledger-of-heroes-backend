<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CharacterEquipment\CharacterEquipmentStoreRequest;
use App\Http\Requests\CharacterEquipment\CharacterEquipmentUpdateRequest;
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
     * List all equipment for a character
     *
     * Returns all items in the character's inventory, including equipped items,
     * backpack items, and custom/freetext items.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/equipment
     * ```
     *
     * **Response includes:**
     * - Database items with full `item` object (id, name, type, etc.)
     * - Custom items with `custom_name` and `custom_description`
     * - `equipped` status (true/false)
     * - `quantity` for stackable items
     * - `location` for item organization (e.g., "backpack", "belt")
     *
     * **Item Types:**
     * - **Database items** - Reference items from the items table with full stats
     * - **Custom items** - Freetext items (homebrew, quest rewards, notes)
     */
    public function index(Character $character): AnonymousResourceCollection
    {
        $equipment = $character->equipment()
            ->with('item.itemType')
            ->get();

        return CharacterEquipmentResource::collection($equipment);
    }

    /**
     * Add item to character inventory
     *
     * Adds a database item or custom freetext item to the character's inventory.
     * Must provide either `item_slug` (database item) or `custom_name` (freetext item), but not both.
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/equipment
     *
     * # Add a database item (e.g., Longsword)
     * {"item_slug": "phb:longsword", "quantity": 1}
     *
     * # Add multiple of same item (e.g., 50 gold pieces)
     * {"item_slug": "phb:gold-piece", "quantity": 50}
     *
     * # Add a custom/homebrew item
     * {"custom_name": "Ring of Plot Convenience", "custom_description": "A mysterious ring from the DM"}
     *
     * # Add custom item with quantity
     * {"custom_name": "Mystery Potion", "quantity": 3}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `item_slug` | string | Conditional | Full slug of database item (from /items endpoint) |
     * | `custom_name` | string | Conditional | Name for freetext item (max 255 chars) |
     * | `custom_description` | string | No | Description for freetext item (max 2000 chars) |
     * | `quantity` | integer | No | Number of items (default: 1, min: 1) |
     *
     * **Validation Rules:**
     * - Must provide either `item_slug` OR `custom_name` (not both, not neither)
     * - `item_slug` must reference an existing item in the database
     * - Stackable items with same `item_slug` may be consolidated
     *
     * **New items default to:**
     * - `equipped`: false
     * - `location`: "backpack"
     */
    public function store(CharacterEquipmentStoreRequest $request, Character $character): JsonResponse
    {
        if ($request->item_slug) {
            // Database item - may be a dangling reference per #288
            $item = Item::where('full_slug', $request->item_slug)->first();

            if ($item) {
                // Item exists - use equipment manager for proper handling
                $equipment = $this->equipmentManager->addItem(
                    $character,
                    $item,
                    $request->quantity ?? 1
                );
                $equipment->load('item.itemType');
            } else {
                // Dangling reference - create with slug only
                $equipment = $character->equipment()->create([
                    'item_slug' => $request->item_slug,
                    'quantity' => $request->quantity ?? 1,
                    'equipped' => false,
                    'location' => 'backpack',
                ]);
                // Load relationship for consistent resource behavior (will be null)
                $equipment->load('item.itemType');
            }
        } else {
            // Custom/freetext item
            $equipment = $character->equipment()->create([
                'item_slug' => null,
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
     * Update equipment (equip/unequip, change quantity)
     *
     * Modifies an existing equipment entry. Use this to equip/unequip items,
     * change quantities, or update location. Cannot change the item itself.
     *
     * **Examples:**
     * ```
     * PATCH /api/v1/characters/1/equipment/99
     *
     * # Equip an item
     * {"equipped": true}
     *
     * # Unequip an item
     * {"equipped": false}
     *
     * # Change quantity (e.g., use a potion)
     * {"quantity": 2}
     *
     * # Move item to different location
     * {"location": "belt"}
     *
     * # Combined update
     * {"equipped": true, "location": "main_hand"}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `equipped` | boolean | No | Whether item is equipped |
     * | `quantity` | integer | No | New quantity (min: 1) |
     * | `location` | string | No | Storage location (max 255 chars) |
     *
     * **Prohibited Fields (cannot change item type):**
     * - `item_id` - Cannot change database item reference
     * - `custom_name` - Cannot change custom item name
     * - `custom_description` - Cannot change custom item description
     *
     * **Equipment Rules:**
     * - Custom items cannot be equipped (only database items with proper slots)
     * - Equipment slot conflicts handled by EquipmentManagerService
     * - Unequipping always succeeds
     */
    public function update(
        CharacterEquipmentUpdateRequest $request,
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

        if ($equipment->item_slug) {
            $equipment->load('item.itemType');
        }

        return new CharacterEquipmentResource($equipment);
    }

    /**
     * Remove item from inventory
     *
     * Removes an item from the character's inventory completely. Use the update
     * endpoint to reduce quantity instead of full removal.
     *
     * **Examples:**
     * ```
     * DELETE /api/v1/characters/1/equipment/99
     * ```
     *
     * **Use Cases:**
     * - Selling/trading items
     * - Discarding items
     * - Item destruction (broken, lost)
     * - Transferring to another character (delete + add)
     *
     * **Note:** To reduce quantity without full removal, use PATCH with `{"quantity": N}`.
     * To remove just one from a stack, reduce quantity to (current - 1).
     *
     * @param  Character  $character  The character
     * @param  CharacterEquipment  $equipment  The equipment entry to remove
     * @return Response 204 on success
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
