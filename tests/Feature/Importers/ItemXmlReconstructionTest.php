<?php

namespace Tests\Feature\Importers;

use App\Models\Item;
use App\Services\Importers\ItemImporter;
use App\Services\Parsers\ItemXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemXmlReconstructionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private ItemXmlParser $parser;

    private ItemImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ItemXmlParser;
        $this->importer = new ItemImporter;
    }

    #[Test]
    public function it_reconstructs_simple_melee_weapon()
    {
        $originalXml = <<<'XML'
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
        $originalXml = <<<'XML'
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
        $originalXml = <<<'XML'
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
        $this->assertEquals(150, $item->range_normal);
        $this->assertEquals(600, $item->range_long);

        // Verify properties
        $item->load('properties');
        $this->assertCount(4, $item->properties);
        $propertyCodes = $item->properties->pluck('code')->sort()->values()->toArray();
        $this->assertEquals(['2H', 'A', 'H', 'M'], $propertyCodes);
    }

    #[Test]
    public function it_reconstructs_magic_item_with_attunement()
    {
        $originalXml = <<<'XML'
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
        $originalXml = <<<'XML'
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
        $originalXml = <<<'XML'
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

    #[Test]
    public function it_reconstructs_magic_item_with_flag()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>+1 Arrow</name>
    <detail>uncommon</detail>
    <type>A</type>
    <magic>YES</magic>
    <weight>0.05</weight>
    <text>You have a +1 bonus to attack and damage rolls made with this magic ammunition.

Source: Dungeon Master's Guide (2014) p. 150</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $this->assertCount(1, $items);

        $item = $this->importer->import($items[0]);

        // Verify magic flag
        $this->assertTrue($item->is_magic);
        $this->assertEquals('uncommon', $item->rarity);
    }

    #[Test]
    public function it_reconstructs_item_with_modifiers()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Arrow +1</name>
    <detail>uncommon</detail>
    <type>A</type>
    <magic>YES</magic>
    <weight>0.05</weight>
    <text>You have a +1 bonus to attack and damage rolls.

Source: Dungeon Master's Guide (2014) p. 150</text>
    <modifier category="bonus">ranged attack +1</modifier>
    <modifier category="bonus">ranged damage +1</modifier>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify modifiers (now parsed as structured data)
        $item->load('modifiers');
        $this->assertCount(2, $item->modifiers);

        // Debug: see what categories we got
        $categories = $item->modifiers->pluck('modifier_category')->toArray();
        // dump($categories); // Uncomment for debugging

        // Parser now extracts structured data from "ranged attacks +1" and "ranged damage +1"
        // Both should match "ranged attack" and "ranged damage" patterns
        $attackModifier = $item->modifiers->first(fn ($m) => str_contains($m->modifier_category, 'attack'));
        $damageModifier = $item->modifiers->first(fn ($m) => str_contains($m->modifier_category, 'damage'));

        $this->assertNotNull($attackModifier, 'Attack modifier not found. Available categories: '.implode(', ', $categories));
        $this->assertEquals('1', $attackModifier->value); // Integer value stored as string

        $this->assertNotNull($damageModifier, 'Damage modifier not found. Available categories: '.implode(', ', $categories));
        $this->assertEquals('1', $damageModifier->value);
    }

    #[Test]
    public function it_reconstructs_item_with_abilities()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Potion of Healing</name>
    <detail>common</detail>
    <type>P</type>
    <magic>YES</magic>
    <text>You regain hit points when you drink this potion.

Source: Dungeon Master's Guide (2014) p. 187</text>
    <roll>2d4+2</roll>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify abilities
        $item->load('abilities');
        $this->assertCount(1, $item->abilities);

        $ability = $item->abilities->first();
        $this->assertEquals('roll', $ability->ability_type);
        $this->assertEquals('2d4+2', $ability->roll_formula);
        $this->assertStringContainsString('2d4+2', $ability->name);
    }

    #[Test]
    public function it_parses_roll_descriptions_from_attribute()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Test Item</name>
    <type>W</type>
    <text>Test item description.

Source: Test Source p. 1</text>
    <roll description="Attack Bonus">1d20+8</roll>
    <roll description="Bludgeoning Damage">2d6</roll>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify roll descriptions
        $item->load('abilities');
        $this->assertCount(2, $item->abilities);

        $abilities = $item->abilities->sortBy('sort_order')->values();
        $this->assertEquals('Attack Bonus', $abilities[0]->name);
        $this->assertEquals('1d20+8', $abilities[0]->roll_formula);

        $this->assertEquals('Bludgeoning Damage', $abilities[1]->name);
        $this->assertEquals('2d6', $abilities[1]->roll_formula);
    }

    #[Test]
    public function it_parses_simple_table_from_description()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Test Item</name>
    <type>W</type>
    <text>Item description.

Test Table:
Option | Effect
1 | Effect A
2 | Effect B

Source: Test p. 1</text>
  </item>
</compendium>
XML;

        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        $item->load('randomTables.entries');
        $this->assertCount(1, $item->randomTables);

        $table = $item->randomTables->first();
        $this->assertEquals('Test Table', $table->table_name);
        $this->assertCount(2, $table->entries);

        $this->assertEquals(1, $table->entries[0]->roll_min);
        $this->assertEquals(1, $table->entries[0]->roll_max);
        $this->assertEquals('Effect A', $table->entries[0]->result_text);
        $this->assertEquals(0, $table->entries[0]->sort_order);
    }

    #[Test]
    public function it_parses_roll_ranges_in_tables()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Wild Magic Item</name>
    <type>W</type>
    <text>This item causes wild magic.

Wild Magic Effects:
d10 | Effect
1-2 | Fireball
3-5 | Teleport
6 | Heal

Source: Test p. 1</text>
  </item>
</compendium>
XML;

        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        $item->load('randomTables.entries');
        $this->assertCount(1, $item->randomTables);

        $table = $item->randomTables->first();
        $entries = $table->entries->sortBy('sort_order')->values();

        // First entry: 1-2
        $this->assertEquals(1, $entries[0]->roll_min);
        $this->assertEquals(2, $entries[0]->roll_max);
        $this->assertEquals('Fireball', $entries[0]->result_text);

        // Second entry: 3-5
        $this->assertEquals(3, $entries[1]->roll_min);
        $this->assertEquals(5, $entries[1]->roll_max);
        $this->assertEquals('Teleport', $entries[1]->result_text);

        // Third entry: 6
        $this->assertEquals(6, $entries[2]->roll_min);
        $this->assertEquals(6, $entries[2]->roll_max);
        $this->assertEquals('Heal', $entries[2]->result_text);
    }

    #[Test]
    public function it_parses_multi_column_tables()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Test Apparatus</name>
    <type>W</type>
    <text>A complex device.

Lever Controls:
Lever | Up | Down
1 | Extend legs | Retract legs
2 | Open window | Close window

Source: Test p. 1</text>
  </item>
</compendium>
XML;

        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        $item->load('randomTables.entries');
        $this->assertCount(1, $item->randomTables);

        $table = $item->randomTables->first();
        $this->assertEquals('Lever Controls', $table->table_name);
        $this->assertCount(2, $table->entries);

        $entries = $table->entries->sortBy('sort_order')->values();
        $this->assertEquals('Extend legs | Retract legs', $entries[0]->result_text);
        $this->assertEquals('Open window | Close window', $entries[1]->result_text);
    }

    #[Test]
    public function it_parses_unusual_dice_types()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Test Deck</name>
    <type>W</type>
    <text>A deck of cards.

Deck Cards:
1d22 | Card | Effect
1 | Ace | Win
2 | King | Lose

Source: Test p. 1</text>
  </item>
</compendium>
XML;

        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        $item->load('randomTables');
        $this->assertCount(1, $item->randomTables);

        $table = $item->randomTables->first();
        $this->assertEquals('1d22', $table->dice_type);
    }

    #[Test]
    public function it_reconstructs_strength_requirement_as_prerequisite()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Plate Armor</name>
    <type>HA</type>
    <weight>65</weight>
    <value>1500.0</value>
    <ac>18</ac>
    <strength>15</strength>
    <stealth>YES</stealth>
    <text>Heavy armor that provides excellent protection.

Proficiency: heavy armor

Source: Player's Handbook (2014) p. 145</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify backward compatibility: strength_requirement column still populated
        $this->assertEquals(15, $item->strength_requirement);

        // Verify new prerequisite system
        $item->load(['prerequisites.prerequisite']);
        $this->assertCount(1, $item->prerequisites);

        $prerequisite = $item->prerequisites->first();
        $this->assertEquals(\App\Models\Item::class, $prerequisite->reference_type);
        $this->assertEquals($item->id, $prerequisite->reference_id);
        $this->assertEquals(\App\Models\AbilityScore::class, $prerequisite->prerequisite_type);
        $this->assertEquals(15, $prerequisite->minimum_value);
        $this->assertEquals(1, $prerequisite->group_id);
        $this->assertNull($prerequisite->description);

        // Verify the prerequisite points to the Strength ability score
        $this->assertNotNull($prerequisite->prerequisite);
        $this->assertEquals('STR', $prerequisite->prerequisite->code);
        $this->assertEquals('Strength', $prerequisite->prerequisite->name);
    }

    #[Test]
    public function it_handles_items_without_strength_requirement()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Leather Armor</name>
    <type>LA</type>
    <weight>10</weight>
    <value>10.0</value>
    <ac>11</ac>
    <text>Light armor with no special requirements.

Proficiency: light armor

Source: Player's Handbook (2014) p. 144</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify no strength requirement
        $this->assertNull($item->strength_requirement);

        // Verify no prerequisites created
        $item->load('prerequisites');
        $this->assertCount(0, $item->prerequisites);
    }

    #[Test]
    public function it_creates_base_ac_modifier_for_regular_shield()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Shield</name>
    <type>S</type>
    <weight>6</weight>
    <value>10.0</value>
    <ac>2</ac>
    <text>A shield is made from wood or metal and is carried in one hand.

Proficiency: shields

Source: Player's Handbook (2014) p. 144</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify dual storage pattern: armor_class column populated
        $this->assertEquals(2, $item->armor_class);

        // Verify shield type
        $item->load('itemType');
        $this->assertEquals('S', $item->itemType->code);

        // Verify base AC modifier created (dual storage)
        $item->load('modifiers');
        $this->assertCount(1, $item->modifiers, 'Shield should have exactly one AC modifier');

        $acModifier = $item->modifiers->first();
        $this->assertEquals('ac_bonus', $acModifier->modifier_category);
        $this->assertEquals('2', $acModifier->value);
    }

    #[Test]
    public function it_creates_both_base_and_magic_modifiers_for_magic_shield()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Shield +1</name>
    <detail>uncommon</detail>
    <type>S</type>
    <weight>6</weight>
    <ac>2</ac>
    <magic>YES</magic>
    <text>While holding this shield, you have a +2 bonus to AC. This bonus is in addition to the shield's normal bonus to AC. A shield +1 grants a +3 bonus to AC total (+2 base shield + 1 magic).

Proficiency: shields

Source: Dungeon Master's Guide (2014) p. 200</text>
    <modifier category="bonus">ac +1</modifier>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify dual storage pattern
        $this->assertEquals(2, $item->armor_class);
        $this->assertTrue($item->is_magic);
        $this->assertEquals('uncommon', $item->rarity);

        // Verify TWO AC modifiers: base shield (2) + magic enchantment (1)
        $item->load('modifiers');
        $this->assertCount(2, $item->modifiers, 'Shield +1 should have two AC modifiers: base (2) + magic (1)');

        // Verify base shield bonus (ac_bonus category)
        $baseModifier = $item->modifiers->where('modifier_category', 'ac_bonus')->first();
        $this->assertNotNull($baseModifier, 'Should have base shield modifier (ac_bonus)');
        $this->assertEquals('2', $baseModifier->value);

        // Verify magic enchantment (ac_magic category)
        $magicModifier = $item->modifiers->where('modifier_category', 'ac_magic')->first();
        $this->assertNotNull($magicModifier, 'Should have magic enchantment modifier (ac_magic)');
        $this->assertEquals('1', $magicModifier->value);

        // Calculate total AC bonus
        $totalAcBonus = $item->modifiers->sum(fn ($m) => (int) $m->value);
        $this->assertEquals(3, $totalAcBonus, 'Total AC bonus should be +3 (2 base + 1 magic)');
    }

    #[Test]
    public function it_creates_both_base_and_magic_modifiers_for_shield_plus_3()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Shield +3</name>
    <detail>very rare</detail>
    <type>S</type>
    <weight>6</weight>
    <ac>2</ac>
    <magic>YES</magic>
    <text>While holding this shield, you have a +5 bonus to AC (+2 base shield + 3 magic).

Proficiency: shields

Source: Dungeon Master's Guide (2014) p. 200</text>
    <modifier category="bonus">ac +3</modifier>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify dual storage
        $this->assertEquals(2, $item->armor_class);
        $this->assertTrue($item->is_magic);
        $this->assertEquals('very rare', $item->rarity);

        // Verify TWO AC modifiers: base (2) + magic (3)
        $item->load('modifiers');
        $this->assertCount(2, $item->modifiers);

        // Verify base shield bonus
        $baseModifier = $item->modifiers->where('modifier_category', 'ac_bonus')->first();
        $this->assertNotNull($baseModifier, 'Should have base shield modifier (ac_bonus)');
        $this->assertEquals('2', $baseModifier->value);

        // Verify magic enchantment
        $magicModifier = $item->modifiers->where('modifier_category', 'ac_magic')->first();
        $this->assertNotNull($magicModifier, 'Should have magic enchantment modifier (ac_magic)');
        $this->assertEquals('3', $magicModifier->value);

        // Verify total AC bonus
        $totalAcBonus = $item->modifiers->sum(fn ($m) => (int) $m->value);
        $this->assertEquals(5, $totalAcBonus, 'Total AC bonus should be +5 (2 base + 3 magic)');
    }

    #[Test]
    public function it_does_not_create_ac_modifier_for_non_shield_items()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Plate Armor</name>
    <type>HA</type>
    <weight>65</weight>
    <value>1500.0</value>
    <ac>18</ac>
    <strength>15</strength>
    <stealth>YES</stealth>
    <text>Heavy armor providing maximum protection.

Proficiency: heavy armor

Source: Player's Handbook (2014) p. 145</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify armor_class column populated (base AC, not a bonus)
        $this->assertEquals(18, $item->armor_class);

        // Verify item type is Heavy Armor, NOT Shield
        $item->load('itemType');
        $this->assertEquals('HA', $item->itemType->code);

        // Verify armor gets ac_base modifier (NOT ac_bonus like shields)
        $item->load('modifiers');
        $this->assertGreaterThanOrEqual(1, $item->modifiers->count(), 'Armor should have at least ac_base modifier');

        // Find the AC modifier
        $acModifier = $item->modifiers->firstWhere('modifier_category', 'ac_base');
        $this->assertNotNull($acModifier, 'Should have ac_base modifier');
        $this->assertEquals('ac_base', $acModifier->modifier_category, 'Armor should use ac_base, not ac_bonus');
        $this->assertEquals('18', $acModifier->value);
        $this->assertEquals('dex_modifier: none', $acModifier->condition, 'Heavy armor does not allow DEX modifier');
    }

    #[Test]
    public function it_does_not_create_ac_modifier_for_shield_without_ac_value()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Broken Shield</name>
    <type>S</type>
    <weight>6</weight>
    <text>This shield is broken and provides no AC bonus.

Source: Test Source p. 1</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify shield type
        $item->load('itemType');
        $this->assertEquals('S', $item->itemType->code);

        // Verify no AC value
        $this->assertNull($item->armor_class);

        // Verify no AC modifiers created
        $item->load('modifiers');
        $this->assertCount(0, $item->modifiers, 'Shield without AC value should not have modifiers');
    }

    #[Test]
    public function it_prevents_duplicate_ac_modifiers_on_reimport()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Shield</name>
    <type>S</type>
    <weight>6</weight>
    <value>10.0</value>
    <ac>2</ac>
    <text>A standard shield.

Proficiency: shields

Source: Player's Handbook (2014) p. 144</text>
  </item>
</compendium>
XML;

        // First import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);
        $item->load('modifiers');
        $this->assertCount(1, $item->modifiers, 'First import should create 1 AC modifier');

        // Re-import the same item (simulates re-running import command)
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);
        $item->load('modifiers');
        $this->assertCount(1, $item->modifiers, 'Re-import should not create duplicate AC modifier');

        // Verify the modifier is still correct
        $acModifier = $item->modifiers->first();
        $this->assertEquals('ac_bonus', $acModifier->modifier_category);
        $this->assertEquals('2', $acModifier->value);
    }

    #[Test]
    public function it_handles_magic_shield_with_numeric_modifiers()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Spellguard Shield</name>
    <detail>very rare</detail>
    <type>S</type>
    <weight>6</weight>
    <ac>2</ac>
    <magic>YES</magic>
    <text>While holding this shield, you have advantage on saving throws against spells and other magical effects, and spell attacks have disadvantage against you. The shield provides +2 bonus to AC and +2 bonus to spell saves.

Proficiency: shields

Source: Dungeon Master's Guide (2014) p. 201</text>
    <modifier category="bonus">ac +2</modifier>
    <modifier category="bonus">saving throw +2</modifier>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify shield properties
        $this->assertEquals(2, $item->armor_class);
        $this->assertTrue($item->is_magic);

        // Verify modifiers: Now with distinct categories, we should have THREE modifiers:
        // 1. Base shield AC bonus (ac_bonus, 2) - from importShieldAcModifier()
        // 2. Magic AC bonus (ac_magic, 2) - from XML <modifier>
        // 3. Saving throw bonus (saving_throw, 2) - from XML <modifier>
        $item->load('modifiers');
        $this->assertCount(3, $item->modifiers, 'Should have base AC bonus + magic AC bonus + saving throw modifier');

        // Verify base shield AC bonus
        $baseAcModifier = $item->modifiers->where('modifier_category', 'ac_bonus')->first();
        $this->assertNotNull($baseAcModifier, 'Should have base shield AC bonus (ac_bonus)');
        $this->assertEquals('2', $baseAcModifier->value);

        // Verify magic AC bonus
        $magicAcModifier = $item->modifiers->where('modifier_category', 'ac_magic')->first();
        $this->assertNotNull($magicAcModifier, 'Should have magic AC bonus (ac_magic)');
        $this->assertEquals('2', $magicAcModifier->value);

        // Verify saving throw modifier exists
        $savingThrowModifier = $item->modifiers->where('modifier_category', 'saving_throw')->first();
        $this->assertNotNull($savingThrowModifier, 'Should have saving throw modifier');
        $this->assertEquals('2', $savingThrowModifier->value);

        // Total AC bonus should be +4 (2 base + 2 magic)
        $totalAcBonus = $item->modifiers->whereIn('modifier_category', ['ac_bonus', 'ac_magic'])->sum(fn ($m) => (int) $m->value);
        $this->assertEquals(4, $totalAcBonus, 'Total AC bonus should be +4 (2 base + 2 magic)');
    }

    #[Test]
    public function it_creates_ac_base_modifier_for_light_armor()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Leather Armor</name>
    <type>LA</type>
    <weight>10</weight>
    <value>10.0</value>
    <ac>11</ac>
    <text>Light armor that allows full DEX modifier to AC.

Proficiency: light armor

Source: Player's Handbook (2014) p. 144</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify armor properties
        $this->assertEquals(11, $item->armor_class);
        $item->load('itemType');
        $this->assertEquals('LA', $item->itemType->code);

        // Verify base AC modifier created with DEX metadata
        $item->load('modifiers');
        $this->assertCount(1, $item->modifiers, 'Light armor should have one ac_base modifier');

        $acModifier = $item->modifiers->first();
        $this->assertEquals('ac_base', $acModifier->modifier_category);
        $this->assertEquals('11', $acModifier->value);
        $this->assertEquals('dex_modifier: full', $acModifier->condition);
    }

    #[Test]
    public function it_creates_ac_base_modifier_for_medium_armor()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Half Plate Armor</name>
    <type>MA</type>
    <weight>40</weight>
    <value>750.0</value>
    <ac>15</ac>
    <stealth>YES</stealth>
    <text>Medium armor that allows DEX modifier (max +2) to AC.

Proficiency: medium armor

Source: Player's Handbook (2014) p. 144</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify armor properties
        $this->assertEquals(15, $item->armor_class);
        $item->load('itemType');
        $this->assertEquals('MA', $item->itemType->code);

        // Verify base AC modifier with DEX cap
        $item->load('modifiers');
        $this->assertGreaterThanOrEqual(1, $item->modifiers->count(), 'Should have at least 1 modifier (AC base, potentially also stealth)');

        // Find the AC modifier
        $acModifier = $item->modifiers->firstWhere('modifier_category', 'ac_base');
        $this->assertNotNull($acModifier, 'Should have ac_base modifier');
        $this->assertEquals('ac_base', $acModifier->modifier_category);
        $this->assertEquals('15', $acModifier->value);
        $this->assertEquals('dex_modifier: max_2', $acModifier->condition);
    }

    #[Test]
    public function it_creates_ac_base_modifier_for_heavy_armor()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Plate Armor</name>
    <type>HA</type>
    <weight>65</weight>
    <value>1500.0</value>
    <ac>18</ac>
    <strength>15</strength>
    <stealth>YES</stealth>
    <text>Heavy armor providing maximum protection with no DEX bonus.

Proficiency: heavy armor

Source: Player's Handbook (2014) p. 145</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify armor properties
        $this->assertEquals(18, $item->armor_class);
        $item->load('itemType');
        $this->assertEquals('HA', $item->itemType->code);

        // Verify base AC modifier with no DEX
        $item->load('modifiers');
        $this->assertGreaterThanOrEqual(1, $item->modifiers->count(), 'Should have at least 1 modifier (AC base, potentially also stealth)');

        // Find the AC modifier
        $acModifier = $item->modifiers->firstWhere('modifier_category', 'ac_base');
        $this->assertNotNull($acModifier, 'Should have ac_base modifier');
        $this->assertEquals('ac_base', $acModifier->modifier_category);
        $this->assertEquals('18', $acModifier->value);
        $this->assertEquals('dex_modifier: none', $acModifier->condition);
    }

    #[Test]
    public function it_creates_both_ac_base_and_ac_magic_for_magic_armor()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Leather Armor +1</name>
    <detail>rare</detail>
    <type>LA</type>
    <magic>YES</magic>
    <weight>10</weight>
    <ac>11</ac>
    <text>You have a +1 bonus to AC while wearing this magic armor.

Proficiency: light armor

Source: Dungeon Master's Guide (2014) p. 152</text>
    <modifier category="bonus">ac +1</modifier>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify armor properties
        $this->assertEquals(11, $item->armor_class);
        $this->assertTrue($item->is_magic);
        $this->assertEquals('rare', $item->rarity);

        // Verify TWO modifiers: base AC (11) + magic enchantment (1)
        $item->load('modifiers');
        $this->assertCount(2, $item->modifiers, 'Magic armor should have ac_base + ac_magic');

        // Verify base armor AC
        $baseModifier = $item->modifiers->where('modifier_category', 'ac_base')->first();
        $this->assertNotNull($baseModifier, 'Should have base armor AC (ac_base)');
        $this->assertEquals('11', $baseModifier->value);
        $this->assertEquals('dex_modifier: full', $baseModifier->condition);

        // Verify magic enchantment
        $magicModifier = $item->modifiers->where('modifier_category', 'ac_magic')->first();
        $this->assertNotNull($magicModifier, 'Should have magic enchantment (ac_magic)');
        $this->assertEquals('1', $magicModifier->value);
    }

    #[Test]
    public function it_does_not_create_ac_base_for_non_armor_items()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Longsword</name>
    <type>M</type>
    <weight>3</weight>
    <value>15.0</value>
    <dmg1>1d8</dmg1>
    <dmg2>1d10</dmg2>
    <dmgType>S</dmgType>
    <property>V</property>
    <text>A versatile martial weapon.

Proficiency: martial, longsword

Source: Player's Handbook (2014) p. 149</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify item type is NOT armor
        $item->load('itemType');
        $this->assertEquals('M', $item->itemType->code);

        // Verify NO AC modifiers created for weapons
        $item->load('modifiers');
        $this->assertCount(0, $item->modifiers, 'Non-armor items should not have AC modifiers');
    }

    #[Test]
    public function it_imports_stealth_disadvantage_as_skill_modifier()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Chain Mail</name>
    <type>HA</type>
    <weight>55</weight>
    <value>75.0</value>
    <ac>16</ac>
    <stealth>YES</stealth>
    <strength>13</strength>
    <text>Made of interlocking metal rings, chain mail includes a layer of quilted fabric worn underneath the mail to prevent chafing and to cushion the impact of blows. The suit includes gauntlets.

The wearer has disadvantage on Stealth (Dexterity) checks.

If the wearer has a Strength score lower than 13, their speed is reduced by 10 feet.

Source: Player's Handbook (2014) p. 145</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify stealth_disadvantage column is set (legacy column, still maintained)
        $this->assertTrue($item->stealth_disadvantage, 'stealth_disadvantage column should be true');

        // Verify skill modifier with disadvantage is created
        $item->load('modifiers.skill', 'modifiers.abilityScore');

        // Filter to skill modifiers only
        $skillModifiers = $item->modifiers->where('modifier_category', 'skill');
        $this->assertGreaterThanOrEqual(1, $skillModifiers->count(), 'Should have at least 1 skill modifier (stealth disadvantage)');

        // Find the stealth disadvantage modifier
        $stealthMod = $skillModifiers->first(fn ($m) => $m->skill?->name === 'Stealth');
        $this->assertNotNull($stealthMod, 'Should have a Stealth skill modifier');
        $this->assertEquals('skill', $stealthMod->modifier_category);
        $this->assertEquals('disadvantage', $stealthMod->value, 'Should have disadvantage value');
        $this->assertEquals('DEX', $stealthMod->abilityScore->code, 'Should be linked to Dexterity');
        $this->assertEquals('Stealth', $stealthMod->skill->name);
    }

    #[Test]
    public function it_does_not_create_skill_modifiers_for_items_without_stealth()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Breastplate</name>
    <type>MA</type>
    <weight>20</weight>
    <value>400.0</value>
    <ac>14</ac>
    <text>This armor consists of a fitted metal chest piece worn with supple leather. Although it leaves the legs and arms relatively unprotected, this armor provides good protection for the wearer's vital organs while leaving the wearer relatively unencumbered.

Source: Player's Handbook (2014) p. 144</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify stealth_disadvantage column is false
        $this->assertFalse($item->stealth_disadvantage, 'stealth_disadvantage column should be false');

        // Verify NO skill modifiers for stealth
        $item->load('modifiers.skill');
        $skillModifiers = $item->modifiers->where('modifier_category', 'skill');
        $stealthMods = $skillModifiers->filter(fn ($m) => $m->skill?->name === 'Stealth');
        $this->assertCount(0, $stealthMods, 'Should have no stealth skill modifiers');
    }
}
