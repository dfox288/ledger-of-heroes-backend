<?php

namespace Tests\Unit\Importers;

use App\Services\Importers\ItemImporter;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Tests for equipment slot inference logic in ItemImporter.
 *
 * Tests the inferEquipmentSlot() and inferSlotFromName() private methods
 * using reflection to verify slot assignment behavior.
 *
 * @see https://github.com/dfox288/ledger-of-heroes/issues/589
 */
#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class ItemEquipmentSlotInferenceTest extends TestCase
{
    private ItemImporter $importer;

    private ReflectionMethod $inferEquipmentSlot;

    private ReflectionMethod $inferSlotFromName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new ItemImporter;

        // Make private methods accessible for testing
        $this->inferEquipmentSlot = new ReflectionMethod(ItemImporter::class, 'inferEquipmentSlot');
        $this->inferEquipmentSlot->setAccessible(true);

        $this->inferSlotFromName = new ReflectionMethod(ItemImporter::class, 'inferSlotFromName');
        $this->inferSlotFromName->setAccessible(true);
    }

    // =========================================================================
    // Type-based Slot Inference Tests
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function light_armor_maps_to_armor_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'LA', 'Leather Armor');
        $this->assertEquals('armor', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function medium_armor_maps_to_armor_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'MA', 'Chain Shirt');
        $this->assertEquals('armor', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function heavy_armor_maps_to_armor_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'HA', 'Plate Armor');
        $this->assertEquals('armor', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function shield_maps_to_off_hand_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'S', 'Shield');
        $this->assertEquals('off_hand', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ring_maps_to_ring_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'RG', 'Ring of Protection');
        $this->assertEquals('ring', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function melee_weapon_maps_to_hand_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'M', 'Longsword');
        $this->assertEquals('hand', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ranged_weapon_maps_to_hand_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'R', 'Longbow');
        $this->assertEquals('hand', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function staff_maps_to_hand_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'ST', 'Staff of Fire');
        $this->assertEquals('hand', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rod_maps_to_hand_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'RD', 'Rod of the Pact Keeper');
        $this->assertEquals('hand', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function wand_maps_to_hand_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'WD', 'Wand of Fireballs');
        $this->assertEquals('hand', $result);
    }

    // =========================================================================
    // Non-equippable Type Tests
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function potion_returns_null(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'P', 'Potion of Healing');
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function scroll_returns_null(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'SC', 'Spell Scroll');
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function adventuring_gear_returns_null(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'G', 'Torch');
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ammunition_returns_null(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'A', 'Arrow');
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function trade_goods_returns_null(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, '$', 'Silk');
        $this->assertNull($result);
    }

    // =========================================================================
    // Wondrous Item Pattern Matching Tests (feet)
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function boots_maps_to_feet_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Boots of Elvenkind');
        $this->assertEquals('feet', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function winged_boots_maps_to_feet_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Winged Boots');
        $this->assertEquals('feet', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function slippers_maps_to_feet_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Slippers of Spider Climbing');
        $this->assertEquals('feet', $result);
    }

    // =========================================================================
    // Wondrous Item Pattern Matching Tests (cloak)
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function cloak_maps_to_cloak_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Cloak of Protection');
        $this->assertEquals('cloak', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cape_maps_to_cloak_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Cape of the Mountebank');
        $this->assertEquals('cloak', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function mantle_maps_to_cloak_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Mantle of Spell Resistance');
        $this->assertEquals('cloak', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function wings_of_flying_maps_to_cloak_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Wings of Flying');
        $this->assertEquals('cloak', $result);
    }

    // =========================================================================
    // Wondrous Item Pattern Matching Tests (belt)
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function belt_maps_to_belt_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Belt of Giant Strength');
        $this->assertEquals('belt', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function girdle_maps_to_belt_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Girdle of Hill Giant Strength');
        $this->assertEquals('belt', $result);
    }

    // =========================================================================
    // Wondrous Item Pattern Matching Tests (head)
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function helm_maps_to_head_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Helm of Brilliance');
        $this->assertEquals('head', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function hat_maps_to_head_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Hat of Disguise');
        $this->assertEquals('head', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function circlet_maps_to_head_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Circlet of Blasting');
        $this->assertEquals('head', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function crown_maps_to_head_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', "Belashyrra's Beholder Crown");
        $this->assertEquals('head', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function headband_maps_to_head_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Headband of Intellect');
        $this->assertEquals('head', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cap_of_water_breathing_maps_to_head_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Cap of Water Breathing');
        $this->assertEquals('head', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function goggles_maps_to_head_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Goggles of Night');
        $this->assertEquals('head', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function eyes_of_charming_maps_to_head_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Eyes of Charming');
        $this->assertEquals('head', $result);
    }

    // =========================================================================
    // Wondrous Item Pattern Matching Tests (neck)
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function amulet_maps_to_neck_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Amulet of Health');
        $this->assertEquals('neck', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function necklace_maps_to_neck_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Necklace of Fireballs');
        $this->assertEquals('neck', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function periapt_maps_to_neck_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Periapt of Health');
        $this->assertEquals('neck', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function medallion_maps_to_neck_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Medallion of Thoughts');
        $this->assertEquals('neck', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function brooch_maps_to_neck_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Brooch of Shielding');
        $this->assertEquals('neck', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function scarab_maps_to_neck_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Scarab of Protection');
        $this->assertEquals('neck', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function talisman_maps_to_neck_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Talisman of Pure Good');
        $this->assertEquals('neck', $result);
    }

    // =========================================================================
    // Wondrous Item Pattern Matching Tests (hands)
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function gloves_maps_to_hands_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Gloves of Thievery');
        $this->assertEquals('hands', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function gauntlets_maps_to_hands_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Gauntlets of Ogre Power');
        $this->assertEquals('hands', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bracers_maps_to_hands_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Bracers of Defense');
        $this->assertEquals('hands', $result);
    }

    // =========================================================================
    // Wondrous Item Pattern Matching Tests (clothes)
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function robe_of_maps_to_clothes_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Robe of the Archmagi');
        $this->assertEquals('clothes', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function robe_of_eyes_maps_to_clothes_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Robe of Eyes');
        $this->assertEquals('clothes', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function clothes_of_mending_maps_to_clothes_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Clothes of Mending');
        $this->assertEquals('clothes', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function glamerweave_maps_to_clothes_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Glamerweave (uncommon)');
        $this->assertEquals('clothes', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function shiftweave_maps_to_clothes_slot(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Shiftweave');
        $this->assertEquals('clothes', $result);
    }

    // =========================================================================
    // Wondrous Items Without Body Slot Tests
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function bag_of_holding_returns_null(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Bag of Holding');
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ioun_stone_returns_null(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Ioun Stone, Absorption');
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function figurine_returns_null(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Figurine of Wondrous Power, Bronze Griffon');
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function tattoo_returns_null(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Blood Fury Tattoo');
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function deck_of_many_things_returns_null(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Deck of Many Things');
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function carpet_of_flying_returns_null(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Carpet of Flying');
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function instrument_returns_null(): void
    {
        $result = $this->inferEquipmentSlot->invoke($this->importer, 'W', 'Instrument of the Bards, Anstruth Harp');
        $this->assertNull($result);
    }
}
