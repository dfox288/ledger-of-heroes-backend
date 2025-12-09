<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterEquipment;
use App\Models\EntityItem;
use App\Models\EquipmentChoiceItem;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Integration tests for the equipment mode gold flow.
 *
 * Tests the full API flow for selecting gold instead of starting equipment,
 * simulating what the frontend wizard does.
 *
 * Issue #431: Equipment mode gold not saved when choosing dice roll option
 */
#[Group('feature-db')]
class EquipmentModeGoldFlowTest extends TestCase
{
    use RefreshDatabase;

    private Character $character;

    private CharacterClass $class;

    private Item $goldItem;

    protected function setUp(): void
    {
        parent::setUp();

        // Create gold item
        $this->goldItem = Item::factory()->create([
            'name' => 'Gold (gp)',
            'slug' => 'gold-gp',
            'full_slug' => 'phb:gold-gp',
        ]);

        // Create class with starting wealth and equipment choices
        $this->class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'full_slug' => 'phb:fighter',
            'starting_wealth_dice' => '5d4',
            'starting_wealth_multiplier' => 10,
        ]);

        // Create equipment choice so equipment_mode choice appears
        $item = Item::factory()->create(['name' => 'Chain Mail', 'full_slug' => 'phb:chain-mail']);
        $entityItem = EntityItem::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $this->class->id,
            'is_choice' => true,
            'choice_group' => 'choice_1',
            'choice_option' => 1,
            'description' => 'chain mail',
            'quantity' => 1,
        ]);
        EquipmentChoiceItem::create([
            'entity_item_id' => $entityItem->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'sort_order' => 0,
        ]);

        // Create character with this class at level 1
        $this->character = Character::factory()->create(['name' => 'Test Fighter']);
        CharacterClassPivot::create([
            'character_id' => $this->character->id,
            'class_slug' => $this->class->full_slug,
            'level' => 1,
            'is_primary' => true,
        ]);
    }

    #[Test]
    public function it_returns_equipment_mode_choice_for_level_1_character(): void
    {
        $response = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");

        $response->assertOk()
            ->assertJsonCount(1, 'data.choices')
            ->assertJsonPath('data.choices.0.type', 'equipment_mode')
            ->assertJsonPath('data.choices.0.remaining', 1)
            ->assertJsonPath('data.choices.0.selected', [])
            ->assertJsonStructure([
                'data' => [
                    'choices' => [
                        '*' => [
                            'id',
                            'type',
                            'source',
                            'source_name',
                            'options',
                            'metadata' => [
                                'starting_wealth' => [
                                    'dice',
                                    'multiplier',
                                    'average',
                                    'formula',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        // Verify options include both equipment and gold
        $options = $response->json('data.choices.0.options');
        $this->assertCount(2, $options);
        $this->assertEquals('equipment', $options[0]['value']);
        $this->assertEquals('gold', $options[1]['value']);
    }

    #[Test]
    public function it_resolves_gold_choice_with_gold_amount(): void
    {
        // Get the choice ID
        $choicesResponse = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $choiceId = $choicesResponse->json('data.choices.0.id');

        // Resolve with gold and specific amount (simulating dice roll)
        $response = $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            [
                'selected' => ['gold'],
                'gold_amount' => 130,
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.message', 'Choice resolved successfully');

        // Verify gold was added to inventory
        $this->character->refresh();
        $goldEquipment = $this->character->equipment()->where('item_slug', 'phb:gold-gp')->first();

        $this->assertNotNull($goldEquipment, 'Gold should be added to inventory');
        $this->assertEquals(130, $goldEquipment->quantity);

        // Verify equipment_mode column was set
        $this->assertEquals('gold', $this->character->equipment_mode);
    }

    #[Test]
    public function it_resolves_gold_choice_with_average_when_gold_amount_not_provided(): void
    {
        $choicesResponse = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $choiceId = $choicesResponse->json('data.choices.0.id');

        // Resolve with gold but no gold_amount - should use average (125 for 5d4 * 10)
        $response = $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            ['selected' => ['gold']]
        );

        $response->assertOk();

        $this->character->refresh();
        $goldEquipment = $this->character->equipment()->where('item_slug', 'phb:gold-gp')->first();

        $this->assertNotNull($goldEquipment);
        $this->assertEquals(125, $goldEquipment->quantity, 'Should use average (125) when gold_amount not provided');
    }

    #[Test]
    public function it_tracks_gold_from_different_sources_separately(): void
    {
        // Create background with gold
        $background = Background::factory()->create(['name' => 'Noble', 'full_slug' => 'phb:noble']);
        $this->character->update(['background_slug' => $background->full_slug]);

        // Add existing background gold (25 gp)
        CharacterEquipment::create([
            'character_id' => $this->character->id,
            'item_slug' => 'phb:gold-gp',
            'quantity' => 25,
            'equipped' => false,
            'custom_description' => json_encode(['source' => 'background']),
        ]);

        $choicesResponse = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $choiceId = $choicesResponse->json('data.choices.0.id');

        // Resolve with gold (130)
        $response = $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            [
                'selected' => ['gold'],
                'gold_amount' => 130,
            ]
        );

        $response->assertOk();

        $this->character->refresh();

        // Gold from different sources is tracked separately (for proper re-selection/undo)
        $goldEntries = $this->character->equipment()->where('item_slug', 'phb:gold-gp')->get();
        $this->assertCount(2, $goldEntries, 'Background and starting wealth gold are tracked separately');

        // Total gold should be 155 (25 + 130)
        $totalGold = $goldEntries->sum('quantity');
        $this->assertEquals(155, $totalGold, 'Total gold should be 155 (25 background + 130 starting wealth)');

        // Currency accessor should sum both entries
        $this->assertEquals(155, $this->character->currency['gp'], 'Currency accessor should sum all gold entries');
    }

    #[Test]
    public function it_hides_equipment_choices_after_gold_mode_selected(): void
    {
        // First, verify equipment choices exist before gold selection
        $equipmentBefore = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment");
        $this->assertGreaterThan(0, count($equipmentBefore->json('data.choices')), 'Should have equipment choices before gold selection');

        // Get equipment_mode choice and select gold
        $choicesResponse = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $choiceId = $choicesResponse->json('data.choices.0.id');

        $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            [
                'selected' => ['gold'],
                'gold_amount' => 130,
            ]
        );

        // Equipment choices should now be empty (gold mode skips equipment)
        $equipmentAfter = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment");
        $this->assertCount(0, $equipmentAfter->json('data.choices'), 'Equipment choices should be hidden after gold mode selected');
    }

    #[Test]
    public function it_shows_resolved_state_when_refetching_choice(): void
    {
        $choicesResponse = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $choiceId = $choicesResponse->json('data.choices.0.id');

        // Resolve with gold
        $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            [
                'selected' => ['gold'],
                'gold_amount' => 130,
            ]
        );

        // Refetch the choice
        $response = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");

        $response->assertOk()
            ->assertJsonPath('data.choices.0.remaining', 0)
            ->assertJsonPath('data.choices.0.selected', ['gold']);
        // Note: gold_amount is no longer stored in metadata since we use equipment_mode column
    }

    #[Test]
    public function it_can_undo_gold_choice(): void
    {
        $choicesResponse = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $choiceId = $choicesResponse->json('data.choices.0.id');

        // Resolve with gold using class average (125) - undo subtracts class average
        $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            [
                'selected' => ['gold'],
                'gold_amount' => 125, // Using average so undo fully removes it
            ]
        );

        // Undo the choice
        $response = $this->deleteJson("/api/v1/characters/{$this->character->id}/choices/{$choiceId}");

        $response->assertOk()
            ->assertJsonPath('data.message', 'Choice undone successfully');

        $this->character->refresh();

        // Gold should be removed (undo subtracts class average = 125)
        $goldEquipment = $this->character->equipment()->where('item_slug', 'phb:gold-gp')->first();
        $this->assertNull($goldEquipment, 'Gold should be removed after undo');

        // equipment_mode should be reset to null
        $this->assertNull($this->character->equipment_mode);
    }

    #[Test]
    public function it_subtracts_gold_on_undo_when_background_gold_exists(): void
    {
        // Add existing background gold (25 gp)
        CharacterEquipment::create([
            'character_id' => $this->character->id,
            'item_slug' => 'phb:gold-gp',
            'quantity' => 25,
            'equipped' => false,
            'custom_description' => json_encode(['source' => 'background']),
        ]);

        $choicesResponse = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $choiceId = $choicesResponse->json('data.choices.0.id');

        // Resolve with gold using class average (125) - total becomes 150
        // Note: Undo subtracts class average (125), not the actual amount
        $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            [
                'selected' => ['gold'],
                'gold_amount' => 125, // Using average for clean undo
            ]
        );

        // Undo
        $this->deleteJson("/api/v1/characters/{$this->character->id}/choices/{$choiceId}");

        $this->character->refresh();

        // Gold should be reduced back to background amount (150 - 125 = 25)
        $goldEquipment = $this->character->equipment()->where('item_slug', 'phb:gold-gp')->first();
        $this->assertNotNull($goldEquipment, 'Background gold should remain');
        $this->assertEquals(25, $goldEquipment->quantity, 'Gold should be reduced to background amount only');
    }

    #[Test]
    public function it_resolves_equipment_mode_then_equipment_choices_flow(): void
    {
        // This simulates the normal wizard flow when selecting "equipment" mode

        $choicesResponse = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $choiceId = $choicesResponse->json('data.choices.0.id');

        // Select equipment mode (not gold)
        $response = $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            ['selected' => ['equipment']]
        );

        $response->assertOk();

        // equipment_mode column should be set
        $this->character->refresh();
        $this->assertEquals('equipment', $this->character->equipment_mode);

        // Equipment choices should still be available
        $equipmentChoices = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment");
        $this->assertGreaterThan(0, count($equipmentChoices->json('data.choices')), 'Equipment choices should be available in equipment mode');

        // No gold should be added
        $goldEquipment = $this->character->equipment()->where('item_slug', 'phb:gold-gp')->first();
        $this->assertNull($goldEquipment, 'No gold should be added when selecting equipment mode');
    }

    #[Test]
    public function it_validates_gold_amount_is_positive_integer(): void
    {
        $choicesResponse = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $choiceId = $choicesResponse->json('data.choices.0.id');

        // Try with negative gold
        $response = $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            [
                'selected' => ['gold'],
                'gold_amount' => -10,
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['gold_amount']);
    }

    #[Test]
    public function full_wizard_flow_with_gold_selection(): void
    {
        // This test simulates the complete wizard flow as the frontend would do it

        // Step 1: Fetch equipment_mode choices
        $step1 = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $step1->assertOk();

        $equipmentModeChoice = $step1->json('data.choices.0');
        $this->assertNotNull($equipmentModeChoice, 'Equipment mode choice should be available');
        $this->assertEquals(1, $equipmentModeChoice['remaining'], 'Choice should be unresolved');

        // Step 2: Fetch equipment choices (should exist before gold selection)
        $step2 = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment");
        $step2->assertOk();
        $this->assertGreaterThan(0, count($step2->json('data.choices')));

        // Step 3: User selects gold mode and rolls dice (simulated: 130 gp)
        $step3 = $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$equipmentModeChoice['id']}",
            [
                'selected' => ['gold'],
                'gold_amount' => 130,
            ]
        );
        $step3->assertOk();

        // Step 4: Re-fetch equipment choices (should be empty now)
        $step4 = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment");
        $step4->assertOk();
        $this->assertCount(0, $step4->json('data.choices'), 'Equipment choices should be hidden after gold selection');

        // Step 5: Verify final state
        $this->character->refresh();

        // Gold added
        $gold = $this->character->equipment()->where('item_slug', 'phb:gold-gp')->first();
        $this->assertNotNull($gold);
        $this->assertEquals(130, $gold->quantity);

        // equipment_mode column should be set
        $this->assertEquals('gold', $this->character->equipment_mode);

        // Step 6: Re-fetch equipment_mode to verify it shows resolved state (for re-entry flow)
        $step6 = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $step6->assertOk();

        $resolvedChoice = $step6->json('data.choices.0');
        $this->assertEquals(0, $resolvedChoice['remaining'], 'Choice should show as resolved');
        $this->assertEquals(['gold'], $resolvedChoice['selected'], 'Selected should show gold');
    }

    #[Test]
    public function it_validates_gold_amount_cannot_exceed_maximum(): void
    {
        $choicesResponse = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $choiceId = $choicesResponse->json('data.choices.0.id');

        // Try with excessive gold (max is 500)
        $response = $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            [
                'selected' => ['gold'],
                'gold_amount' => 999999,
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['gold_amount']);
    }

    #[Test]
    public function it_validates_gold_amount_cannot_be_zero(): void
    {
        $choicesResponse = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $choiceId = $choicesResponse->json('data.choices.0.id');

        // Try with zero gold
        $response = $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            [
                'selected' => ['gold'],
                'gold_amount' => 0,
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['gold_amount']);
    }

    #[Test]
    public function it_can_switch_from_gold_to_equipment_mode(): void
    {
        $choicesResponse = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $choiceId = $choicesResponse->json('data.choices.0.id');

        // Step 1: Select gold mode using average (125) - undo will subtract the class average
        $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            [
                'selected' => ['gold'],
                'gold_amount' => 125, // Using average so undo fully removes it
            ]
        )->assertOk();

        // Verify gold was added
        $this->character->refresh();
        $this->assertEquals(125, $this->character->equipment()->where('item_slug', 'phb:gold-gp')->first()->quantity);

        // Step 2: Undo the gold choice
        $this->deleteJson("/api/v1/characters/{$this->character->id}/choices/{$choiceId}")
            ->assertOk();

        // Verify gold was removed (undo subtracts class average = 125)
        $this->character->refresh();
        $this->assertNull($this->character->equipment()->where('item_slug', 'phb:gold-gp')->first());

        // Step 3: Select equipment mode instead
        $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            ['selected' => ['equipment']]
        )->assertOk();

        // Verify equipment_mode column was set
        $this->character->refresh();
        $this->assertEquals('equipment', $this->character->equipment_mode);

        // Step 4: Equipment choices should now be available
        $equipmentChoices = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment");
        $this->assertGreaterThan(0, count($equipmentChoices->json('data.choices')));
    }

    #[Test]
    public function it_can_switch_from_equipment_to_gold_mode(): void
    {
        $choicesResponse = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $choiceId = $choicesResponse->json('data.choices.0.id');

        // Step 1: Select equipment mode
        $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            ['selected' => ['equipment']]
        )->assertOk();

        // Verify equipment_mode column was set
        $this->character->refresh();
        $this->assertEquals('equipment', $this->character->equipment_mode);

        // Step 2: Undo and switch to gold
        $this->deleteJson("/api/v1/characters/{$this->character->id}/choices/{$choiceId}")
            ->assertOk();

        $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            [
                'selected' => ['gold'],
                'gold_amount' => 150,
            ]
        )->assertOk();

        // Verify gold was added and equipment_mode column updated
        $this->character->refresh();
        $gold = $this->character->equipment()->where('item_slug', 'phb:gold-gp')->first();
        $this->assertNotNull($gold);
        $this->assertEquals(150, $gold->quantity);

        $this->assertEquals('gold', $this->character->equipment_mode);

        // Equipment choices should be hidden
        $equipmentChoices = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment");
        $this->assertCount(0, $equipmentChoices->json('data.choices'));
    }

    // ===================================================================================
    // BUG REGRESSION TESTS - These tests expose the bugs reported in the issue
    // ===================================================================================

    #[Test]
    public function bug_gold_reselection_with_different_amount_calculates_correctly(): void
    {
        // Bug: When selecting gold a second time with a different amount,
        // the gold is not correctly recalculated. It should remove ALL previous
        // starting wealth gold and add the new amount, preserving background gold.

        // Setup: Add background gold (25 gp)
        CharacterEquipment::create([
            'character_id' => $this->character->id,
            'item_slug' => 'phb:gold-gp',
            'quantity' => 25,
            'equipped' => false,
            'custom_description' => json_encode(['source' => 'background']),
        ]);

        $choicesResponse = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $choiceId = $choicesResponse->json('data.choices.0.id');

        // First selection: Roll 130 gp (total should be 25 + 130 = 155)
        $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            [
                'selected' => ['gold'],
                'gold_amount' => 130,
            ]
        )->assertOk();

        $this->character->refresh();
        $totalGoldAfterFirst = $this->character->equipment()->where('item_slug', 'phb:gold-gp')->sum('quantity');
        $this->assertEquals(155, $totalGoldAfterFirst, 'After first selection: 25 (background) + 130 (rolled) = 155');

        // Second selection: Roll a different amount (100 gp)
        // Expected: Remove the 130 rolled gold, add 100 → 25 + 100 = 125
        // Bug: Uses class average (125) to subtract instead of actual (130), resulting in wrong amount
        $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            [
                'selected' => ['gold'],
                'gold_amount' => 100,
            ]
        )->assertOk();

        $this->character->refresh();
        $totalGoldAfterSecond = $this->character->equipment()->where('item_slug', 'phb:gold-gp')->sum('quantity');

        // Correct behavior: 25 (background) + 100 (new rolled) = 125
        $this->assertEquals(125, $totalGoldAfterSecond, 'After re-selection: 25 (background) + 100 (new rolled) = 125');
    }

    #[Test]
    public function bug_switching_to_equipment_preserves_background_gold(): void
    {
        // Bug: When switching from gold mode to equipment mode,
        // ALL gold is removed including background gold.
        // Background gold should be preserved.

        // Setup: Add background gold (25 gp)
        CharacterEquipment::create([
            'character_id' => $this->character->id,
            'item_slug' => 'phb:gold-gp',
            'quantity' => 25,
            'equipped' => false,
            'custom_description' => json_encode(['source' => 'background']),
        ]);

        $choicesResponse = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $choiceId = $choicesResponse->json('data.choices.0.id');

        // Select gold mode with 130 gp (total: 25 + 130 = 155)
        $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            [
                'selected' => ['gold'],
                'gold_amount' => 130,
            ]
        )->assertOk();

        $this->character->refresh();
        $totalGold = $this->character->equipment()->where('item_slug', 'phb:gold-gp')->sum('quantity');
        $this->assertEquals(155, $totalGold, 'Total gold should be 155 (25 background + 130 rolled)');

        // Now switch to equipment mode
        // Expected: Remove the 130 rolled gold, keep the 25 background gold
        $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            ['selected' => ['equipment']]
        )->assertOk();

        $this->character->refresh();

        // Verify equipment_mode changed
        $this->assertEquals('equipment', $this->character->equipment_mode);

        // Background gold (25 gp) should still exist
        $totalGoldAfterSwitch = $this->character->equipment()->where('item_slug', 'phb:gold-gp')->sum('quantity');
        $this->assertEquals(25, $totalGoldAfterSwitch, 'Only background gold (25 gp) should remain');
    }

    #[Test]
    public function bug_equipment_choices_show_after_switching_from_gold(): void
    {
        // Bug: After switching from gold mode to equipment mode,
        // equipment choices do not show up in the frontend.

        $choicesResponse = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $choiceId = $choicesResponse->json('data.choices.0.id');

        // First, select gold mode
        $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            [
                'selected' => ['gold'],
                'gold_amount' => 130,
            ]
        )->assertOk();

        // Verify equipment choices are hidden in gold mode
        $equipmentChoicesInGold = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment");
        $this->assertCount(0, $equipmentChoicesInGold->json('data.choices'), 'Equipment choices should be hidden in gold mode');

        // Now switch to equipment mode
        $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            ['selected' => ['equipment']]
        )->assertOk();

        $this->character->refresh();
        $this->assertEquals('equipment', $this->character->equipment_mode);

        // Equipment choices should now be visible
        $equipmentChoicesAfterSwitch = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment");
        $this->assertGreaterThan(
            0,
            count($equipmentChoicesAfterSwitch->json('data.choices')),
            'Equipment choices should be visible after switching from gold to equipment mode'
        );
    }

    #[Test]
    public function bug_fixed_equipment_populated_after_resolving_all_choices(): void
    {
        // Bug: After selecting equipment mode and resolving all equipment choices,
        // fixed (non-choice) equipment from the class is not added.

        // First, add some fixed equipment to the class
        $fixedItem = Item::factory()->create(['name' => 'Shield', 'full_slug' => 'phb:shield']);
        \App\Models\EntityItem::create([
            'reference_type' => \App\Models\CharacterClass::class,
            'reference_id' => $this->class->id,
            'item_id' => $fixedItem->id,
            'is_choice' => false,  // Fixed equipment, not a choice
            'quantity' => 1,
        ]);

        $choicesResponse = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $choiceId = $choicesResponse->json('data.choices.0.id');

        // Select equipment mode
        $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            ['selected' => ['equipment']]
        )->assertOk();

        // Now get and resolve all equipment choices
        $equipmentChoices = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment");
        foreach ($equipmentChoices->json('data.choices') as $equipChoice) {
            // Select the first option for each choice
            $firstOption = $equipChoice['options'][0]['option'];
            $this->postJson(
                "/api/v1/characters/{$this->character->id}/choices/{$equipChoice['id']}",
                ['selected' => [$firstOption]]
            )->assertOk();
        }

        // Verify all equipment choices are resolved
        $remainingChoices = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment");
        $pendingCount = collect($remainingChoices->json('data.choices'))->where('remaining', '>', 0)->count();
        $this->assertEquals(0, $pendingCount, 'All equipment choices should be resolved');

        // Check that fixed equipment was populated
        $this->character->refresh();
        $hasShield = $this->character->equipment()->where('item_slug', 'phb:shield')->exists();
        $this->assertTrue($hasShield, 'Fixed equipment (shield) should be populated after all choices resolved');
    }
}
