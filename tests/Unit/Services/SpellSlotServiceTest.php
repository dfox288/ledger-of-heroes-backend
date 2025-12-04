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
    public function it_returns_correct_slots_for_wizard(): void
    {
        // Create a Wizard (full caster)
        $wizard = CharacterClass::where('name', 'Wizard')
            ->whereNull('parent_class_id')
            ->first();

        // Skip if Wizard not seeded
        if (! $wizard) {
            $this->markTestSkipped('Wizard class not found in database');
        }

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 5,
            'is_primary' => true,
        ]);

        // Initialize spell slots
        $this->service->recalculateMaxSlots($character);

        $result = $this->service->getSlots($character);

        // Level 5 Wizard has: 4 1st, 3 2nd, 2 3rd
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

    // =========================================================================
    // recalculateMaxSlots() Tests
    // =========================================================================

    #[Test]
    public function it_creates_spell_slots_for_new_caster(): void
    {
        $wizard = CharacterClass::where('name', 'Wizard')
            ->whereNull('parent_class_id')
            ->first();

        if (! $wizard) {
            $this->markTestSkipped('Wizard class not found in database');
        }

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 3,
            'is_primary' => true,
        ]);

        // Character has no spell slots yet
        $this->assertEquals(0, CharacterSpellSlot::where('character_id', $character->id)->count());

        // Recalculate
        $this->service->recalculateMaxSlots($character);

        // Should have created spell slots
        $this->assertGreaterThan(0, CharacterSpellSlot::where('character_id', $character->id)->count());
    }

    #[Test]
    public function it_updates_max_slots_on_level_up(): void
    {
        $wizard = CharacterClass::where('name', 'Wizard')
            ->whereNull('parent_class_id')
            ->first();

        if (! $wizard) {
            $this->markTestSkipped('Wizard class not found in database');
        }

        $character = Character::factory()->create();
        $classPivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        // Initialize at level 1
        $this->service->recalculateMaxSlots($character);

        // Level 1 wizard has 2 first-level slots
        $level1Slots = CharacterSpellSlot::where('character_id', $character->id)
            ->where('spell_level', 1)
            ->first();
        $this->assertEquals(2, $level1Slots->max_slots);

        // Level up to 3
        $classPivot->update(['level' => 3]);
        $character->refresh();

        $this->service->recalculateMaxSlots($character);

        // Level 3 wizard has 4 first-level slots
        $level3Slots = CharacterSpellSlot::where('character_id', $character->id)
            ->where('spell_level', 1)
            ->first();
        $this->assertEquals(4, $level3Slots->max_slots);
    }
}
