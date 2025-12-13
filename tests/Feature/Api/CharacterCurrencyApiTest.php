<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterCurrencyApiTest extends TestCase
{
    use RefreshDatabase;

    private Character $character;

    protected function setUp(): void
    {
        parent::setUp();

        $this->character = Character::factory()->create();

        // Create the currency items
        Item::factory()->create(['slug' => 'phb:platinum-pp', 'name' => 'Platinum (pp)']);
        Item::factory()->create(['slug' => 'phb:gold-gp', 'name' => 'Gold (gp)']);
        Item::factory()->create(['slug' => 'phb:electrum-ep', 'name' => 'Electrum (ep)']);
        Item::factory()->create(['slug' => 'phb:silver-sp', 'name' => 'Silver (sp)']);
        Item::factory()->create(['slug' => 'phb:copper-cp', 'name' => 'Copper (cp)']);
    }

    private function setCurrency(int $pp = 0, int $gp = 0, int $ep = 0, int $sp = 0, int $cp = 0): void
    {
        $currencies = [
            'phb:platinum-pp' => $pp,
            'phb:gold-gp' => $gp,
            'phb:electrum-ep' => $ep,
            'phb:silver-sp' => $sp,
            'phb:copper-cp' => $cp,
        ];

        foreach ($currencies as $slug => $quantity) {
            if ($quantity > 0) {
                CharacterEquipment::create([
                    'character_id' => $this->character->id,
                    'item_slug' => $slug,
                    'quantity' => $quantity,
                ]);
            }
        }
    }

    // =====================
    // Basic Operations
    // =====================

    #[Test]
    public function it_adds_currency_with_plus_prefix(): void
    {
        $this->setCurrency(gp: 10);

        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            ['gp' => '+5']
        );

        $response->assertOk()
            ->assertJsonPath('data.gp', 15);
    }

    #[Test]
    public function it_subtracts_currency_with_minus_prefix(): void
    {
        $this->setCurrency(gp: 10);

        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            ['gp' => '-5']
        );

        $response->assertOk()
            ->assertJsonPath('data.gp', 5);
    }

    #[Test]
    public function it_sets_currency_to_absolute_value_without_prefix(): void
    {
        $this->setCurrency(gp: 10);

        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            ['gp' => '25']
        );

        $response->assertOk()
            ->assertJsonPath('data.gp', 25);
    }

    #[Test]
    public function it_handles_multiple_currency_changes_in_one_request(): void
    {
        $this->setCurrency(gp: 100, sp: 50);

        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            [
                'gp' => '-10',
                'sp' => '+20',
                'cp' => '100',
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.gp', 90)
            ->assertJsonPath('data.sp', 70)
            ->assertJsonPath('data.cp', 100);
    }

    #[Test]
    public function it_returns_all_currency_types_in_response(): void
    {
        $this->setCurrency(pp: 1, gp: 10, ep: 5, sp: 50, cp: 100);

        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            ['gp' => '+1']
        );

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['pp', 'gp', 'ep', 'sp', 'cp'],
            ])
            ->assertJsonPath('data.pp', 1)
            ->assertJsonPath('data.gp', 11)
            ->assertJsonPath('data.ep', 5)
            ->assertJsonPath('data.sp', 50)
            ->assertJsonPath('data.cp', 100);
    }

    // =====================
    // Auto-Conversion (Making Change)
    // =====================

    #[Test]
    public function it_converts_gold_to_silver_when_silver_insufficient(): void
    {
        // Have 5 GP, 0 SP. Request -10 SP. Should convert 1 GP to 10 SP.
        $this->setCurrency(gp: 5, sp: 0);

        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            ['sp' => '-10']
        );

        $response->assertOk()
            ->assertJsonPath('data.gp', 4)
            ->assertJsonPath('data.sp', 0);
    }

    #[Test]
    public function it_converts_gold_to_copper_when_copper_insufficient(): void
    {
        // Have 5 GP, 0 CP. Request -50 CP.
        // Algorithm cascades: 1 GP → 10 SP, then 5 SP → 50 CP.
        // Result: 4 GP, 5 SP, 0 CP (change kept in SP)
        $this->setCurrency(gp: 5, cp: 0);

        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            ['cp' => '-50']
        );

        $response->assertOk()
            ->assertJsonPath('data.gp', 4)
            ->assertJsonPath('data.sp', 5)
            ->assertJsonPath('data.cp', 0);
    }

    #[Test]
    public function it_converts_platinum_through_gold_to_silver(): void
    {
        // Have 1 PP, 0 GP, 0 SP. Request -5 SP. Should convert 1 PP to 10 GP, 1 GP to 10 SP, subtract 5.
        $this->setCurrency(pp: 1, gp: 0, sp: 0);

        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            ['sp' => '-5']
        );

        $response->assertOk()
            ->assertJsonPath('data.pp', 0)
            ->assertJsonPath('data.gp', 9)
            ->assertJsonPath('data.sp', 5);
    }

    #[Test]
    public function it_converts_silver_to_copper_when_copper_insufficient(): void
    {
        // Have 0 GP, 5 SP, 0 CP. Request -20 CP. Should convert 2 SP to 20 CP, subtract 20.
        $this->setCurrency(sp: 5, cp: 0);

        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            ['cp' => '-20']
        );

        $response->assertOk()
            ->assertJsonPath('data.sp', 3)
            ->assertJsonPath('data.cp', 0);
    }

    #[Test]
    public function it_handles_electrum_in_conversions(): void
    {
        // Have 0 GP, 2 EP (= 1 GP = 10 SP), 0 SP. Request -5 SP.
        // Should convert 1 EP to 5 SP, subtract 5.
        $this->setCurrency(ep: 2, sp: 0);

        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            ['sp' => '-5']
        );

        $response->assertOk()
            ->assertJsonPath('data.ep', 1)
            ->assertJsonPath('data.sp', 0);
    }

    #[Test]
    public function it_uses_partial_coins_correctly(): void
    {
        // Have 5 GP, 3 SP. Request -7 SP. Should convert 1 GP to 10 SP (total 13), subtract 7.
        $this->setCurrency(gp: 5, sp: 3);

        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            ['sp' => '-7']
        );

        $response->assertOk()
            ->assertJsonPath('data.gp', 4)
            ->assertJsonPath('data.sp', 6);
    }

    // =====================
    // Insufficient Funds
    // =====================

    #[Test]
    public function it_returns_422_when_insufficient_funds(): void
    {
        $this->setCurrency(gp: 1);

        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            ['gp' => '-10']
        );

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Insufficient funds');
    }

    #[Test]
    public function it_returns_422_when_conversion_cannot_cover_cost(): void
    {
        // Have 1 GP (= 100 CP). Request -150 CP. Cannot afford.
        $this->setCurrency(gp: 1, cp: 0);

        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            ['cp' => '-150']
        );

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Insufficient funds');
    }

    #[Test]
    public function it_returns_422_with_detailed_error_message(): void
    {
        $this->setCurrency(cp: 20);

        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            ['cp' => '-50']
        );

        $response->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['currency']]);
    }

    // =====================
    // Validation
    // =====================

    #[Test]
    public function it_validates_currency_format(): void
    {
        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            ['gp' => 'invalid']
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['gp']);
    }

    #[Test]
    public function it_rejects_empty_request(): void
    {
        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            []
        );

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_rejects_unknown_currency_types(): void
    {
        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            ['gold_pieces' => '10']
        );

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_validates_max_currency_value(): void
    {
        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            ['gp' => '99999999999']
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['gp']);
    }

    // =====================
    // Edge Cases
    // =====================

    #[Test]
    public function it_handles_zero_value_changes(): void
    {
        $this->setCurrency(gp: 10);

        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            ['gp' => '+0']
        );

        $response->assertOk()
            ->assertJsonPath('data.gp', 10);
    }

    #[Test]
    public function it_handles_setting_to_zero(): void
    {
        $this->setCurrency(gp: 10);

        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            ['gp' => '0']
        );

        $response->assertOk()
            ->assertJsonPath('data.gp', 0);
    }

    #[Test]
    public function it_creates_currency_entry_when_adding_to_zero(): void
    {
        // Character has no currency items at all

        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            ['gp' => '+100']
        );

        $response->assertOk()
            ->assertJsonPath('data.gp', 100);

        // Verify the equipment record was created
        $this->assertDatabaseHas('character_equipment', [
            'character_id' => $this->character->id,
            'item_slug' => 'phb:gold-gp',
            'quantity' => 100,
        ]);
    }

    #[Test]
    public function it_returns_404_for_nonexistent_character(): void
    {
        $response = $this->patchJson(
            '/api/v1/characters/nonexistent-id/currency',
            ['gp' => '+10']
        );

        $response->assertNotFound();
    }

    // =====================
    // Complex Scenarios
    // =====================

    #[Test]
    public function it_handles_complex_multi_currency_conversion(): void
    {
        // Start: 1 PP (1000 CP), 5 GP (500 CP), 10 SP (100 CP), 50 CP = 1650 CP total
        // Request: -75 CP, -200 SP (-2000 CP), +5 GP (+500 CP)
        //
        // After +5 GP: 1 PP, 10 GP, 10 SP, 50 CP = 2150 CP
        // Subtractions: 75 CP + 2000 SP = 2075 CP
        // 2150 >= 2075, so we CAN afford it
        // Remaining: 2150 - 2075 = 75 CP worth
        $this->setCurrency(pp: 1, gp: 5, sp: 10, cp: 50);

        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            [
                'cp' => '-75',
                'sp' => '-200',
                'gp' => '+5',
            ]
        );

        // Should succeed - total value is sufficient
        $response->assertOk();

        // Verify we have approximately 75 CP worth remaining
        $data = $response->json('data');
        $totalCopperRemaining = $data['pp'] * 1000 + $data['gp'] * 100 + $data['ep'] * 50 + $data['sp'] * 10 + $data['cp'];
        expect($totalCopperRemaining)->toBe(75);
    }

    #[Test]
    public function it_processes_additions_before_subtractions(): void
    {
        // Have 0 GP. Add 10 GP then subtract 5 GP. Should work.
        $this->setCurrency(gp: 0);

        $response = $this->patchJson(
            "/api/v1/characters/{$this->character->public_id}/currency",
            [
                'gp' => '+10',
                'sp' => '-50', // This would need GP conversion
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.gp', 5)  // 10 - 5 (converted to cover 50 SP)
            ->assertJsonPath('data.sp', 0);
    }
}
