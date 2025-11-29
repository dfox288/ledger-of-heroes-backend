<?php

namespace Tests\Unit\Importers\Concerns;

use App\Models\CharacterTrait;
use App\Models\EntityDataTable;
use App\Models\Race;
use App\Services\Importers\Concerns\ImportsDataTables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class ImportsDataTablesTest extends TestCase
{
    use ImportsDataTables, RefreshDatabase;

    #[Test]
    public function it_imports_data_table_from_trait_description()
    {
        $race = Race::factory()->create();
        $description = <<<'TEXT'
Choose your personality:
d3 | Trait
1 | Brave
2 | Cautious
3 | Curious
TEXT;
        $trait = CharacterTrait::factory()->forEntity(Race::class, $race->id)->create([
            'description' => $description,
        ]);

        $this->importTraitTables($trait, $trait->description);

        $this->assertDatabaseHas('entity_data_tables', [
            'reference_type' => CharacterTrait::class,
            'reference_id' => $trait->id,
        ]);
    }

    #[Test]
    public function it_creates_table_entries_with_correct_roll_ranges()
    {
        $race = Race::factory()->create();
        $description = <<<'TEXT'
Roll Table:
d6 | Result
1 | First
2-3 | Second
4-6 | Third
TEXT;
        $trait = CharacterTrait::factory()->forEntity(Race::class, $race->id)->create([
            'description' => $description,
        ]);

        $this->importTraitTables($trait, $trait->description);

        $table = EntityDataTable::first();
        $entries = $table->entries()->orderBy('sort_order')->get();

        $this->assertCount(3, $entries);
        $this->assertEquals(1, $entries[0]->roll_min);
        $this->assertEquals(1, $entries[0]->roll_max);
        $this->assertEquals(2, $entries[1]->roll_min);
        $this->assertEquals(3, $entries[1]->roll_max);
    }

    #[Test]
    public function it_detects_dice_type_from_table()
    {
        $race = Race::factory()->create();
        $description = <<<'TEXT'
Dice Table:
d6 | Result
1 | One
2 | Two
3 | Three
4 | Four
5 | Five
6 | Six
TEXT;
        $trait = CharacterTrait::factory()->forEntity(Race::class, $race->id)->create([
            'description' => $description,
        ]);

        $this->importTraitTables($trait, $trait->description);

        $this->assertDatabaseHas('entity_data_tables', [
            'dice_type' => 'd6',
        ]);
    }

    #[Test]
    public function it_handles_multiple_tables_in_one_description()
    {
        $race = Race::factory()->create();
        $description = <<<'TEXT'
First Table:
d2 | Choice
1 | A
2 | B

Second Table:
d2 | Option
1 | X
2 | Y
TEXT;
        $trait = CharacterTrait::factory()->forEntity(Race::class, $race->id)->create([
            'description' => $description,
        ]);

        $this->importTraitTables($trait, $trait->description);

        $tables = EntityDataTable::where('reference_id', $trait->id)->get();
        $this->assertCount(2, $tables);
    }

    #[Test]
    public function it_skips_traits_without_tables()
    {
        $race = Race::factory()->create();
        $trait = CharacterTrait::factory()->forEntity(Race::class, $race->id)->create([
            'description' => 'Just plain text with no table.',
        ]);

        $this->importTraitTables($trait, $trait->description);

        $this->assertDatabaseCount('entity_data_tables', 0);
    }

    #[Test]
    public function it_imports_level_progression_tables()
    {
        $race = Race::factory()->create();
        $description = <<<'TEXT'
Your martial arts training allows you to use special dice:

Martial Arts:
Level | Martial Arts
1st | 1d4
5th | 1d6
11th | 1d8
17th | 1d10

Source: PHB
TEXT;
        $trait = CharacterTrait::factory()->forEntity(Race::class, $race->id)->create([
            'description' => $description,
        ]);

        $this->importTraitTables($trait, $trait->description);

        // Should create a table
        $table = EntityDataTable::where('reference_id', $trait->id)->first();
        $this->assertNotNull($table);
        $this->assertEquals('Martial Arts', $table->table_name);
        $this->assertEquals('progression', $table->table_type->value);

        // Should have 4 entries with level values
        $entries = $table->entries()->orderBy('sort_order')->get();
        $this->assertCount(4, $entries);

        $this->assertEquals(1, $entries[0]->level);
        $this->assertEquals('1d4', $entries[0]->result_text);

        $this->assertEquals(5, $entries[1]->level);
        $this->assertEquals('1d6', $entries[1]->result_text);

        $this->assertEquals(11, $entries[2]->level);
        $this->assertEquals('1d8', $entries[2]->result_text);

        $this->assertEquals(17, $entries[3]->level);
        $this->assertEquals('1d10', $entries[3]->result_text);
    }
}
