<?php

namespace Tests\Unit\Factories;

use App\Enums\DataTableType;
use App\Models\EntityDataTable;
use App\Models\EntityDataTableEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class EntityDataTableFactoriesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_data_table()
    {
        $table = EntityDataTable::factory()->create();

        $this->assertInstanceOf(EntityDataTable::class, $table);
        $this->assertNotNull($table->reference_type);
        $this->assertNotNull($table->table_name);
        $this->assertNotNull($table->dice_type);
        $this->assertEquals(DataTableType::RANDOM, $table->table_type);
    }

    #[Test]
    public function it_creates_data_table_entry()
    {
        $entry = EntityDataTableEntry::factory()->create();

        $this->assertInstanceOf(EntityDataTableEntry::class, $entry);
        $this->assertNotNull($entry->entity_data_table_id);
        $this->assertNotNull($entry->result_text);
    }

    #[Test]
    public function it_creates_multiple_entries_for_table()
    {
        $table = EntityDataTable::factory()->create(['dice_type' => 'd6']);

        $entries = EntityDataTableEntry::factory()
            ->count(6)
            ->forTable($table)
            ->create();

        $this->assertCount(6, $entries);
        $this->assertEquals($table->id, $entries->first()->entity_data_table_id);
    }

    #[Test]
    public function it_creates_table_with_specific_type()
    {
        $table = EntityDataTable::factory()
            ->ofType(DataTableType::DAMAGE)
            ->create();

        $this->assertEquals(DataTableType::DAMAGE, $table->table_type);
    }
}
