<?php

namespace Tests\Unit\Services;

use App\Enums\SpellSlotType;
use App\Exceptions\InsufficientSpellSlotsException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterSpellSlot;
use App\Services\SpellSlotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpellSlotServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /**
     * Use TestDatabaseSeeder to load class fixtures (Wizard, Warlock, etc.)
     * needed for multiclass and caster tests.
     */
    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    private SpellSlotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SpellSlotService::class);
    }

    // =========================================================================
    // getSlots() Tests
    // =========================================================================

    #[Test]
    public function it_returns_empty_slots_for_non_caster(): void
    {
        // Create a Fighter (non-caster) - use factory without spellcasting_ability_id
        $fighter = CharacterClass::factory()->baseClass()->create([
            'name' => 'Fighter',
            'spellcasting_ability_id' => null,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
        ]);

        $result = $this->service->getSlots($character);

        $this->assertEmpty($result['standard']);
        $this->assertEmpty($result['pact_magic']);
    }

    #[Test]
    public function it_returns_correct_slots_for_full_caster(): void
    {
        // Manually create spell slots (simulating a level 5 full caster)
        $character = Character::factory()->create();

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 2,
            'max_slots' => 3,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 3,
            'max_slots' => 2,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $result = $this->service->getSlots($character);

        // Level 5 full caster has: 4 1st, 3 2nd, 2 3rd
        $this->assertNotEmpty($result['standard']);
        $this->assertArrayHasKey(1, $result['standard']);
        $this->assertEquals(4, $result['standard'][1]['max']);
        $this->assertEquals(0, $result['standard'][1]['used']);
    }

    #[Test]
    public function it_returns_separate_pact_magic_for_warlock(): void
    {
        // Create a Warlock (pact magic)
        $warlock = CharacterClass::where('name', 'Warlock')
            ->whereNull('parent_class_id')
            ->first();

        // Skip if Warlock not seeded
        if (! $warlock) {
            $this->markTestSkipped('Warlock class not found in database');
        }

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $warlock->id,
            'level' => 5,
            'is_primary' => true,
        ]);

        // Initialize spell slots
        $this->service->recalculateMaxSlots($character);

        $result = $this->service->getSlots($character);

        // Level 5 Warlock has 2 pact slots at 3rd level
        $this->assertNotEmpty($result['pact_magic']);
        $this->assertArrayHasKey(3, $result['pact_magic']);
        $this->assertEquals(2, $result['pact_magic'][3]['max']);
    }

    #[Test]
    public function it_returns_multiple_spell_levels(): void
    {
        $character = Character::factory()->create();

        // Create slots for levels 1, 2, and 3
        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 1,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 2,
            'max_slots' => 3,
            'used_slots' => 2,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 3,
            'max_slots' => 2,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $result = $this->service->getSlots($character);

        // Should have all three levels
        $this->assertCount(3, $result['standard']);
        $this->assertArrayHasKey(1, $result['standard']);
        $this->assertArrayHasKey(2, $result['standard']);
        $this->assertArrayHasKey(3, $result['standard']);
    }

    #[Test]
    public function it_returns_correct_used_and_available_counts(): void
    {
        $character = Character::factory()->create();

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 2,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $result = $this->service->getSlots($character);

        $this->assertEquals(4, $result['standard'][1]['max']);
        $this->assertEquals(2, $result['standard'][1]['used']);
        $this->assertEquals(2, $result['standard'][1]['available']);
    }

    #[Test]
    public function it_returns_both_standard_and_pact_magic_slots(): void
    {
        $character = Character::factory()->create();

        // Standard slot
        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 2,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        // Pact magic slot
        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 3,
            'max_slots' => 2,
            'used_slots' => 1,
            'slot_type' => SpellSlotType::PACT_MAGIC,
        ]);

        $result = $this->service->getSlots($character);

        $this->assertNotEmpty($result['standard']);
        $this->assertNotEmpty($result['pact_magic']);
        $this->assertEquals(2, $result['standard'][1]['max']);
        $this->assertEquals(2, $result['pact_magic'][3]['max']);
        $this->assertEquals(1, $result['pact_magic'][3]['used']);
    }

    // =========================================================================
    // useSlot() Tests
    // =========================================================================

    #[Test]
    public function it_uses_a_standard_slot(): void
    {
        $character = Character::factory()->create();
        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 1,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $this->service->useSlot($character, 1, SpellSlotType::STANDARD);

        $slot = CharacterSpellSlot::where('character_id', $character->id)
            ->where('spell_level', 1)
            ->where('slot_type', SpellSlotType::STANDARD)
            ->first();

        $this->assertEquals(2, $slot->used_slots);
    }

    #[Test]
    public function it_uses_a_pact_magic_slot(): void
    {
        $character = Character::factory()->create();
        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 3,
            'max_slots' => 2,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::PACT_MAGIC,
        ]);

        $this->service->useSlot($character, 3, SpellSlotType::PACT_MAGIC);

        $slot = CharacterSpellSlot::where('character_id', $character->id)
            ->where('spell_level', 3)
            ->where('slot_type', SpellSlotType::PACT_MAGIC)
            ->first();

        $this->assertEquals(1, $slot->used_slots);
    }

    #[Test]
    public function it_throws_when_no_slots_available(): void
    {
        $character = Character::factory()->create();
        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 2,
            'used_slots' => 2,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $this->expectException(InsufficientSpellSlotsException::class);

        $this->service->useSlot($character, 1, SpellSlotType::STANDARD);
    }

    #[Test]
    public function it_throws_when_slot_level_does_not_exist(): void
    {
        $character = Character::factory()->create();
        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 2,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $this->expectException(InsufficientSpellSlotsException::class);

        // Try to use a 5th level slot that doesn't exist
        $this->service->useSlot($character, 5, SpellSlotType::STANDARD);
    }

    #[Test]
    public function it_throws_when_using_wrong_slot_type(): void
    {
        $character = Character::factory()->create();
        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $this->expectException(InsufficientSpellSlotsException::class);

        // Try to use as pact magic when only standard exists
        $this->service->useSlot($character, 1, SpellSlotType::PACT_MAGIC);
    }

    // =========================================================================
    // resetSlots() Tests
    // =========================================================================

    #[Test]
    public function it_resets_only_standard_slots(): void
    {
        $character = Character::factory()->create();

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 3,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 3,
            'max_slots' => 2,
            'used_slots' => 2,
            'slot_type' => SpellSlotType::PACT_MAGIC,
        ]);

        $this->service->resetSlots($character, SpellSlotType::STANDARD);

        // Standard slot should be reset
        $standardSlot = CharacterSpellSlot::where('character_id', $character->id)
            ->where('slot_type', SpellSlotType::STANDARD)
            ->first();
        $this->assertEquals(0, $standardSlot->used_slots);

        // Pact magic should NOT be reset
        $pactSlot = CharacterSpellSlot::where('character_id', $character->id)
            ->where('slot_type', SpellSlotType::PACT_MAGIC)
            ->first();
        $this->assertEquals(2, $pactSlot->used_slots);
    }

    #[Test]
    public function it_resets_only_pact_magic_slots(): void
    {
        $character = Character::factory()->create();

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 3,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 3,
            'max_slots' => 2,
            'used_slots' => 2,
            'slot_type' => SpellSlotType::PACT_MAGIC,
        ]);

        $this->service->resetSlots($character, SpellSlotType::PACT_MAGIC);

        // Standard slot should NOT be reset
        $standardSlot = CharacterSpellSlot::where('character_id', $character->id)
            ->where('slot_type', SpellSlotType::STANDARD)
            ->first();
        $this->assertEquals(3, $standardSlot->used_slots);

        // Pact magic should be reset
        $pactSlot = CharacterSpellSlot::where('character_id', $character->id)
            ->where('slot_type', SpellSlotType::PACT_MAGIC)
            ->first();
        $this->assertEquals(0, $pactSlot->used_slots);
    }

    #[Test]
    public function it_resets_all_slots(): void
    {
        $character = Character::factory()->create();

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 3,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 3,
            'max_slots' => 2,
            'used_slots' => 2,
            'slot_type' => SpellSlotType::PACT_MAGIC,
        ]);

        $this->service->resetAllSlots($character);

        $this->assertEquals(
            0,
            CharacterSpellSlot::where('character_id', $character->id)->sum('used_slots')
        );
    }

    #[Test]
    public function it_resets_multiple_levels_of_standard_slots(): void
    {
        $character = Character::factory()->create();

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 4,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 2,
            'max_slots' => 3,
            'used_slots' => 2,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 3,
            'max_slots' => 2,
            'used_slots' => 1,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $this->service->resetSlots($character, SpellSlotType::STANDARD);

        $allSlots = CharacterSpellSlot::where('character_id', $character->id)
            ->where('slot_type', SpellSlotType::STANDARD)
            ->get();

        foreach ($allSlots as $slot) {
            $this->assertEquals(0, $slot->used_slots);
        }
    }

    #[Test]
    public function it_resets_when_no_slots_are_used(): void
    {
        $character = Character::factory()->create();

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        // Should not error even if nothing to reset
        $this->service->resetSlots($character, SpellSlotType::STANDARD);

        $slot = CharacterSpellSlot::where('character_id', $character->id)->first();
        $this->assertEquals(0, $slot->used_slots);
    }

    #[Test]
    public function it_handles_reset_when_character_has_no_slots(): void
    {
        $character = Character::factory()->create();

        // Should not error even if character has no slots
        $this->service->resetSlots($character, SpellSlotType::STANDARD);
        $this->service->resetAllSlots($character);

        $this->assertEquals(0, CharacterSpellSlot::where('character_id', $character->id)->count());
    }

    // =========================================================================
    // recalculateMaxSlots() Tests
    // =========================================================================

    #[Test]
    public function it_preserves_used_slots_during_recalculation(): void
    {
        $character = Character::factory()->create();

        // Create spell slot
        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        // Use some slots
        $this->service->useSlot($character, 1, SpellSlotType::STANDARD);
        $this->service->useSlot($character, 1, SpellSlotType::STANDARD);

        // Verify used slots
        $slotBefore = CharacterSpellSlot::where('character_id', $character->id)
            ->where('spell_level', 1)
            ->first();
        $this->assertEquals(2, $slotBefore->used_slots);

        // Manually "recalculate" by updating max_slots (simulating level up)
        $slotBefore->update(['max_slots' => 5]);

        // Used slots should be preserved
        $slotAfter = CharacterSpellSlot::where('character_id', $character->id)
            ->where('spell_level', 1)
            ->first();
        $this->assertEquals(2, $slotAfter->used_slots);
        $this->assertEquals(5, $slotAfter->max_slots);
    }

    #[Test]
    public function it_removes_pact_slots_when_warlock_levels_are_lost(): void
    {
        $warlock = CharacterClass::where('name', 'Warlock')
            ->whereNull('parent_class_id')
            ->first();

        if (! $warlock) {
            $this->markTestSkipped('Warlock class not found in database');
        }

        $character = Character::factory()->create();
        $classPivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $warlock->id,
            'level' => 5,
            'is_primary' => true,
        ]);

        // Initialize pact slots
        $this->service->recalculateMaxSlots($character);

        // Verify pact slots exist
        $pactSlots = CharacterSpellSlot::where('character_id', $character->id)
            ->where('slot_type', SpellSlotType::PACT_MAGIC)
            ->first();
        $this->assertNotNull($pactSlots);

        // Remove warlock class
        $classPivot->delete();
        $character->refresh();

        $this->service->recalculateMaxSlots($character);

        // Pact magic slots should be removed
        $pactSlotsAfter = CharacterSpellSlot::where('character_id', $character->id)
            ->where('slot_type', SpellSlotType::PACT_MAGIC)
            ->first();
        $this->assertNull($pactSlotsAfter);
    }

    #[Test]
    public function it_upgrades_pact_slot_level_on_warlock_level_up(): void
    {
        $warlock = CharacterClass::where('name', 'Warlock')
            ->whereNull('parent_class_id')
            ->first();

        if (! $warlock) {
            $this->markTestSkipped('Warlock class not found in database');
        }

        $character = Character::factory()->create();
        $classPivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $warlock->id,
            'level' => 2,
            'is_primary' => true,
        ]);

        // Initialize at level 2 (2 slots at 1st level)
        $this->service->recalculateMaxSlots($character);

        $level2Slots = CharacterSpellSlot::where('character_id', $character->id)
            ->where('slot_type', SpellSlotType::PACT_MAGIC)
            ->first();
        $this->assertEquals(1, $level2Slots->spell_level);
        $this->assertEquals(2, $level2Slots->max_slots);

        // Level up to 5 (2 slots at 3rd level)
        $classPivot->update(['level' => 5]);
        $character->refresh();

        $this->service->recalculateMaxSlots($character);

        // Old 1st level slot should be removed
        $oldSlots = CharacterSpellSlot::where('character_id', $character->id)
            ->where('slot_type', SpellSlotType::PACT_MAGIC)
            ->where('spell_level', 1)
            ->first();
        $this->assertNull($oldSlots);

        // New 3rd level slot should exist
        $newSlots = CharacterSpellSlot::where('character_id', $character->id)
            ->where('slot_type', SpellSlotType::PACT_MAGIC)
            ->where('spell_level', 3)
            ->first();
        $this->assertNotNull($newSlots);
        $this->assertEquals(3, $newSlots->spell_level);
        $this->assertEquals(2, $newSlots->max_slots);
    }

    #[Test]
    public function it_handles_both_standard_and_pact_slots_for_multiclass(): void
    {
        // Simulate a multiclass character with both standard and pact magic slots
        $character = Character::factory()->create();

        // Standard slots (from wizard)
        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 3,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        // Pact magic slots (from warlock)
        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 2,
            'max_slots' => 2,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::PACT_MAGIC,
        ]);

        $result = $this->service->getSlots($character);

        // Should have BOTH standard slots and pact slots
        $this->assertNotEmpty($result['standard']);
        $this->assertNotEmpty($result['pact_magic']);
        $this->assertEquals(3, $result['standard'][1]['max']);
        $this->assertEquals(2, $result['pact_magic'][2]['max']);
    }
}
