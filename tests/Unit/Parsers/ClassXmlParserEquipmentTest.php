<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\ClassXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
}
