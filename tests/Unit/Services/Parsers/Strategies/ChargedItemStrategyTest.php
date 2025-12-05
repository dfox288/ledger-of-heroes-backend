<?php

namespace Tests\Unit\Services\Parsers\Strategies;

use App\Services\Parsers\Strategies\ChargedItemStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use SimpleXMLElement;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]

class ChargedItemStrategyTest extends TestCase
{
    use RefreshDatabase;

    private ChargedItemStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new ChargedItemStrategy;
    }

    #[Test]
    public function it_applies_to_magic_staves()
    {
        $baseData = ['type_code' => 'ST', 'is_magic' => true];
        $xml = new SimpleXMLElement('<item><name>Staff of Power</name></item>');

        $this->assertTrue($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_applies_to_magic_wands()
    {
        $baseData = ['type_code' => 'WD', 'is_magic' => true];
        $xml = new SimpleXMLElement('<item><name>Wand of Fireballs</name></item>');

        $this->assertTrue($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_applies_to_magic_rods()
    {
        $baseData = ['type_code' => 'RD', 'is_magic' => true];
        $xml = new SimpleXMLElement('<item><name>Rod of Absorption</name></item>');

        $this->assertTrue($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_applies_to_magic_items_with_charges()
    {
        $baseData = ['type_code' => 'W', 'is_magic' => true, 'charges_max' => 10];
        $xml = new SimpleXMLElement('<item><name>Ring of Spell Storing</name></item>');

        $this->assertTrue($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_applies_to_items_mentioning_spell_charges()
    {
        $baseData = [
            'type_code' => 'W',
            'is_magic' => false,
            'description' => 'While holding it, you can use an action to expend 1 or more of its charges to cast cure wounds (1 charge).',
        ];
        $xml = new SimpleXMLElement('<item><name>Test Item</name></item>');

        $this->assertTrue($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_does_not_apply_to_non_magic_staff()
    {
        $baseData = ['type_code' => 'ST', 'is_magic' => false];
        $xml = new SimpleXMLElement('<item><name>Quarterstaff</name></item>');

        $this->assertFalse($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_does_not_apply_to_magic_items_without_charges_or_spell_references()
    {
        $baseData = ['type_code' => 'W', 'is_magic' => true, 'description' => 'This is a magic ring.'];
        $xml = new SimpleXMLElement('<item><name>Ring of Protection</name></item>');

        $this->assertFalse($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_extracts_single_spell_with_fixed_charge_cost()
    {
        $baseData = [
            'name' => 'Staff of Healing',
            'description' => 'You can cast cure wounds (1 charge).',
        ];
        $xml = new SimpleXMLElement('<item><name>Staff of Healing</name></item>');

        $this->strategy->reset();
        $result = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertArrayHasKey('spell_references', $result);
        $this->assertCount(1, $result['spell_references']);
        $this->assertEquals('Cure Wounds', $result['spell_references'][0]['name']);
        $this->assertEquals(1, $result['spell_references'][0]['charges_cost_min']);
        $this->assertEquals(1, $result['spell_references'][0]['charges_cost_max']);
        $this->assertNull($result['spell_references'][0]['charges_cost_formula']);
        // spell_id may be null if database not seeded - that's okay for parser tests
        $this->assertArrayHasKey('spell_id', $result['spell_references'][0]);
    }

    #[Test]
    public function it_extracts_spell_with_variable_charge_cost()
    {
        $baseData = [
            'name' => 'Staff of Healing',
            'description' => 'You can expend charges to cast cure wounds (1 charge per spell level, up to 4th).',
        ];
        $xml = new SimpleXMLElement('<item><name>Staff of Healing</name></item>');

        $this->strategy->reset();
        $result = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertArrayHasKey('spell_references', $result);
        $this->assertCount(1, $result['spell_references']);
        $this->assertEquals('Cure Wounds', $result['spell_references'][0]['name']);
        $this->assertEquals(1, $result['spell_references'][0]['charges_cost_min']);
        $this->assertEquals(4, $result['spell_references'][0]['charges_cost_max']);
        $this->assertEquals('1 per spell level', $result['spell_references'][0]['charges_cost_formula']);
    }

    #[Test]
    public function it_extracts_multiple_spells_from_description()
    {
        $baseData = [
            'name' => 'Staff of Fire',
            'description' => 'While holding it, you can use an action to cast burning hands (1 charge) or fireball (3 charges).',
        ];
        $xml = new SimpleXMLElement('<item><name>Staff of Fire</name></item>');

        $this->strategy->reset();
        $result = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertArrayHasKey('spell_references', $result);
        $this->assertCount(2, $result['spell_references']);
        $this->assertEquals('Burning Hands', $result['spell_references'][0]['name']);
        $this->assertEquals(1, $result['spell_references'][0]['charges_cost_min']);
        $this->assertEquals('Fireball', $result['spell_references'][1]['name']);
        $this->assertEquals(3, $result['spell_references'][1]['charges_cost_min']);
    }

    #[Test]
    public function it_extracts_spells_with_following_spells_pattern()
    {
        $baseData = [
            'name' => 'Wand of Paralysis',
            'description' => 'While holding it, you can cast the following spells: hold person (2 charges) or hold monster (5 charges).',
        ];
        $xml = new SimpleXMLElement('<item><name>Wand of Paralysis</name></item>');

        $this->strategy->reset();
        $result = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertArrayHasKey('spell_references', $result);
        $this->assertCount(2, $result['spell_references']);
        $this->assertEquals('Hold Person', $result['spell_references'][0]['name']);
        $this->assertEquals(2, $result['spell_references'][0]['charges_cost_min']);
        $this->assertEquals('Hold Monster', $result['spell_references'][1]['name']);
        $this->assertEquals(5, $result['spell_references'][1]['charges_cost_min']);
    }

    #[Test]
    public function it_handles_spell_names_with_apostrophes()
    {
        $baseData = [
            'name' => 'Test Wand',
            'description' => 'You can cast mordenkainen\'s sword (7 charges).',
        ];
        $xml = new SimpleXMLElement('<item><name>Test Wand</name></item>');

        $this->strategy->reset();
        $result = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertArrayHasKey('spell_references', $result);
        $this->assertCount(1, $result['spell_references']);
        $this->assertEquals('Mordenkainen\'s Sword', $result['spell_references'][0]['name']);
        $this->assertEquals(7, $result['spell_references'][0]['charges_cost_min']);
    }

    #[Test]
    public function it_handles_items_without_spell_references()
    {
        $baseData = [
            'name' => 'Ring of Protection',
            'description' => 'You gain a +1 bonus to AC and saving throws while wearing this ring.',
        ];
        $xml = new SimpleXMLElement('<item><name>Ring of Protection</name></item>');

        $this->strategy->reset();
        $result = $this->strategy->enhanceRelationships($baseData, $xml);

        // If spell_references exists, it should be empty
        if (isset($result['spell_references'])) {
            $this->assertEmpty($result['spell_references']);
        } else {
            $this->assertArrayNotHasKey('spell_references', $result);
        }
    }

    #[Test]
    public function it_handles_empty_description()
    {
        $baseData = [
            'name' => 'Test Item',
            'description' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test Item</name></item>');

        $this->strategy->reset();
        $result = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertEquals([], $result);
    }

    #[Test]
    public function it_handles_missing_description()
    {
        $baseData = ['name' => 'Test Item'];
        $xml = new SimpleXMLElement('<item><name>Test Item</name></item>');

        $this->strategy->reset();
        $result = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertEquals([], $result);
    }

    #[Test]
    public function it_tracks_spell_references_found_metric()
    {
        $baseData = [
            'name' => 'Staff of Fire',
            'description' => 'You can cast burning hands (1 charge) or fireball (3 charges).',
        ];
        $xml = new SimpleXMLElement('<item><name>Staff of Fire</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceRelationships($baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertArrayHasKey('spell_references_found', $metadata['metrics']);
        $this->assertEquals(2, $metadata['metrics']['spell_references_found']);
    }

    #[Test]
    public function it_normalizes_spell_names_to_title_case()
    {
        $baseData = [
            'name' => 'Wand of Magic Missiles',
            'description' => 'You can cast magic missile (1 charge).',
        ];
        $xml = new SimpleXMLElement('<item><name>Wand of Magic Missiles</name></item>');

        $this->strategy->reset();
        $result = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertEquals('Magic Missile', $result['spell_references'][0]['name']);
    }

    #[Test]
    public function it_handles_charge_with_singular_charge_keyword()
    {
        $baseData = [
            'name' => 'Wand of Magic Detection',
            'description' => 'You can cast detect magic (1 charge).',
        ];
        $xml = new SimpleXMLElement('<item><name>Wand of Magic Detection</name></item>');

        $this->strategy->reset();
        $result = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertArrayHasKey('spell_references', $result);
        $this->assertCount(1, $result['spell_references']);
        $this->assertEquals('Detect Magic', $result['spell_references'][0]['name']);
        $this->assertEquals(1, $result['spell_references'][0]['charges_cost_min']);
    }

    #[Test]
    public function it_handles_charge_with_plural_charges_keyword()
    {
        $baseData = [
            'name' => 'Staff of Power',
            'description' => 'You can cast cone of cold (5 charges).',
        ];
        $xml = new SimpleXMLElement('<item><name>Staff of Power</name></item>');

        $this->strategy->reset();
        $result = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertArrayHasKey('spell_references', $result);
        $this->assertCount(1, $result['spell_references']);
        $this->assertEquals('Cone Of Cold', $result['spell_references'][0]['name']);
        $this->assertEquals(5, $result['spell_references'][0]['charges_cost_min']);
    }

    #[Test]
    public function it_handles_complex_staff_with_multiple_variable_cost_spells()
    {
        $baseData = [
            'name' => 'Staff of Healing',
            'description' => 'The staff has the following spells: cure wounds (1 charge per spell level, up to 4th), or lesser restoration (2 charges), or mass cure wounds (5 charges).',
        ];
        $xml = new SimpleXMLElement('<item><name>Staff of Healing</name></item>');

        $this->strategy->reset();
        $result = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertArrayHasKey('spell_references', $result);
        $this->assertCount(3, $result['spell_references']);

        // Cure Wounds with variable cost
        $this->assertEquals('Cure Wounds', $result['spell_references'][0]['name']);
        $this->assertEquals(1, $result['spell_references'][0]['charges_cost_min']);
        $this->assertEquals(4, $result['spell_references'][0]['charges_cost_max']);
        $this->assertEquals('1 per spell level', $result['spell_references'][0]['charges_cost_formula']);

        // Lesser Restoration with fixed cost
        $this->assertEquals('Lesser Restoration', $result['spell_references'][1]['name']);
        $this->assertEquals(2, $result['spell_references'][1]['charges_cost_min']);
        $this->assertEquals(2, $result['spell_references'][1]['charges_cost_max']);

        // Mass Cure Wounds with fixed cost
        $this->assertEquals('Mass Cure Wounds', $result['spell_references'][2]['name']);
        $this->assertEquals(5, $result['spell_references'][2]['charges_cost_min']);
        $this->assertEquals(5, $result['spell_references'][2]['charges_cost_max']);
    }
}
