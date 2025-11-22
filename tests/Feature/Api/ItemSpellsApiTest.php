<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItemSpellsApiTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_serializes_spells_with_charge_costs_correctly()
    {
        // Create item and spell
        $staff = Item::factory()->create([
            'name' => 'Staff of Testing',
            'slug' => 'staff-of-testing',
            'charges_max' => 10,
            'recharge_formula' => '1d6+4',
            'recharge_timing' => 'dawn',
        ]);

        $cureWounds = Spell::factory()->create([
            'name' => 'Cure Wounds',
            'level' => 1,
        ]);

        // Create spell association with charge costs
        DB::table('entity_spells')->insert([
            'reference_type' => Item::class,
            'reference_id' => $staff->id,
            'spell_id' => $cureWounds->id,
            'charges_cost_min' => 1,
            'charges_cost_max' => 4,
            'charges_cost_formula' => '1 per spell level',
        ]);

        // Load spells relationship and serialize
        $staff->load('spells');
        $resource = new \App\Http\Resources\ItemResource($staff);
        $response = $resource->toArray(request());

        $this->assertEquals('Staff of Testing', $response['name']);
        $this->assertEquals(10, $response['charges_max']);

        // Spells is a ResourceCollection, need to resolve it
        $spells = $response['spells']->resolve();

        $this->assertCount(1, $spells);
        $this->assertEquals('Cure Wounds', $spells[0]['name']);
        $this->assertEquals(1, $spells[0]['charges_cost_min']);
        $this->assertEquals(4, $spells[0]['charges_cost_max']);
        $this->assertEquals('1 per spell level', $spells[0]['charges_cost_formula']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_spells_array_for_items_without_spells()
    {
        $wand = Item::factory()->create([
            'name' => 'Wand of Testing',
            'slug' => 'wand-of-testing',
            'charges_max' => 3,
        ]);

        $wand->load('spells');
        $resource = new \App\Http\Resources\ItemResource($wand);
        $response = $resource->toArray(request());

        $this->assertEquals('Wand of Testing', $response['name']);

        // Resolve the ResourceCollection to array
        $spells = $response['spells']->resolve();
        $this->assertEquals([], $spells);
    }
}
