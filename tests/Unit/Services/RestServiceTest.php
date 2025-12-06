<?php

namespace Tests\Unit\Services;

use App\Enums\ResetTiming;
use App\Enums\SpellSlotType;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterSpellSlot;
use App\Services\RestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RestServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private RestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RestService::class);
    }

    // =========================================================================
    // Short Rest Tests
    // =========================================================================

    #[Test]
    public function short_rest_resets_pact_magic_slots(): void
    {
        $character = Character::factory()->create();

        // Create pact magic slots (used)
        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 3,
            'max_slots' => 2,
            'used_slots' => 2,
            'slot_type' => SpellSlotType::PACT_MAGIC,
        ]);

        // Create standard slots (used) - should NOT reset
        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 3,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $result = $this->service->shortRest($character);

        // Pact magic should be reset
        $pactSlot = CharacterSpellSlot::where('character_id', $character->id)
            ->where('slot_type', SpellSlotType::PACT_MAGIC)
            ->first();
        $this->assertEquals(0, $pactSlot->used_slots);

        // Standard slots should NOT be reset
        $standardSlot = CharacterSpellSlot::where('character_id', $character->id)
            ->where('slot_type', SpellSlotType::STANDARD)
            ->first();
        $this->assertEquals(3, $standardSlot->used_slots);

        // Result should indicate pact magic reset
        $this->assertTrue($result['pact_magic_reset']);
    }

    #[Test]
    public function short_rest_does_not_reset_hit_dice(): void
    {
        $fighter = CharacterClass::factory()->baseClass()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'hit_dice_spent' => 3,
        ]);

        $this->service->shortRest($character);

        $pivot = CharacterClassPivot::where('character_id', $character->id)->first();
        $this->assertEquals(3, $pivot->hit_dice_spent, 'Hit dice should NOT reset on short rest');
    }

    // =========================================================================
    // Long Rest Tests
    // =========================================================================

    #[Test]
    public function long_rest_resets_all_spell_slots(): void
    {
        $character = Character::factory()->create();

        // Create pact magic slots (used)
        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 3,
            'max_slots' => 2,
            'used_slots' => 2,
            'slot_type' => SpellSlotType::PACT_MAGIC,
        ]);

        // Create standard slots (used)
        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 3,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $result = $this->service->longRest($character);

        // Both should be reset
        $this->assertEquals(
            0,
            CharacterSpellSlot::where('character_id', $character->id)->sum('used_slots')
        );

        $this->assertTrue($result['spell_slots_reset']);
    }

    #[Test]
    public function long_rest_restores_hp_to_max(): void
    {
        $character = Character::factory()->create([
            'current_hit_points' => 15,
            'max_hit_points' => 45,
            'temp_hit_points' => 0,
        ]);

        $result = $this->service->longRest($character);

        $character->refresh();
        $this->assertEquals(45, $character->current_hit_points);
        $this->assertEquals(30, $result['hp_restored']);
    }

    #[Test]
    public function long_rest_recovers_half_hit_dice(): void
    {
        $fighter = CharacterClass::factory()->baseClass()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 8,
            'is_primary' => true,
            'hit_dice_spent' => 6, // 6 spent, 2 available
        ]);

        $result = $this->service->longRest($character);

        $pivot = CharacterClassPivot::where('character_id', $character->id)->first();

        // Level 8 = max 8 hit dice. Half = 4. Spent 6, recover 4 = 2 remaining spent
        $this->assertEquals(2, $pivot->hit_dice_spent);
        $this->assertEquals(4, $result['hit_dice_recovered']);
    }

    #[Test]
    public function long_rest_recovers_minimum_one_hit_die(): void
    {
        $wizard = CharacterClass::factory()->baseClass()->create([
            'name' => 'Wizard',
            'hit_die' => 6,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 1, // Level 1 = max 1 hit die, half = 0.5 rounds to minimum 1
            'is_primary' => true,
            'hit_dice_spent' => 1,
        ]);

        $result = $this->service->longRest($character);

        $pivot = CharacterClassPivot::where('character_id', $character->id)->first();
        $this->assertEquals(0, $pivot->hit_dice_spent);
        $this->assertEquals(1, $result['hit_dice_recovered']);
    }

    #[Test]
    public function long_rest_clears_death_saves(): void
    {
        $character = Character::factory()->create([
            'death_save_successes' => 2,
            'death_save_failures' => 1,
        ]);

        $this->service->longRest($character);

        $character->refresh();
        $this->assertEquals(0, $character->death_save_successes);
        $this->assertEquals(0, $character->death_save_failures);
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    #[Test]
    public function short_rest_works_with_no_spell_slots(): void
    {
        $character = Character::factory()->create();

        // No spell slots at all (e.g., Fighter)
        $result = $this->service->shortRest($character);

        $this->assertFalse($result['pact_magic_reset']);
    }

    #[Test]
    public function short_rest_identifies_features_that_reset(): void
    {
        $fighter = CharacterClass::factory()->baseClass()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        // Create a feature that resets on short rest
        $fighter->features()->create([
            'level' => 2,
            'feature_name' => 'Action Surge',
            'is_optional' => false,
            'is_multiclass_only' => false,
            'description' => 'Once on your turn, you can take one additional action',
            'sort_order' => 0,
            'resets_on' => ResetTiming::SHORT_REST,
        ]);

        // Create a feature that doesn't reset on short rest
        $fighter->features()->create([
            'level' => 3,
            'feature_name' => 'Second Wind',
            'is_optional' => false,
            'is_multiclass_only' => false,
            'description' => 'Regain hit points',
            'sort_order' => 1,
            'resets_on' => ResetTiming::LONG_REST,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
        ]);

        $result = $this->service->shortRest($character);

        // Should identify Action Surge but not Second Wind
        $this->assertContains('Action Surge', $result['features_reset']);
        $this->assertNotContains('Second Wind', $result['features_reset']);
        $this->assertCount(1, $result['features_reset']);
    }

    #[Test]
    public function long_rest_caps_hp_at_max(): void
    {
        $character = Character::factory()->create([
            'current_hit_points' => 45, // Already at max
            'max_hit_points' => 45,
        ]);

        $result = $this->service->longRest($character);

        $character->refresh();
        $this->assertEquals(45, $character->current_hit_points);
        $this->assertEquals(0, $result['hp_restored']);
    }

    #[Test]
    public function long_rest_does_not_over_recover_hit_dice(): void
    {
        $fighter = CharacterClass::factory()->baseClass()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 10,
            'is_primary' => true,
            'hit_dice_spent' => 2, // Only 2 spent, but could recover 5
        ]);

        $result = $this->service->longRest($character);

        $pivot = CharacterClassPivot::where('character_id', $character->id)->first();
        $this->assertEquals(0, $pivot->hit_dice_spent);
        $this->assertEquals(2, $result['hit_dice_recovered']); // Only recovered what was spent
    }

    #[Test]
    public function long_rest_identifies_all_resetting_features(): void
    {
        $druid = CharacterClass::factory()->baseClass()->create([
            'name' => 'Druid',
            'hit_die' => 8,
        ]);

        // Create features with different reset timings
        $druid->features()->create([
            'level' => 2,
            'feature_name' => 'Wild Shape',
            'is_optional' => false,
            'is_multiclass_only' => false,
            'description' => 'Transform into a beast',
            'sort_order' => 0,
            'resets_on' => ResetTiming::SHORT_REST,
        ]);

        $druid->features()->create([
            'level' => 5,
            'feature_name' => 'Wild Shape (CR 1)',
            'is_optional' => false,
            'is_multiclass_only' => false,
            'description' => 'Transform into CR 1 beasts',
            'sort_order' => 1,
            'resets_on' => ResetTiming::LONG_REST,
        ]);

        $druid->features()->create([
            'level' => 18,
            'feature_name' => 'Beast Spells',
            'is_optional' => false,
            'is_multiclass_only' => false,
            'description' => 'Cast spells while wild shaped',
            'sort_order' => 2,
            'resets_on' => ResetTiming::DAWN,
        ]);

        // Feature above character's level should not appear
        $druid->features()->create([
            'level' => 20,
            'feature_name' => 'Archdruid',
            'is_optional' => false,
            'is_multiclass_only' => false,
            'description' => 'Unlimited Wild Shape',
            'sort_order' => 3,
            'resets_on' => ResetTiming::SHORT_REST,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $druid->id,
            'level' => 18, // Below level 20, so Archdruid should not be included
            'is_primary' => true,
        ]);

        $result = $this->service->longRest($character);

        // Should identify all three features at or below level 18 with SHORT_REST, LONG_REST, or DAWN
        $this->assertContains('Wild Shape', $result['features_reset']);
        $this->assertContains('Wild Shape (CR 1)', $result['features_reset']);
        $this->assertContains('Beast Spells', $result['features_reset']);
        $this->assertNotContains('Archdruid', $result['features_reset']);
        $this->assertCount(3, $result['features_reset']);
    }

    // =========================================================================
    // Return Data Tests
    // =========================================================================

    #[Test]
    public function short_rest_returns_complete_summary(): void
    {
        $character = Character::factory()->create();

        $result = $this->service->shortRest($character);

        $this->assertArrayHasKey('pact_magic_reset', $result);
        $this->assertArrayHasKey('features_reset', $result);
    }

    #[Test]
    public function long_rest_returns_complete_summary(): void
    {
        $character = Character::factory()->create([
            'current_hit_points' => 30,
            'max_hit_points' => 50,
        ]);

        $result = $this->service->longRest($character);

        $this->assertArrayHasKey('hp_restored', $result);
        $this->assertArrayHasKey('hit_dice_recovered', $result);
        $this->assertArrayHasKey('spell_slots_reset', $result);
        $this->assertArrayHasKey('death_saves_cleared', $result);
        $this->assertArrayHasKey('features_reset', $result);
    }
}
