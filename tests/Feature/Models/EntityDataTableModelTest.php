<?php

namespace Tests\Feature\Models;

use App\Enums\DataTableType;
use App\Models\EntityDataTable;
use App\Models\EntityDataTableEntry;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class EntityDataTableModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function data_table_belongs_to_race_via_polymorphic(): void
    {
        $race = Race::factory()->create();

        $table = EntityDataTable::factory()->forEntity(Race::class, $race->id)->create([
            'table_name' => 'Size Modifier',
            'dice_type' => '2d8',
            'table_type' => DataTableType::MODIFIER,
        ]);

        $this->assertEquals($race->id, $table->reference->id);
        $this->assertInstanceOf(Race::class, $table->reference);
    }

    #[Test]
    public function data_table_has_many_entries(): void
    {
        $race = Race::factory()->create();

        $table = EntityDataTable::factory()->forEntity(Race::class, $race->id)->create([
            'table_name' => 'Size Modifier',
            'dice_type' => '2d8',
        ]);

        EntityDataTableEntry::factory()->create([
            'entity_data_table_id' => $table->id,
            'roll_min' => 2,
            'roll_max' => 2,
            'result_text' => 'Minimum roll',
            'sort_order' => 1,
        ]);

        EntityDataTableEntry::factory()->create([
            'entity_data_table_id' => $table->id,
            'roll_min' => 16,
            'roll_max' => 16,
            'result_text' => 'Maximum roll',
            'sort_order' => 2,
        ]);

        $this->assertCount(2, $table->entries);
    }

    #[Test]
    public function data_table_has_table_type(): void
    {
        $race = Race::factory()->create();

        $table = EntityDataTable::factory()->forEntity(Race::class, $race->id)->create([
            'table_name' => 'Wild Magic Surge',
            'dice_type' => 'd100',
            'table_type' => DataTableType::RANDOM,
        ]);

        $this->assertEquals(DataTableType::RANDOM, $table->table_type);
        $this->assertEquals('random', $table->table_type->value);
    }
}
