<?php

namespace Tests\Feature\Models;

use App\Models\Race;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RandomTableModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_random_table_belongs_to_race_via_polymorphic(): void
    {
        $race = Race::factory()->create();

        $table = RandomTable::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'table_name' => 'Size Modifier',
            'dice_type' => '2d8',
        ]);

        $this->assertEquals($race->id, $table->reference->id);
        $this->assertInstanceOf(Race::class, $table->reference);
    }

    public function test_random_table_has_many_entries(): void
    {
        $race = Race::factory()->create();

        $table = RandomTable::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'table_name' => 'Size Modifier',
            'dice_type' => '2d8',
        ]);

        RandomTableEntry::create([
            'random_table_id' => $table->id,
            'roll_value' => '2',
            'result' => 'Minimum roll',
            'sort_order' => 1,
        ]);

        RandomTableEntry::create([
            'random_table_id' => $table->id,
            'roll_value' => '16',
            'result' => 'Maximum roll',
            'sort_order' => 2,
        ]);

        $this->assertCount(2, $table->entries);
    }
}
