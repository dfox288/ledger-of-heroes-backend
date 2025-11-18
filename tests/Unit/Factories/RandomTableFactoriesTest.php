<?php

namespace Tests\Unit\Factories;

use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RandomTableFactoriesTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_random_table()
    {
        $table = RandomTable::factory()->create();

        $this->assertInstanceOf(RandomTable::class, $table);
        $this->assertNotNull($table->reference_type);
        $this->assertNotNull($table->table_name);
        $this->assertNotNull($table->dice_type);
    }

    /** @test */
    public function it_creates_random_table_entry()
    {
        $entry = RandomTableEntry::factory()->create();

        $this->assertInstanceOf(RandomTableEntry::class, $entry);
        $this->assertNotNull($entry->random_table_id);
        $this->assertNotNull($entry->result);
    }

    /** @test */
    public function it_creates_multiple_entries_for_table()
    {
        $table = RandomTable::factory()->create(['dice_type' => 'd6']);

        $entries = RandomTableEntry::factory()
            ->count(6)
            ->forTable($table)
            ->create();

        $this->assertCount(6, $entries);
        $this->assertEquals($table->id, $entries->first()->random_table_id);
    }
}
