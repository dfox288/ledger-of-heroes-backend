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

        // Verify marker was created
        $marker = $this->character->equipment()->where('item_slug', 'equipment_mode_marker')->first();
        $this->assertNotNull($marker, 'Equipment mode marker should be created');

        $metadata = json_decode($marker->custom_description, true);
        $this->assertEquals('gold', $metadata['equipment_mode']);
        $this->assertEquals(130, $metadata['gold_amount']);
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
    public function it_merges_gold_with_existing_background_gold(): void
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

        // Should have single merged gold entry (25 + 130 = 155)
        $goldEntries = $this->character->equipment()->where('item_slug', 'phb:gold-gp')->get();
        $this->assertCount(1, $goldEntries, 'Should have single merged gold entry');
        $this->assertEquals(155, $goldEntries->first()->quantity, 'Gold should be merged (25 + 130 = 155)');
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
            ->assertJsonPath('data.choices.0.selected', ['gold'])
            ->assertJsonPath('data.choices.0.metadata.gold_amount', 130);
    }

    #[Test]
    public function it_can_undo_gold_choice(): void
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

        // Undo the choice
        $response = $this->deleteJson("/api/v1/characters/{$this->character->id}/choices/{$choiceId}");

        $response->assertOk()
            ->assertJsonPath('data.message', 'Choice undone successfully');

        $this->character->refresh();

        // Gold should be removed
        $goldEquipment = $this->character->equipment()->where('item_slug', 'phb:gold-gp')->first();
        $this->assertNull($goldEquipment, 'Gold should be removed after undo');

        // Marker should be removed
        $marker = $this->character->equipment()->where('item_slug', 'equipment_mode_marker')->first();
        $this->assertNull($marker, 'Marker should be removed after undo');
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

        // Resolve with gold (130) - total becomes 155
        $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            [
                'selected' => ['gold'],
                'gold_amount' => 130,
            ]
        );

        // Undo
        $this->deleteJson("/api/v1/characters/{$this->character->id}/choices/{$choiceId}");

        $this->character->refresh();

        // Gold should be reduced back to background amount (155 - 130 = 25)
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

        // Marker should be created with equipment mode
        $this->character->refresh();
        $marker = $this->character->equipment()->where('item_slug', 'equipment_mode_marker')->first();
        $this->assertNotNull($marker);

        $metadata = json_decode($marker->custom_description, true);
        $this->assertEquals('equipment', $metadata['equipment_mode']);

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

        // Marker created
        $marker = $this->character->equipment()->where('item_slug', 'equipment_mode_marker')->first();
        $this->assertNotNull($marker);
        $metadata = json_decode($marker->custom_description, true);
        $this->assertEquals('gold', $metadata['equipment_mode']);
        $this->assertEquals(130, $metadata['gold_amount']);

        // Step 6: Re-fetch equipment_mode to verify it shows resolved state (for re-entry flow)
        $step6 = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment_mode");
        $step6->assertOk();

        $resolvedChoice = $step6->json('data.choices.0');
        $this->assertEquals(0, $resolvedChoice['remaining'], 'Choice should show as resolved');
        $this->assertEquals(['gold'], $resolvedChoice['selected'], 'Selected should show gold');
        $this->assertEquals(130, $resolvedChoice['metadata']['gold_amount'], 'Metadata should include gold_amount for re-entry');
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

        // Step 1: Select gold mode
        $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            [
                'selected' => ['gold'],
                'gold_amount' => 130,
            ]
        )->assertOk();

        // Verify gold was added
        $this->character->refresh();
        $this->assertEquals(130, $this->character->equipment()->where('item_slug', 'phb:gold-gp')->first()->quantity);

        // Step 2: Undo the gold choice
        $this->deleteJson("/api/v1/characters/{$this->character->id}/choices/{$choiceId}")
            ->assertOk();

        // Verify gold was removed
        $this->character->refresh();
        $this->assertNull($this->character->equipment()->where('item_slug', 'phb:gold-gp')->first());

        // Step 3: Select equipment mode instead
        $this->postJson(
            "/api/v1/characters/{$this->character->id}/choices/{$choiceId}",
            ['selected' => ['equipment']]
        )->assertOk();

        // Verify marker shows equipment mode
        $this->character->refresh();
        $marker = $this->character->equipment()->where('item_slug', 'equipment_mode_marker')->first();
        $this->assertNotNull($marker);
        $metadata = json_decode($marker->custom_description, true);
        $this->assertEquals('equipment', $metadata['equipment_mode']);
        $this->assertNull($metadata['gold_amount']);

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

        // Verify marker shows equipment mode
        $this->character->refresh();
        $marker = $this->character->equipment()->where('item_slug', 'equipment_mode_marker')->first();
        $metadata = json_decode($marker->custom_description, true);
        $this->assertEquals('equipment', $metadata['equipment_mode']);

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

        // Verify gold was added and marker updated
        $this->character->refresh();
        $gold = $this->character->equipment()->where('item_slug', 'phb:gold-gp')->first();
        $this->assertNotNull($gold);
        $this->assertEquals(150, $gold->quantity);

        $marker = $this->character->equipment()->where('item_slug', 'equipment_mode_marker')->first();
        $metadata = json_decode($marker->custom_description, true);
        $this->assertEquals('gold', $metadata['equipment_mode']);
        $this->assertEquals(150, $metadata['gold_amount']);

        // Equipment choices should be hidden
        $equipmentChoices = $this->getJson("/api/v1/characters/{$this->character->id}/pending-choices?type=equipment");
        $this->assertCount(0, $equipmentChoices->json('data.choices'));
    }
}
