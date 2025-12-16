<?php

namespace App\Http\Resources;

use App\Enums\ItemGroup;
use App\Enums\ItemTypeCode;
use App\Http\Resources\Concerns\FormatsRelatedModels;
use App\Services\CharacterStatCalculator;
use App\Services\ProficiencyCheckerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

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
            /** @var array{id: int, name: string, slug: string, armor_class: int|null, damage_dice: string|null, weight: float|null, requires_attunement: bool, equipment_slot: string|null, item_type: string|null}|null Item details (null for custom items) */
            'item' => $this->when($this->item_slug !== null, fn () => $this->item
                ? $this->formatEntityWith(
                    $this->item,
                    ['id', 'name', 'slug', 'armor_class', 'damage_dice', 'weight', 'requires_attunement'],
                    [
                        'item_type' => fn ($item) => $item->itemType?->name,
                        'equipment_slot' => fn ($item) => $this->normalizeEquipmentSlot($item->equipment_slot),
                    ]
                )
                : null  // Dangling reference - item_slug set but item doesn't exist
            ),
            /** @var string|null Item slug reference (null for custom items) */
            'item_slug' => $this->item_slug,
            /** @var bool True if this is a currency item (Gold, Silver, Copper, Electrum, Platinum) */
            'is_currency' => $this->isCurrency(),
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
            // Issue #708: Combat data for equipped weapons
            ...$this->getWeaponCombatData(),
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

    /**
     * Check if this equipment entry is a currency item.
     */
    private function isCurrency(): bool
    {
        if ($this->item_slug === null) {
            return false;
        }

        $currencySlugs = [
            'phb:copper-cp',
            'phb:silver-sp',
            'phb:electrum-ep',
            'phb:gold-gp',
            'phb:platinum-pp',
        ];

        return in_array($this->item_slug, $currencySlugs, true);
    }

    /**
     * Get weapon combat data for equipped weapons in hand slots.
     *
     * Issue #708: Returns attack_bonus, damage_bonus, ability_used for
     * weapons equipped in main_hand or off_hand.
     *
     * @return array{attack_bonus?: int, damage_bonus?: int, ability_used?: string}
     */
    private function getWeaponCombatData(): array
    {
        // Only include combat data for equipped weapons in hand slots
        if (! $this->isEquippedWeaponInHandSlot()) {
            return [];
        }

        $character = $this->character;
        $item = $this->item;

        // Get character's ability modifiers
        $calculator = app(CharacterStatCalculator::class);
        $abilityScores = $character->getFinalAbilityScoresArray();
        $strMod = $abilityScores['STR'] !== null ? $calculator->abilityModifier($abilityScores['STR']) : 0;
        $dexMod = $abilityScores['DEX'] !== null ? $calculator->abilityModifier($abilityScores['DEX']) : 0;

        // Determine ability to use based on weapon type
        $item->loadMissing(['itemType', 'properties']);
        $isFinesse = $item->properties->contains('code', 'F');
        $isRanged = $item->itemType?->code === ItemTypeCode::RANGED_WEAPON->value;

        if ($isRanged) {
            $abilityMod = $dexMod;
            $abilityUsed = 'DEX';
        } elseif ($isFinesse) {
            // Use whichever modifier is higher
            if ($dexMod > $strMod) {
                $abilityMod = $dexMod;
                $abilityUsed = 'DEX';
            } else {
                $abilityMod = $strMod;
                $abilityUsed = 'STR';
            }
        } else {
            $abilityMod = $strMod;
            $abilityUsed = 'STR';
        }

        // Check proficiency
        $isProficient = $this->isWeaponProficient($character, $item);
        $proficiencyBonus = $calculator->proficiencyBonus($character->total_level);

        // Calculate attack and damage bonus
        $attackBonus = $abilityMod + ($isProficient ? $proficiencyBonus : 0);
        $damageBonus = $abilityMod;

        return [
            /** @var int Attack bonus (ability modifier + proficiency if proficient) */
            'attack_bonus' => $attackBonus,
            /** @var int Damage bonus (ability modifier) */
            'damage_bonus' => $damageBonus,
            /** @var string Ability used for attacks (STR or DEX) */
            'ability_used' => $abilityUsed,
        ];
    }

    /**
     * Check if this is an equipped weapon in a hand slot.
     */
    private function isEquippedWeaponInHandSlot(): bool
    {
        // Must be equipped in main_hand or off_hand
        if (! in_array($this->location, ['main_hand', 'off_hand'], true)) {
            return false;
        }

        // Must have a valid item that is a weapon
        if (! $this->item) {
            return false;
        }

        $this->item->loadMissing('itemType');

        return in_array($this->item->itemType?->code, ItemTypeCode::weaponCodes(), true);
    }

    /**
     * Check if character is proficient with this weapon.
     */
    private function isWeaponProficient(\App\Models\Character $character, \App\Models\Item $item): bool
    {
        // Generate the expected proficiency slug (e.g., "core:longsword")
        $weaponSlug = 'core:'.Str::slug($item->name);

        // Load proficiencies if not loaded
        $character->loadMissing('proficiencies');

        // Check for specific weapon name proficiency
        return $character->proficiencies
            ->where('proficiency_type_slug', $weaponSlug)
            ->isNotEmpty();
    }

    /**
     * Normalize equipment_slot values to match EquipmentLocation enum values.
     *
     * Maps legacy/simplified slot names to the actual location values used
     * by CharacterEquipment.location for consistent frontend handling.
     *
     * @param  string|null  $slot  The raw equipment_slot from Item
     * @return string|null The normalized slot matching EquipmentLocation values
     */
    private function normalizeEquipmentSlot(?string $slot): ?string
    {
        if ($slot === null) {
            return null;
        }

        // Map legacy values to EquipmentLocation values
        return match ($slot) {
            'hand' => 'main_hand',  // Weapons go to main_hand by default
            'ring' => 'ring_1',     // Rings default to first ring slot
            default => $slot,       // armor, belt, cloak, head, neck, etc. already match
        };
    }
}
