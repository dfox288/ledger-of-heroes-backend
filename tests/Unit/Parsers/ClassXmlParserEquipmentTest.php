<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\ClassXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class ClassXmlParserEquipmentTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private ClassXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ClassXmlParser;
    }

    #[Test]
    public function it_extracts_equipment_section_without_proficiencies()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <class>
        <name>Rogue</name>
        <hd>8</hd>
        <autolevel level="1">
            <feature>
                <name>Starting Rogue</name>
                <text>Hit Points at 1st level: 8 + Constitution

--- Proficiencies ---
Armor: light armor
Weapons: simple weapons
Skills: Choose 4 from Acrobatics

You begin play with the following equipment, in addition to any equipment provided by your background.

• (a) a rapier or (b) a shortsword
• (a) a shortbow and quiver of arrows (20) or (b) a shortsword
• Leather armor, two dagger, and thieves' tools

If you forgo this starting equipment, you start with 4d4 × 10 gp to buy your equipment.
</text>
            </feature>
        </autolevel>
    </class>
</compendium>
XML;

        $classes = $this->parser->parse($xml);
        $equipment = $classes[0]['equipment'];

        $this->assertNotEmpty($equipment['items']);

        // Should NOT include hit points or proficiency text
        foreach ($equipment['items'] as $item) {
            $this->assertStringNotContainsString('Hit Points', $item['description']);
            $this->assertStringNotContainsString('Proficiencies', $item['description']);
            $this->assertStringNotContainsString('Armor:', $item['description']);
            $this->assertStringNotContainsString('Skills:', $item['description']);
        }

        // Should include actual equipment
        $descriptions = array_column($equipment['items'], 'description');
        $allText = implode(' ', $descriptions);
        $this->assertStringContainsString('rapier', $allText);
        $this->assertStringContainsString('shortsword', $allText);
    }

    #[Test]
    public function it_groups_choice_options_together()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <class>
        <name>Fighter</name>
        <hd>10</hd>
        <autolevel level="1">
            <feature>
                <name>Starting Fighter</name>
                <text>You begin play with the following equipment:

• (a) chain mail or (b) leather armor, longbow, and 20 arrows
• (a) a martial weapon and a shield or (b) two martial weapons
• (a) a light crossbow and 20 bolts or (b) two handaxes
• (a) a dungeoneer's pack or (b) an explorer's pack
</text>
            </feature>
        </autolevel>
    </class>
</compendium>
XML;

        $classes = $this->parser->parse($xml);
        $items = $classes[0]['equipment']['items'];

        // Group items by choice_group
        $choiceGroups = [];
        foreach ($items as $item) {
            if ($item['is_choice'] && isset($item['choice_group'])) {
                $choiceGroups[$item['choice_group']][] = $item;
            }
        }

        // Should have 4 distinct choice groups
        $this->assertCount(4, $choiceGroups);

        // Choice 1: chain mail OR leather armor+longbow+arrows (2 options)
        $this->assertArrayHasKey('choice_1', $choiceGroups);
        $this->assertCount(2, $choiceGroups['choice_1']);

        // Choice 2: weapon+shield OR two weapons (2 options)
        $this->assertArrayHasKey('choice_2', $choiceGroups);
        $this->assertCount(2, $choiceGroups['choice_2']);

        // Verify choice_option numbering
        $this->assertEquals(1, $choiceGroups['choice_1'][0]['choice_option']);
        $this->assertEquals(2, $choiceGroups['choice_1'][1]['choice_option']);
    }

    #[Test]
    public function it_handles_three_way_choices()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <class>
        <name>Test</name>
        <hd>8</hd>
        <autolevel level="1">
            <feature>
                <name>Starting Test</name>
                <text>You begin play with the following equipment:

• (a) a burglar's pack, (b) a dungeoneer's pack, or (c) an explorer's pack
</text>
            </feature>
        </autolevel>
    </class>
</compendium>
XML;

        $classes = $this->parser->parse($xml);
        $items = $classes[0]['equipment']['items'];

        $choiceItems = array_filter($items, fn ($item) => $item['is_choice']);

        // Should have 3 options in one choice group
        $this->assertCount(3, $choiceItems);

        // All should be in the same choice group
        $choiceGroups = array_unique(array_column($choiceItems, 'choice_group'));
        $this->assertCount(1, $choiceGroups);

        // Should have options 1, 2, 3
        $options = array_column($choiceItems, 'choice_option');
        sort($options);
        $this->assertEquals([1, 2, 3], $options);
    }

    #[Test]
    public function it_parses_non_choice_items()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <class>
        <name>Test</name>
        <hd>8</hd>
        <autolevel level="1">
            <feature>
                <name>Starting Test</name>
                <text>You begin play with the following equipment:

• Leather armor, two dagger, and thieves' tools
</text>
            </feature>
        </autolevel>
    </class>
</compendium>
XML;

        $classes = $this->parser->parse($xml);
        $items = $classes[0]['equipment']['items'];

        // Should parse multiple items from one bullet
        $this->assertGreaterThanOrEqual(3, count($items));

        // None should be marked as choices
        foreach ($items as $item) {
            $this->assertFalse($item['is_choice']);
            $this->assertNull($item['choice_group']);
            $this->assertNull($item['choice_option']);
        }

        // Check for expected items
        $descriptions = array_column($items, 'description');
        $allText = strtolower(implode(' ', $descriptions));
        $this->assertStringContainsString('leather armor', $allText);
        $this->assertStringContainsString('dagger', $allText);
        $this->assertStringContainsString('thieves', $allText);
    }

    #[Test]
    public function it_extracts_quantity_from_word_numbers()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <class>
        <name>Test</name>
        <hd>8</hd>
        <autolevel level="1">
            <feature>
                <name>Starting Test</name>
                <text>You begin play with the following equipment:

• two dagger and four javelins
</text>
            </feature>
        </autolevel>
    </class>
</compendium>
XML;

        $classes = $this->parser->parse($xml);
        $items = $classes[0]['equipment']['items'];

        // Find dagger and javelin items
        $dagger = collect($items)->first(fn ($i) => str_contains(strtolower($i['description']), 'dagger'));
        $javelin = collect($items)->first(fn ($i) => str_contains(strtolower($i['description']), 'javelin'));

        $this->assertNotNull($dagger);
        $this->assertNotNull($javelin);
        $this->assertEquals(2, $dagger['quantity']);
        $this->assertEquals(4, $javelin['quantity']);
    }

    #[Test]
    public function it_parses_compound_choice_items_with_category_references()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <class>
        <name>Fighter</name>
        <hd>10</hd>
        <autolevel level="1">
            <feature>
                <name>Starting Fighter</name>
                <text>You begin play with the following equipment:

• (a) a martial weapon and a shield or (b) two martial weapons
</text>
            </feature>
        </autolevel>
    </class>
</compendium>
XML;

        $classes = $this->parser->parse($xml);
        $items = $classes[0]['equipment']['items'];

        // Option A: martial weapon + shield
        $optionA = collect($items)->first(fn ($i) => $i['choice_option'] === 1);
        $this->assertNotNull($optionA);
        $this->assertArrayHasKey('choice_items', $optionA);
        $this->assertCount(2, $optionA['choice_items']);

        // First item: martial weapon category
        $this->assertEquals('category', $optionA['choice_items'][0]['type']);
        $this->assertEquals('martial', $optionA['choice_items'][0]['value']);
        $this->assertEquals(1, $optionA['choice_items'][0]['quantity']);

        // Second item: shield (specific item)
        $this->assertEquals('item', $optionA['choice_items'][1]['type']);
        $this->assertEquals('shield', $optionA['choice_items'][1]['value']);

        // Option B: two martial weapons
        $optionB = collect($items)->first(fn ($i) => $i['choice_option'] === 2);
        $this->assertNotNull($optionB);
        $this->assertCount(1, $optionB['choice_items']);
        $this->assertEquals('category', $optionB['choice_items'][0]['type']);
        $this->assertEquals('martial', $optionB['choice_items'][0]['value']);
        $this->assertEquals(2, $optionB['choice_items'][0]['quantity']);
    }

    #[Test]
    public function it_parses_shortbow_with_quiver_of_arrows()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <class>
        <name>Rogue</name>
        <hd>8</hd>
        <autolevel level="1">
            <feature>
                <name>Starting Rogue</name>
                <text>You begin play with the following equipment:

• (a) a shortbow and quiver of arrows (20) or (b) a shortsword
</text>
            </feature>
        </autolevel>
    </class>
</compendium>
XML;

        $classes = $this->parser->parse($xml);
        $items = $classes[0]['equipment']['items'];

        // Option A: shortbow + arrows
        $optionA = collect($items)->first(fn ($i) => $i['choice_option'] === 1);
        $this->assertNotNull($optionA);
        $this->assertArrayHasKey('choice_items', $optionA);
        $this->assertCount(2, $optionA['choice_items']);

        // First: shortbow
        $this->assertEquals('item', $optionA['choice_items'][0]['type']);
        $this->assertEquals('shortbow', $optionA['choice_items'][0]['value']);
        $this->assertEquals(1, $optionA['choice_items'][0]['quantity']);

        // Second: arrows with quantity 20
        $this->assertEquals('item', $optionA['choice_items'][1]['type']);
        $this->assertEquals('arrows', $optionA['choice_items'][1]['value']);
        $this->assertEquals(20, $optionA['choice_items'][1]['quantity']);
    }

    #[Test]
    public function it_parses_simple_and_martial_melee_categories()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <class>
        <name>Test</name>
        <hd>8</hd>
        <autolevel level="1">
            <feature>
                <name>Starting Test</name>
                <text>You begin play with the following equipment:

• (a) any simple melee weapon or (b) any martial ranged weapon
</text>
            </feature>
        </autolevel>
    </class>
</compendium>
XML;

        $classes = $this->parser->parse($xml);
        $items = $classes[0]['equipment']['items'];

        // Option A: simple melee
        $optionA = collect($items)->first(fn ($i) => $i['choice_option'] === 1);
        $this->assertEquals('category', $optionA['choice_items'][0]['type']);
        $this->assertEquals('simple_melee', $optionA['choice_items'][0]['value']);

        // Option B: martial ranged
        $optionB = collect($items)->first(fn ($i) => $i['choice_option'] === 2);
        $this->assertEquals('category', $optionB['choice_items'][0]['type']);
        $this->assertEquals('martial_ranged', $optionB['choice_items'][0]['value']);
    }

    #[Test]
    public function it_includes_choice_items_for_non_choice_equipment()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <class>
        <name>Test</name>
        <hd>8</hd>
        <autolevel level="1">
            <feature>
                <name>Starting Test</name>
                <text>You begin play with the following equipment:

• Leather armor, two dagger, and thieves' tools
</text>
            </feature>
        </autolevel>
    </class>
</compendium>
XML;

        $classes = $this->parser->parse($xml);
        $items = $classes[0]['equipment']['items'];

        // Each non-choice item should have choice_items array
        foreach ($items as $item) {
            $this->assertArrayHasKey('choice_items', $item);
            $this->assertNotEmpty($item['choice_items']);
        }

        // Find dagger item
        $dagger = collect($items)->first(fn ($i) => str_contains(strtolower($i['description']), 'dagger'));
        $this->assertNotNull($dagger);
        $this->assertEquals('item', $dagger['choice_items'][0]['type']);
        $this->assertEquals(2, $dagger['choice_items'][0]['quantity']);
    }

    #[Test]
    public function it_parses_musical_instrument_as_category()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <class>
        <name>Bard</name>
        <hd>8</hd>
        <autolevel level="1">
            <feature>
                <name>Starting Bard</name>
                <text>You begin play with the following equipment:

• (a) a lute or (b) any other musical instrument
</text>
            </feature>
        </autolevel>
    </class>
</compendium>
XML;

        $classes = $this->parser->parse($xml);
        $items = $classes[0]['equipment']['items'];

        // Option B: any other musical instrument
        $optionB = collect($items)->first(fn ($i) => $i['choice_option'] === 2);
        $this->assertNotNull($optionB);
        $this->assertArrayHasKey('choice_items', $optionB);
        $this->assertCount(1, $optionB['choice_items']);

        // Should be a category reference, not an item
        $this->assertEquals('category', $optionB['choice_items'][0]['type']);
        $this->assertEquals('musical_instrument', $optionB['choice_items'][0]['value']);
        $this->assertEquals(1, $optionB['choice_items'][0]['quantity']);
    }

    #[Test]
    public function it_parses_various_musical_instrument_phrases()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <class>
        <name>Test</name>
        <hd>8</hd>
        <autolevel level="1">
            <feature>
                <name>Starting Test</name>
                <text>You begin play with the following equipment:

• (a) a musical instrument or (b) any musical instrument of your choice or (c) one musical instrument
</text>
            </feature>
        </autolevel>
    </class>
</compendium>
XML;

        $classes = $this->parser->parse($xml);
        $items = $classes[0]['equipment']['items'];

        // All three options should be musical instrument categories
        foreach ([1, 2, 3] as $optionNum) {
            $option = collect($items)->first(fn ($i) => $i['choice_option'] === $optionNum);
            $this->assertNotNull($option, "Option {$optionNum} should exist");
            $this->assertEquals('category', $option['choice_items'][0]['type'], "Option {$optionNum} should be category");
            $this->assertEquals('musical_instrument', $option['choice_items'][0]['value'], "Option {$optionNum} should be musical_instrument");
        }
    }
}
