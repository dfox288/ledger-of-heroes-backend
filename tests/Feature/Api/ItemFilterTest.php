<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemFilterTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    #[Test]
    public function it_filters_items_by_minimum_strength_requirement()
    {
        $strength = AbilityScore::where('code', 'STR')->first();

        // Item with strength_requirement column (legacy)
        $plateArmor = Item::factory()->create([
            'name' => 'Plate Armor',
            'strength_requirement' => 15,
        ]);

        // Item with EntityPrerequisite (new system)
        $heavyShield = Item::factory()->create(['name' => 'Heavy Shield']);
        $heavyShield->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);

        // Item without strength requirement
        $leatherArmor = Item::factory()->create([
            'name' => 'Leather Armor',
            'strength_requirement' => null,
        ]);

        // Test min_strength=15 (should get plate armor)
        $response = $this->getJson('/api/v1/items?min_strength=15');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Plate Armor');

        // Test min_strength=13 (should get both)
        $response = $this->getJson('/api/v1/items?min_strength=13');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_filters_items_with_prerequisites()
    {
        $itemWithPrereq = Item::factory()->create([
            'name' => 'Plate Armor',
            'strength_requirement' => 15,
        ]);

        $itemWithout = Item::factory()->create([
            'name' => 'Leather Armor',
            'strength_requirement' => null,
        ]);

        $response = $this->getJson('/api/v1/items?has_prerequisites=true');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Plate Armor');
    }

    #[Test]
    public function it_filters_items_with_new_prerequisite_system()
    {
        $strength = AbilityScore::where('code', 'STR')->first();

        $itemWithNewPrereq = Item::factory()->create(['name' => 'Heavy Shield']);
        $itemWithNewPrereq->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 15,
            'group_id' => 1,
        ]);

        $itemWithout = Item::factory()->create(['name' => 'Leather Armor']);

        $response = $this->getJson('/api/v1/items?has_prerequisites=true');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Heavy Shield');
    }

    #[Test]
    public function it_supports_both_legacy_and_new_prerequisite_systems()
    {
        $strength = AbilityScore::where('code', 'STR')->first();

        // Legacy system (strength_requirement column)
        $plateArmor = Item::factory()->create([
            'name' => 'Plate Armor',
            'strength_requirement' => 15,
        ]);

        // New system (entity_prerequisites table)
        $heavyShield = Item::factory()->create(['name' => 'Heavy Shield']);
        $heavyShield->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 15,
            'group_id' => 1,
        ]);

        // Both should be returned
        $response = $this->getJson('/api/v1/items?min_strength=15');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Plate Armor', $names);
        $this->assertContains('Heavy Shield', $names);
    }

    #[Test]
    public function it_paginates_filtered_items()
    {
        // Create 25 items with strength requirements
        for ($i = 1; $i <= 25; $i++) {
            Item::factory()->create([
                'name' => "Armor {$i}",
                'strength_requirement' => 15,
            ]);
        }

        $response = $this->getJson('/api/v1/items?min_strength=15&per_page=10');
        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.total', 25);
        $response->assertJsonPath('meta.per_page', 10);
    }

    #[Test]
    public function it_returns_empty_when_no_items_match_strength_requirement()
    {
        Item::factory()->create([
            'name' => 'Leather Armor',
            'strength_requirement' => null,
        ]);

        Item::factory()->create([
            'name' => 'Chain Mail',
            'strength_requirement' => 13,
        ]);

        $response = $this->getJson('/api/v1/items?min_strength=20');
        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }
}
