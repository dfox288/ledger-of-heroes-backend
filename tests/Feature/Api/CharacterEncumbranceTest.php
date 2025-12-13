<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\Item;
use App\Models\ItemType;
use Database\Seeders\LookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for encumbrance tracking and effects.
 *
 * D&D 5e Variant Encumbrance Rules:
 * - STR × 5 lbs or less: Unencumbered
 * - Over STR × 5 lbs: Encumbered (-10 ft speed)
 * - Over STR × 10 lbs: Heavily Encumbered (-20 ft speed, disadvantage on ability checks, attacks, and saves using STR, DEX, CON)
 *
 * Covers issue #498.3.3
 */
class CharacterEncumbranceTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = LookupSeeder::class;

    private Item $heavyArmor;

    private Item $lightItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createItemFixtures();
    }

    private function createItemFixtures(): void
    {
        $heavyArmorType = ItemType::where('code', 'HA')->first();
        $wonderousType = ItemType::where('code', 'W')->first();

        $this->heavyArmor = Item::create([
            'name' => 'Plate Armor',
            'slug' => 'test:plate-armor',
            'item_type_id' => $heavyArmorType->id,
            'weight' => 65.0,
            'rarity' => 'common',
            'description' => 'Heavy plate armor.',
        ]);

        $this->lightItem = Item::create([
            'name' => 'Potion',
            'slug' => 'test:potion',
            'item_type_id' => $wonderousType->id,
            'weight' => 0.5,
            'rarity' => 'common',
            'description' => 'A small potion.',
        ]);
    }

    // =============================
    // Current Weight Calculation
    // =============================

    #[Test]
    public function it_exposes_current_weight_in_stats(): void
    {
        $character = Character::factory()->create(['strength' => 10]);

        CharacterEquipment::factory()
            ->withItem($this->heavyArmor)
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk();
        expect((float) $response->json('data.current_weight'))->toBe(65.0);
    }

    #[Test]
    public function it_calculates_weight_with_quantity(): void
    {
        $character = Character::factory()->create(['strength' => 10]);

        CharacterEquipment::factory()
            ->withItem($this->lightItem)
            ->create([
                'character_id' => $character->id,
                'quantity' => 10,
            ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk();
        expect((float) $response->json('data.current_weight'))->toBe(5.0); // 0.5 * 10
    }

    #[Test]
    public function it_shows_zero_weight_when_no_equipment(): void
    {
        $character = Character::factory()->create(['strength' => 10]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk();
        expect((float) $response->json('data.current_weight'))->toBe(0.0);
    }

    // =============================
    // Encumbrance Status
    // =============================

    #[Test]
    public function it_shows_unencumbered_status_when_under_threshold(): void
    {
        // STR 10 = carrying capacity 150 lbs
        // STR × 5 = 50 lbs threshold for encumbered
        $character = Character::factory()->create(['strength' => 10]);

        // Add 40 lbs of equipment (under 50 lb threshold)
        $item40lbs = Item::create([
            'name' => 'Medium Pack',
            'slug' => 'test:medium-pack',
            'item_type_id' => ItemType::where('code', 'W')->first()->id,
            'weight' => 40.0,
            'rarity' => 'common',
            'description' => 'A medium pack.',
        ]);
        CharacterEquipment::factory()
            ->withItem($item40lbs)
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.encumbrance.status', 'unencumbered')
            ->assertJsonPath('data.encumbrance.speed_penalty', 0);
    }

    #[Test]
    public function it_shows_encumbered_status_over_5x_strength(): void
    {
        // STR 10 = 50 lbs threshold for encumbered
        $character = Character::factory()->create(['strength' => 10]);

        // Add 55 lbs of equipment (over 50 lb threshold)
        $item55lbs = Item::create([
            'name' => 'Heavy Pack',
            'slug' => 'test:heavy-pack',
            'item_type_id' => ItemType::where('code', 'W')->first()->id,
            'weight' => 55.0,
            'rarity' => 'common',
            'description' => 'A heavy pack.',
        ]);
        CharacterEquipment::factory()
            ->withItem($item55lbs)
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.encumbrance.status', 'encumbered')
            ->assertJsonPath('data.encumbrance.speed_penalty', 10);
    }

    #[Test]
    public function it_shows_heavily_encumbered_status_over_10x_strength(): void
    {
        // STR 10 = 100 lbs threshold for heavily encumbered
        $character = Character::factory()->create(['strength' => 10]);

        // Add 110 lbs of equipment (over 100 lb threshold)
        $item110lbs = Item::create([
            'name' => 'Very Heavy Pack',
            'slug' => 'test:very-heavy-pack',
            'item_type_id' => ItemType::where('code', 'W')->first()->id,
            'weight' => 110.0,
            'rarity' => 'common',
            'description' => 'A very heavy pack.',
        ]);
        CharacterEquipment::factory()
            ->withItem($item110lbs)
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.encumbrance.status', 'heavily_encumbered')
            ->assertJsonPath('data.encumbrance.speed_penalty', 20)
            ->assertJsonPath('data.encumbrance.has_disadvantage', true);
    }

    #[Test]
    public function it_exposes_encumbrance_thresholds(): void
    {
        // STR 10: threshold1 = 50, threshold2 = 100
        $character = Character::factory()->create(['strength' => 10]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.encumbrance.threshold_encumbered', 50)
            ->assertJsonPath('data.encumbrance.threshold_heavily_encumbered', 100);
    }

    #[Test]
    public function it_handles_null_strength_gracefully(): void
    {
        $character = Character::factory()->create(['strength' => null]);

        CharacterEquipment::factory()
            ->withItem($this->heavyArmor)
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.encumbrance', null);
    }
}
