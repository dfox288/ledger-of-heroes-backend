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

    #[Test]
    public function it_imports_data_tables_from_multiple_traits()
    {
        $race = Race::factory()->create();

        $trait1 = CharacterTrait::factory()->forEntity(Race::class, $race->id)->create([
            'description' => <<<'TEXT'
Table 1:
d2 | Result
1 | A
2 | B
TEXT,
        ]);

        $trait2 = CharacterTrait::factory()->forEntity(Race::class, $race->id)->create([
            'description' => <<<'TEXT'
Table 2:
d2 | Result
1 | X
2 | Y
TEXT,
        ]);

        $createdTraits = [$trait1, $trait2];
        $traitsData = [
            ['description' => $trait1->description],
            ['description' => $trait2->description],
        ];

        $this->importDataTablesFromTraits($createdTraits, $traitsData);

        // Should create one table for each trait
        $table1 = EntityDataTable::where('reference_id', $trait1->id)->first();
        $table2 = EntityDataTable::where('reference_id', $trait2->id)->first();

        $this->assertNotNull($table1);
        $this->assertNotNull($table2);

        $this->assertEquals('Table 1', $table1->table_name);
        $this->assertEquals('Table 2', $table2->table_name);
    }

    #[Test]
    public function it_skips_traits_without_description_in_data_array()
    {
        $race = Race::factory()->create();

        $trait1 = CharacterTrait::factory()->forEntity(Race::class, $race->id)->create([
            'description' => <<<'TEXT'
Table:
d2 | Result
1 | A
2 | B
TEXT,
        ]);

        $trait2 = CharacterTrait::factory()->forEntity(Race::class, $race->id)->create([
            'description' => 'No table here',
        ]);

        $createdTraits = [$trait1, $trait2];
        $traitsData = [
            ['description' => $trait1->description],
            [], // Missing description key
        ];

        $this->importDataTablesFromTraits($createdTraits, $traitsData);

        // Should only create table for trait1
        $this->assertCount(1, EntityDataTable::all());
        $this->assertNotNull(EntityDataTable::where('reference_id', $trait1->id)->first());
    }

    #[Test]
    public function it_does_not_clear_existing_tables_when_using_trait_method()
    {
        $race = Race::factory()->create();
        $trait = CharacterTrait::factory()->forEntity(Race::class, $race->id)->create([
            'description' => <<<'TEXT'
New Table:
d2 | Result
1 | A
2 | B
TEXT,
        ]);

        // Create an existing table
        $existingTable = EntityDataTable::create([
            'reference_type' => CharacterTrait::class,
            'reference_id' => $trait->id,
            'table_name' => 'Existing Table',
            'dice_type' => 'd6',
            'table_type' => 'random',
        ]);

        // Import should not clear existing (clearExisting: false)
        $this->importTraitTables($trait, $trait->description);

        // Both tables should exist
        $tables = EntityDataTable::where('reference_id', $trait->id)->get();
        $this->assertCount(2, $tables);
    }

    #[Test]
    public function it_handles_description_with_no_detectable_tables()
    {
        $race = Race::factory()->create();
        $trait = CharacterTrait::factory()->forEntity(Race::class, $race->id)->create([
            'description' => 'Just some plain text with no pipe-delimited table.',
        ]);

        $this->importTraitTables($trait, $trait->description);

        $this->assertDatabaseCount('entity_data_tables', 0);
    }

    #[Test]
    public function it_handles_empty_description()
    {
        $race = Race::factory()->create();
        $trait = CharacterTrait::factory()->forEntity(Race::class, $race->id)->create([
            'description' => '',
        ]);

        $this->importTraitTables($trait, $trait->description);

        $this->assertDatabaseCount('entity_data_tables', 0);
    }

    #[Test]
    public function it_creates_table_with_correct_polymorphic_reference()
    {
        $race = Race::factory()->create();
        $description = <<<'TEXT'
Test Table:
d2 | Result
1 | One
2 | Two
TEXT;
        $trait = CharacterTrait::factory()->forEntity(Race::class, $race->id)->create([
            'description' => $description,
        ]);

        $this->importTraitTables($trait, $trait->description);

        $table = EntityDataTable::first();
        $this->assertEquals(CharacterTrait::class, $table->reference_type);
        $this->assertEquals($trait->id, $table->reference_id);
    }

    #[Test]
    public function it_preserves_sort_order_of_entries()
    {
        $race = Race::factory()->create();
        $description = <<<'TEXT'
Order Test:
d6 | Result
1 | First
2 | Second
3 | Third
4 | Fourth
5 | Fifth
6 | Sixth
TEXT;
        $trait = CharacterTrait::factory()->forEntity(Race::class, $race->id)->create([
            'description' => $description,
        ]);

        $this->importTraitTables($trait, $trait->description);

        $table = EntityDataTable::first();
        $entries = $table->entries()->orderBy('sort_order')->get();

        $this->assertCount(6, $entries);
        $this->assertEquals(0, $entries[0]->sort_order);
        $this->assertEquals('First', $entries[0]->result_text);
        $this->assertEquals(5, $entries[5]->sort_order);
        $this->assertEquals('Sixth', $entries[5]->result_text);
    }
}
