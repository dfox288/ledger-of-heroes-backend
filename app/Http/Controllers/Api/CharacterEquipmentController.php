<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Character\Equipment\CharacterEquipmentStoreRequest;
use App\Http\Requests\Character\Equipment\CharacterEquipmentUpdateRequest;
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
            $item = Item::where('slug', $request->item_slug)->first();

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
     * Update equipment (equip/unequip, change quantity, change location)
     *
     * Modifies an existing equipment entry. Use this to equip/unequip items,
     * change quantities, or update location. Cannot change the item itself.
     *
     * **Examples:**
     * ```
     * PATCH /api/v1/characters/1/equipment/99
     *
     * # Equip to main hand (auto-sets equipped=true)
     * {"location": "main_hand"}
     *
     * # Equip armor (auto-sets equipped=true)
     * {"location": "armor"}
     *
     * # Equip ring with attunement
     * {"location": "ring_1", "is_attuned": true}
     *
     * # Unequip to backpack (auto-sets equipped=false, clears is_attuned)
     * {"location": "backpack"}
     *
     * # Legacy equip (auto-determines location by item type)
     * {"equipped": true}
     *
     * # Change quantity (e.g., use a potion)
     * {"quantity": 2}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `location` | string | No | Equipment slot (see table below) |
     * | `equipped` | boolean | No | Legacy equip flag (prefer location) |
     * | `quantity` | integer | No | New quantity (min: 1) |
     * | `is_attuned` | boolean | No | Attunement flag for magic items (default max 3, can be higher) |
     *
     * **Location Slots:**
     * | Location | Slot Limit | Description |
     * |----------|------------|-------------|
     * | `main_hand` | 1 | Primary weapon |
     * | `off_hand` | 1 | Shield or secondary weapon |
     * | `head` | 1 | Helmets, circlets |
     * | `neck` | 1 | Amulets, necklaces |
     * | `cloak` | 1 | Cloaks |
     * | `armor` | 1 | Body armor |
     * | `belt` | 1 | Belts |
     * | `hands` | 1 | Gloves, gauntlets |
     * | `ring_1` | 1 | Ring slot 1 |
     * | `ring_2` | 1 | Ring slot 2 |
     * | `feet` | 1 | Boots |
     * | `backpack` | unlimited | Unequipped storage |
     *
     * **Prohibited Fields (cannot change item type):**
     * - `item_id` - Cannot change database item reference
     * - `custom_name` - Cannot change custom item name
     * - `custom_description` - Cannot change custom item description
     *
     * **Equipment Rules:**
     * - Custom items cannot be equipped (only database items)
     * - Single-slot locations auto-unequip previous item to backpack
     * - Two-handed weapons auto-unequip off_hand; off_hand blocked while 2H equipped
     * - Attunement limit: dynamic based on class features (default 3, Artificer can have more)
     * - Attunement requires item to have requires_attunement property
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

        // Handle location changes (takes precedence over equipped flag)
        if ($request->has('location')) {
            $this->equipmentManager->setLocation($equipment, $request->location);
        } elseif ($request->has('equipped')) {
            // Only handle equipped flag if location not provided
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

        // Handle is_attuned updates
        // - If location is provided AND is_attuned is provided, apply is_attuned after location change
        // - If only is_attuned is provided, update it directly
        if ($request->has('is_attuned')) {
            $equipment->refresh(); // Get latest state after location change
            $equipment->update(['is_attuned' => $request->boolean('is_attuned')]);
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
