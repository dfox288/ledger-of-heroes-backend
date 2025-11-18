<?php

namespace Tests\Feature\Importers;

use App\Services\Importers\ItemImporter;
use App\Services\Parsers\ItemXmlParser;
use App\Models\Item;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class ItemXmlReconstructionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private ItemXmlParser $parser;
    private ItemImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ItemXmlParser();
        $this->importer = new ItemImporter();
    }

    #[Test]
    public function it_reconstructs_simple_melee_weapon()
    {
        $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Battleaxe</name>
    <type>M</type>
    <weight>4</weight>
    <value>10.0</value>
    <property>V,M</property>
    <dmg1>1d8</dmg1>
    <dmg2>1d10</dmg2>
    <dmgType>S</dmgType>
    <text>A versatile martial weapon.

Proficiency: martial, battleaxe

Source: Player's Handbook (2014) p. 149</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $this->assertCount(1, $items);

        $item = $this->importer->import($items[0]);

        // Verify basic attributes
        $this->assertEquals('Battleaxe', $item->name);
        $this->assertEquals('battleaxe', $item->slug);
        $this->assertEquals('common', $item->rarity);
        $this->assertFalse($item->requires_attunement);
        $this->assertEquals(1000, $item->cost_cp); // 10.0 GP = 1000 CP
        $this->assertEquals(4.0, $item->weight);
        $this->assertEquals('1d8', $item->damage_dice);
        $this->assertEquals('1d10', $item->versatile_damage);
        $this->assertFalse($item->stealth_disadvantage);

        // Verify relationships
        $item->load(['itemType', 'damageType', 'properties', 'sources.source', 'proficiencies']);

        $this->assertEquals('M', $item->itemType->code);
        $this->assertEquals('S', $item->damageType->code);

        // Verify properties
        $this->assertCount(2, $item->properties);
        $propertyCodes = $item->properties->pluck('code')->sort()->values()->toArray();
        $this->assertEquals(['M', 'V'], $propertyCodes);

        // Verify source
        $this->assertCount(1, $item->sources);
        $this->assertEquals('Player\'s Handbook', $item->sources[0]->source->name);
        $this->assertEquals('149', $item->sources[0]->pages);

        // Verify proficiencies
        $this->assertCount(2, $item->proficiencies);
        $profNames = $item->proficiencies->pluck('proficiency_name')->sort()->values()->toArray();
        $this->assertEquals(['battleaxe', 'martial'], $profNames);
    }

    #[Test]
    public function it_reconstructs_armor_with_requirements()
    {
        $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Chain Mail</name>
    <type>HA</type>
    <weight>55</weight>
    <value>75.0</value>
    <ac>16</ac>
    <strength>13</strength>
    <stealth>YES</stealth>
    <text>Heavy armor that provides excellent protection but restricts movement.

Proficiency: heavy armor

Source: Player's Handbook (2014) p. 145</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify armor-specific attributes
        $this->assertEquals('Chain Mail', $item->name);
        $this->assertEquals(16, $item->armor_class);
        $this->assertEquals(13, $item->strength_requirement);
        $this->assertTrue($item->stealth_disadvantage);
        $this->assertEquals(7500, $item->cost_cp); // 75.0 GP = 7500 CP
        $this->assertEquals(55.0, $item->weight);

        // Verify item type
        $item->load('itemType');
        $this->assertEquals('HA', $item->itemType->code);

        // Verify proficiency
        $item->load('proficiencies');
        $this->assertCount(1, $item->proficiencies);
        $this->assertEquals('heavy armor', $item->proficiencies[0]->proficiency_name);
        $this->assertEquals('armor', $item->proficiencies[0]->proficiency_type);
    }

    #[Test]
    public function it_reconstructs_ranged_weapon_with_range()
    {
        $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Longbow</name>
    <type>R</type>
    <weight>2</weight>
    <value>50.0</value>
    <property>A,H,2H,M</property>
    <dmg1>1d8</dmg1>
    <dmgType>P</dmgType>
    <range>150/600</range>
    <text>A powerful ranged weapon requiring ammunition.

Proficiency: martial, longbow

Source: Player's Handbook (2014) p. 149</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify ranged weapon attributes
        $this->assertEquals('Longbow', $item->name);
        $this->assertEquals('1d8', $item->damage_dice);
        $this->assertEquals('150/600', $item->weapon_range);

        // Verify properties
        $item->load('properties');
        $this->assertCount(4, $item->properties);
        $propertyCodes = $item->properties->pluck('code')->sort()->values()->toArray();
        $this->assertEquals(['2H', 'A', 'H', 'M'], $propertyCodes);
    }

    #[Test]
    public function it_reconstructs_magic_item_with_attunement()
    {
        $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>+1 Longsword</name>
    <detail>uncommon</detail>
    <type>M</type>
    <weight>3</weight>
    <property>V,M</property>
    <dmg1>1d8</dmg1>
    <dmg2>1d10</dmg2>
    <dmgType>S</dmgType>
    <text>You have a +1 bonus to attack and damage rolls made with this magic weapon. Requires attunement.

Proficiency: martial

Source: Dungeon Master's Guide (2014) p. 213</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify magic item attributes
        $this->assertEquals('+1 Longsword', $item->name);
        $this->assertEquals('uncommon', $item->rarity);
        $this->assertTrue($item->requires_attunement);

        // Verify source
        $item->load('sources.source');
        $this->assertEquals('Dungeon Master\'s Guide', $item->sources[0]->source->name);
        $this->assertEquals('213', $item->sources[0]->pages);
    }

    #[Test]
    public function it_reconstructs_item_without_cost()
    {
        $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Torch</name>
    <type>G</type>
    <weight>1</weight>
    <text>A simple torch that provides light.

Source: Player's Handbook (2014) p. 153</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify item without cost/value
        $this->assertEquals('Torch', $item->name);
        $this->assertNull($item->cost_cp);
        $this->assertEquals(1.0, $item->weight);

        // Verify item type
        $item->load('itemType');
        $this->assertEquals('G', $item->itemType->code);
    }

    #[Test]
    public function it_parses_attunement_from_detail_field()
    {
        $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Cloak of Protection</name>
    <detail>uncommon (requires attunement)</detail>
    <type>W</type>
    <magic>YES</magic>
    <text>You gain a +1 bonus to AC and saving throws while you wear this cloak.

Source: Dungeon Master's Guide (2014) p. 159</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify attunement parsed from detail field
        $this->assertTrue($item->requires_attunement);
        $this->assertEquals('uncommon', $item->rarity);
        $this->assertTrue($item->is_magic);
    }
}
