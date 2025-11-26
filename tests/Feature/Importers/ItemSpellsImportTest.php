<?php

namespace Tests\Feature\Importers;

use App\Models\Item;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('importers')]
class ItemSpellsImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed required lookup data (only if not already seeded)
        // Sources are created via factory when needed by getSource()
        if (\App\Models\SpellSchool::count() === 0) {
            $this->seed(\Database\Seeders\SpellSchoolSeeder::class);
        }
        if (\App\Models\ItemType::count() === 0) {
            $this->seed(\Database\Seeders\ItemTypeSeeder::class);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_staff_of_healing_with_spell_charge_costs()
    {
        // Create the spells that the staff can cast
        $cureWounds = Spell::factory()->create(['name' => 'Cure Wounds', 'level' => 1]);
        $lesserRestoration = Spell::factory()->create(['name' => 'Lesser Restoration', 'level' => 2]);
        $massCureWounds = Spell::factory()->create(['name' => 'Mass Cure Wounds', 'level' => 5]);

        // Import XML for Staff of Healing
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Staff of Healing</name>
        <type>ST</type>
        <rarity>rare</rarity>
        <text>This staff has 10 charges. While holding it, you can use an action to expend 1 or more of its charges to cast one of the following spells from it, using your spell save DC and spellcasting ability modifier: cure wounds (1 charge per spell level, up to 4th), lesser restoration (2 charges), or mass cure wounds (5 charges).</text>
        <text>The staff regains 1d6 + 4 expended charges daily at dawn. If you expend the last charge, roll a d20. On a 1, the staff vanishes in a flash of light, lost forever.</text>
    </item>
</compendium>
XML;

        $xmlFile = tmpfile();
        fwrite($xmlFile, $xml);
        $xmlPath = stream_get_meta_data($xmlFile)['uri'];

        // Run importer
        $this->artisan('import:items', ['file' => $xmlPath])
            ->assertExitCode(0);

        // Assert item was created with charge data
        $staff = Item::where('name', 'Staff of Healing')->first();
        $this->assertNotNull($staff);
        $this->assertEquals(10, $staff->charges_max);
        $this->assertEquals('1d6+4', $staff->recharge_formula);
        $this->assertEquals('dawn', $staff->recharge_timing);

        // Assert spell associations were created with charge costs
        $staff->load('spells');
        $this->assertCount(3, $staff->spells);

        // Cure Wounds - variable cost
        $cureWoundsAssoc = $staff->spells->where('id', $cureWounds->id)->first();
        $this->assertNotNull($cureWoundsAssoc);
        $this->assertEquals(1, $cureWoundsAssoc->pivot->charges_cost_min);
        $this->assertEquals(4, $cureWoundsAssoc->pivot->charges_cost_max);
        $this->assertEquals('1 per spell level', $cureWoundsAssoc->pivot->charges_cost_formula);

        // Lesser Restoration - fixed cost
        $lesserRestorationAssoc = $staff->spells->where('id', $lesserRestoration->id)->first();
        $this->assertNotNull($lesserRestorationAssoc);
        $this->assertEquals(2, $lesserRestorationAssoc->pivot->charges_cost_min);
        $this->assertEquals(2, $lesserRestorationAssoc->pivot->charges_cost_max);
        $this->assertNull($lesserRestorationAssoc->pivot->charges_cost_formula);

        // Mass Cure Wounds - fixed cost
        $massCureWoundsAssoc = $staff->spells->where('id', $massCureWounds->id)->first();
        $this->assertNotNull($massCureWoundsAssoc);
        $this->assertEquals(5, $massCureWoundsAssoc->pivot->charges_cost_min);
        $this->assertEquals(5, $massCureWoundsAssoc->pivot->charges_cost_max);
        $this->assertNull($massCureWoundsAssoc->pivot->charges_cost_formula);

        fclose($xmlFile);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_items_without_spells_gracefully()
    {
        // Import Wand of Smiles (has charges but no spells)
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Wand of Smiles</name>
        <type>W</type>
        <rarity>common</rarity>
        <text>This wand has 3 charges. While holding it, you can use an action to expend 1 of its charges and target a humanoid you can see within 30 feet of you. The target must succeed on a DC 10 Charisma saving throw or be forced to smile for 1 minute.</text>
        <text>The wand regains all expended charges daily at dawn.</text>
    </item>
</compendium>
XML;

        $xmlFile = tmpfile();
        fwrite($xmlFile, $xml);
        $xmlPath = stream_get_meta_data($xmlFile)['uri'];

        $this->artisan('import:items', ['file' => $xmlPath])
            ->assertExitCode(0);

        $wand = Item::where('name', 'Wand of Smiles')->first();
        $this->assertNotNull($wand);
        $this->assertEquals(3, $wand->charges_max);

        // Should have no spell associations
        $wand->load('spells');
        $this->assertCount(0, $wand->spells);

        fclose($xmlFile);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_skips_spells_that_dont_exist_in_database()
    {
        // Create only one of the spells
        $cureWounds = Spell::factory()->create(['name' => 'Cure Wounds', 'level' => 1]);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Staff of Healing</name>
        <type>ST</type>
        <rarity>rare</rarity>
        <text>This staff has 10 charges. While holding it, you can use an action to expend 1 or more of its charges to cast one of the following spells from it: cure wounds (1 charge per spell level, up to 4th), lesser restoration (2 charges), or mass cure wounds (5 charges).</text>
        <text>The staff regains 1d6 + 4 expended charges daily at dawn.</text>
    </item>
</compendium>
XML;

        $xmlFile = tmpfile();
        fwrite($xmlFile, $xml);
        $xmlPath = stream_get_meta_data($xmlFile)['uri'];

        $this->artisan('import:items', ['file' => $xmlPath])
            ->assertExitCode(0);

        $staff = Item::where('name', 'Staff of Healing')->first();
        $staff->load('spells');

        // Should only have Cure Wounds (the one that exists)
        $this->assertCount(1, $staff->spells);
        $this->assertEquals($cureWounds->id, $staff->spells->first()->id);

        fclose($xmlFile);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_spell_charge_costs_on_reimport()
    {
        $cureWounds = Spell::factory()->create(['name' => 'Cure Wounds', 'level' => 1]);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Test Staff</name>
        <type>ST</type>
        <rarity>rare</rarity>
        <text>This staff has 5 charges. You can cast cure wounds (1 charge per spell level, up to 4th).</text>
        <text>The staff regains 1d3 expended charges daily at dawn.</text>
    </item>
</compendium>
XML;

        $xmlFile = tmpfile();
        fwrite($xmlFile, $xml);
        $xmlPath = stream_get_meta_data($xmlFile)['uri'];

        // First import
        $this->artisan('import:items', ['file' => $xmlPath]);

        $staff = Item::where('name', 'Test Staff')->first();
        $staff->load('spells');
        $this->assertEquals(1, $staff->spells->first()->pivot->charges_cost_min);
        $this->assertEquals(4, $staff->spells->first()->pivot->charges_cost_max);

        // Manually update the charge costs
        DB::table('entity_spells')
            ->where('reference_type', Item::class)
            ->where('reference_id', $staff->id)
            ->update([
                'charges_cost_min' => 99,
                'charges_cost_max' => 99,
            ]);

        // Re-import (should update)
        $this->artisan('import:items', ['file' => $xmlPath]);

        $staff->refresh();
        $staff->load('spells');

        // Should be back to original values
        $this->assertEquals(1, $staff->spells->first()->pivot->charges_cost_min);
        $this->assertEquals(4, $staff->spells->first()->pivot->charges_cost_max);

        fclose($xmlFile);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_case_insensitive_spell_name_matching()
    {
        // Create spell with specific casing
        Spell::factory()->create(['name' => 'Cure Wounds', 'level' => 1]);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Test Item</name>
        <type>W</type>
        <rarity>common</rarity>
        <text>You can cast cure wounds (1 charge).</text>
        <text>The item regains charges at dawn.</text>
    </item>
</compendium>
XML;

        $xmlFile = tmpfile();
        fwrite($xmlFile, $xml);
        $xmlPath = stream_get_meta_data($xmlFile)['uri'];

        $this->artisan('import:items', ['file' => $xmlPath])
            ->assertExitCode(0);

        $item = Item::where('name', 'Test Item')->first();
        $item->load('spells');

        // Should match despite different casing in XML
        $this->assertCount(1, $item->spells);
        $this->assertEquals('Cure Wounds', $item->spells->first()->name);

        fclose($xmlFile);
    }
}
