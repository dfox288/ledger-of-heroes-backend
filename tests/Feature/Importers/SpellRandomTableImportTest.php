<?php

namespace Tests\Feature\Importers;

use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use App\Models\Spell;
use App\Services\Importers\SpellImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpellRandomTableImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Only seed if not already seeded (prevents duplicate key errors)
        if (\App\Models\SpellSchool::count() === 0) {
            $this->seed(\Database\Seeders\SpellSchoolSeeder::class);
        }
    }

    #[Test]
    public function it_imports_spell_with_random_table(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
  <spell>
    <name>Prismatic Spray</name>
    <level>7</level>
    <school>EV</school>
    <time>1 action</time>
    <range>Self (60-foot cone)</range>
    <components>V, S</components>
    <duration>Instantaneous</duration>
    <classes>Sorcerer, Wizard</classes>
    <text>Eight multicolored rays of light flash from your hand. Each ray is a different color and has a different power and purpose. Each creature in a 60-foot cone must make a Dexterity saving throw. For each target, roll a d8 to determine which color ray affects it.

d8 | Power
1 | Red: The target takes 10d6 fire damage on a failed save, or half as much damage on a successful one.
2 | Orange: The target takes 10d6 acid damage on a failed save, or half as much damage on a successful one.
3 | Yellow: The target takes 10d6 lightning damage on a failed save, or half as much damage on a successful one.
4 | Green: The target takes 10d6 poison damage on a failed save, or half as much damage on a successful one.
5 | Blue: The target takes 10d6 cold damage on a failed save, or half as much damage on a successful one.
6 | Indigo: On a failed save, the target is restrained.
7 | Violet: On a failed save, the target is blinded.
8 | Special: The target is struck by two rays. Roll twice more, rerolling any 8.</text>
  </spell>
</compendium>
XML;

        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'spell_test_');
        file_put_contents($tempFile, $xml);

        try {
            // Import
            $importer = new SpellImporter;
            $count = $importer->importFromFile($tempFile);

            // Verify spell was created
            $this->assertEquals(1, $count);
            $spell = Spell::where('name', 'Prismatic Spray')->first();
            $this->assertNotNull($spell);

            // Verify random table was created
            $tables = $spell->randomTables;
            $this->assertCount(1, $tables);

            $table = $tables->first();
            $this->assertEquals('Power', $table->table_name);
            $this->assertEquals('d8', $table->dice_type);

            // Verify entries
            $entries = $table->entries()->orderBy('sort_order')->get();
            $this->assertCount(8, $entries);

            // Check first entry
            $firstEntry = $entries[0];
            $this->assertEquals(1, $firstEntry->roll_min);
            $this->assertEquals(1, $firstEntry->roll_max);
            $this->assertStringContainsString('Red', $firstEntry->result_text);
            $this->assertStringContainsString('10d6 fire damage', $firstEntry->result_text);

            // Check last entry
            $lastEntry = $entries[7];
            $this->assertEquals(8, $lastEntry->roll_min);
            $this->assertEquals(8, $lastEntry->roll_max);
            $this->assertStringContainsString('Special', $lastEntry->result_text);
            $this->assertStringContainsString('two rays', $lastEntry->result_text);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function it_handles_spell_without_random_tables(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
  <spell>
    <name>Fireball</name>
    <level>3</level>
    <school>EV</school>
    <time>1 action</time>
    <range>150 feet</range>
    <components>V, S, M (a tiny ball of bat guano and sulfur)</components>
    <duration>Instantaneous</duration>
    <classes>Sorcerer, Wizard</classes>
    <text>A bright streak flashes from your pointing finger to a point you choose within range and then blossoms with a low roar into an explosion of flame.</text>
  </spell>
</compendium>
XML;

        $tempFile = tempnam(sys_get_temp_dir(), 'spell_test_');
        file_put_contents($tempFile, $xml);

        try {
            $importer = new SpellImporter;
            $count = $importer->importFromFile($tempFile);

            $this->assertEquals(1, $count);
            $spell = Spell::where('name', 'Fireball')->first();
            $this->assertNotNull($spell);

            // Should have no random tables
            $this->assertCount(0, $spell->randomTables);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function it_replaces_random_tables_on_reimport(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
  <spell>
    <name>Confusion</name>
    <level>4</level>
    <school>EN</school>
    <time>1 action</time>
    <range>90 feet</range>
    <components>V, S, M (three nut shells)</components>
    <duration>Concentration, up to 1 minute</duration>
    <classes>Bard, Druid, Sorcerer, Wizard</classes>
    <text>Roll a d10 to determine what it does on its turn.

d10 | Behavior
1 | The creature uses all its movement to move in a random direction.
2-6 | The creature doesn't move or take actions this turn.
7-8 | The creature uses its action to make a melee attack.
9-10 | The creature can act and move normally.</text>
  </spell>
</compendium>
XML;

        $tempFile = tempnam(sys_get_temp_dir(), 'spell_test_');
        file_put_contents($tempFile, $xml);

        try {
            // First import
            $importer = new SpellImporter;
            $importer->importFromFile($tempFile);

            $spell = Spell::where('name', 'Confusion')->first();
            $this->assertCount(1, $spell->randomTables);
            $table = $spell->randomTables->first();
            $this->assertCount(4, $table->entries);

            // Store old IDs
            $oldTableId = $table->id;
            $oldEntryIds = $table->entries->pluck('id')->toArray();

            // Re-import same spell
            $importer->importFromFile($tempFile);

            // Refresh spell
            $spell->refresh();

            // Should still have 1 table
            $this->assertCount(1, $spell->randomTables);
            $newTable = $spell->randomTables->first();
            $this->assertCount(4, $newTable->entries);

            // But it should be new records (not updated)
            $this->assertNotEquals($oldTableId, $newTable->id);
            $newEntryIds = $newTable->entries->pluck('id')->toArray();
            $this->assertEmpty(array_intersect($oldEntryIds, $newEntryIds));

            // Old table and entries should be deleted
            $this->assertNull(RandomTable::find($oldTableId));
            foreach ($oldEntryIds as $oldId) {
                $this->assertNull(RandomTableEntry::find($oldId));
            }
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function it_handles_multiple_tables_in_single_spell(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
  <spell>
    <name>Wild Magic Surge</name>
    <level>1</level>
    <school>EV</school>
    <time>1 action</time>
    <range>Self</range>
    <components>V, S</components>
    <duration>Instantaneous</duration>
    <classes>Sorcerer</classes>
    <text>Roll on the Wild Magic table.

d20 | Effect Type
1-5 | Minor effect
6-10 | Moderate effect

d6 | Damage Type
1 | Fire
2 | Cold
3 | Lightning</text>
  </spell>
</compendium>
XML;

        $tempFile = tempnam(sys_get_temp_dir(), 'spell_test_');
        file_put_contents($tempFile, $xml);

        try {
            $importer = new SpellImporter;
            $importer->importFromFile($tempFile);

            $spell = Spell::where('name', 'Wild Magic Surge')->first();
            $this->assertCount(2, $spell->randomTables);

            // First table
            $table1 = $spell->randomTables[0];
            $this->assertEquals('Effect Type', $table1->table_name);
            $this->assertEquals('d20', $table1->dice_type);
            $this->assertCount(2, $table1->entries);

            // Second table
            $table2 = $spell->randomTables[1];
            $this->assertEquals('Damage Type', $table2->table_name);
            $this->assertEquals('d6', $table2->dice_type);
            $this->assertCount(3, $table2->entries);
        } finally {
            @unlink($tempFile);
        }
    }
}
