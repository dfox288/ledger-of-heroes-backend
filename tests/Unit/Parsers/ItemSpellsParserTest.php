<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\Concerns\ParsesItemSpells;
use Tests\TestCase;

class ItemSpellsParserTest extends TestCase
{
    use ParsesItemSpells;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_fixed_charge_cost()
    {
        $text = 'lesser restoration (2 charges)';
        $result = $this->parseSpellChargeCost($text);

        $this->assertSame(2, $result['min']);
        $this->assertSame(2, $result['max']);
        $this->assertNull($result['formula']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_variable_charge_cost_per_spell_level()
    {
        $text = 'cure wounds (1 charge per spell level, up to 4th)';
        $result = $this->parseSpellChargeCost($text);

        $this->assertSame(1, $result['min']);
        $this->assertSame(4, $result['max']);
        $this->assertSame('1 per spell level', $result['formula']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_free_spells_with_no_charges()
    {
        $text = 'detect magic (no charges)';
        $result = $this->parseSpellChargeCost($text);

        $this->assertSame(0, $result['min']);
        $this->assertSame(0, $result['max']);
        $this->assertNull($result['formula']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_expends_syntax()
    {
        $text = 'expend 3 charges to cast fireball';
        $result = $this->parseSpellChargeCost($text);

        $this->assertSame(3, $result['min']);
        $this->assertSame(3, $result['max']);
        $this->assertNull($result['formula']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_text_without_charge_costs()
    {
        $text = 'This is just regular text without costs';
        $result = $this->parseSpellChargeCost($text);

        $this->assertNull($result['min']);
        $this->assertNull($result['max']);
        $this->assertNull($result['formula']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_multiple_spells_from_staff_of_healing_description()
    {
        // This matches the ACTUAL XML format with a period before "or mass cure wounds"
        $description = <<<'TEXT'
This staff has 10 charges. While holding it, you can use an action to expend 1 or more of its charges to cast one of the following spells from it, using your spell save DC and spellcasting ability modifier: cure wounds (1 charge per spell level, up to 4th), lesser restoration (2 charges). or mass cure wounds (5 charges).
The staff regains 1d6 + 4 expended charges daily at dawn.
TEXT;

        $spells = $this->parseItemSpells($description);

        $this->assertCount(3, $spells);

        // Cure Wounds
        $this->assertSame('cure wounds', $spells[0]['spell_name']);
        $this->assertSame(1, $spells[0]['charges_cost_min']);
        $this->assertSame(4, $spells[0]['charges_cost_max']);
        $this->assertSame('1 per spell level', $spells[0]['charges_cost_formula']);

        // Lesser Restoration
        $this->assertSame('lesser restoration', $spells[1]['spell_name']);
        $this->assertSame(2, $spells[1]['charges_cost_min']);
        $this->assertSame(2, $spells[1]['charges_cost_max']);
        $this->assertNull($spells[1]['charges_cost_formula']);

        // Mass Cure Wounds
        $this->assertSame('mass cure wounds', $spells[2]['spell_name']);
        $this->assertSame(5, $spells[2]['charges_cost_min']);
        $this->assertSame(5, $spells[2]['charges_cost_max']);
        $this->assertNull($spells[2]['charges_cost_formula']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_spells_from_staff_of_fire_description()
    {
        $description = <<<'TEXT'
The staff has 10 charges. While holding it, you can use an action to expend 1 or more of its charges to cast one of the following spells from it, using your spell save DC: burning hands (1 charge), fireball (3 charges), or wall of fire (4 charges).
TEXT;

        $spells = $this->parseItemSpells($description);

        $this->assertCount(3, $spells);

        $this->assertSame('burning hands', $spells[0]['spell_name']);
        $this->assertSame(1, $spells[0]['charges_cost_min']);

        $this->assertSame('fireball', $spells[1]['spell_name']);
        $this->assertSame(3, $spells[1]['charges_cost_min']);

        $this->assertSame('wall of fire', $spells[2]['spell_name']);
        $this->assertSame(4, $spells[2]['charges_cost_min']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_array_for_items_without_spells()
    {
        $description = 'This wand has 3 charges. It can force humanoids to smile.';
        $spells = $this->parseItemSpells($description);

        $this->assertEmpty($spells);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_single_charge_syntax()
    {
        $text = 'detect thoughts (1 charge)';
        $result = $this->parseSpellChargeCost($text);

        $this->assertSame(1, $result['min']);
        $this->assertSame(1, $result['max']);
        $this->assertNull($result['formula']);
    }
}
