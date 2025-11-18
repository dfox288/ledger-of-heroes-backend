<?php

namespace Tests\Feature\Importers;

use App\Models\Spell;
use App\Services\Importers\SpellImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpellXmlReconstructionTest extends TestCase
{
    use RefreshDatabase;

    private SpellImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new SpellImporter();
    }

    #[Test]
    public function it_reconstructs_simple_cantrip()
    {
        // Original XML for Acid Splash cantrip
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Acid Splash</name>
    <level>0</level>
    <school>C</school>
    <time>1 action</time>
    <range>60 feet</range>
    <components>V, S</components>
    <duration>Instantaneous</duration>
    <classes>Sorcerer, Wizard</classes>
    <text>You hurl a bubble of acid. Choose one creature you can see within range, or choose two creatures you can see within range that are within 5 feet of each other. A target must succeed on a Dexterity saving throw or take 1d6 acid damage.

Cantrip Upgrade: This spell's damage increases by 1d6 when you reach 5th level (2d6), 11th level (3d6), and 17th level (4d6).

Source:	Player's Handbook (2014) p. 211</text>
    <roll description="Acid Damage" level="0">1d6</roll>
    <roll description="Acid Damage" level="5">2d6</roll>
    <roll description="Acid Damage" level="11">3d6</roll>
    <roll description="Acid Damage" level="17">4d6</roll>
  </spell>
</compendium>
XML;

        // Import the spell
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported spell
        $spell = Spell::where('name', 'Acid Splash')->first();
        $this->assertNotNull($spell, 'Spell should be imported');

        // Reconstruct XML from database
        $reconstructed = $this->reconstructSpellXml($spell);

        // Verify core attributes
        $this->assertEquals('Acid Splash', (string) $reconstructed->name);
        $this->assertEquals('0', (string) $reconstructed->level);
        $this->assertEquals('C', (string) $reconstructed->school);
        $this->assertEquals('1 action', (string) $reconstructed->time);
        $this->assertEquals('60 feet', (string) $reconstructed->range);
        $this->assertEquals('V, S', (string) $reconstructed->components);
        $this->assertEquals('Instantaneous', (string) $reconstructed->duration);

        // Verify no ritual tag (cantrips aren't rituals)
        $this->assertEmpty($reconstructed->ritual);

        // Verify roll elements for cantrip scaling
        $rolls = $reconstructed->roll;
        $this->assertCount(4, $rolls, 'Should have 4 roll elements for cantrip scaling');

        // Verify each roll element
        $expectedRolls = [
            ['level' => '0', 'formula' => '1d6', 'description' => 'Acid Damage'],
            ['level' => '5', 'formula' => '2d6', 'description' => 'Acid Damage'],
            ['level' => '11', 'formula' => '3d6', 'description' => 'Acid Damage'],
            ['level' => '17', 'formula' => '4d6', 'description' => 'Acid Damage'],
        ];

        foreach ($expectedRolls as $index => $expected) {
            $roll = $rolls[$index];
            $this->assertEquals($expected['level'], (string) $roll['level']);
            $this->assertEquals($expected['formula'], (string) $roll);
            $this->assertEquals($expected['description'], (string) $roll['description']);
        }

        // Verify text content (description)
        $this->assertStringContainsString('You hurl a bubble of acid', (string) $reconstructed->text);
        $this->assertStringContainsString('Cantrip Upgrade', (string) $reconstructed->text);

        // Verify source citation
        $this->assertStringContainsString('Source:', (string) $reconstructed->text);
        $this->assertStringContainsString("Player's Handbook", (string) $reconstructed->text);
        $this->assertStringContainsString('2014', (string) $reconstructed->text);
        $this->assertStringContainsString('p. 211', (string) $reconstructed->text);

        // Verify classes
        $this->assertStringContainsString('Sorcerer', (string) $reconstructed->classes);
        $this->assertStringContainsString('Wizard', (string) $reconstructed->classes);
    }

    /**
     * Reconstruct spell XML from database model
     */
    private function reconstructSpellXml(Spell $spell): \SimpleXMLElement
    {
        $xml = '<spell>';
        $xml .= "<name>{$spell->name}</name>";
        $xml .= "<level>{$spell->level}</level>";
        $xml .= "<school>{$spell->spellSchool->code}</school>";

        if ($spell->is_ritual) {
            $xml .= '<ritual>YES</ritual>';
        }

        $xml .= "<time>{$spell->casting_time}</time>";
        $xml .= "<range>{$spell->range}</range>";

        // Reconstruct components
        $components = $spell->components;
        if ($spell->material_components) {
            // Add material description back
            $components = preg_replace('/\bM\b/', "M ({$spell->material_components})", $components);
        }
        $xml .= "<components>{$components}</components>";

        $xml .= "<duration>{$spell->duration}</duration>";

        // Reconstruct classes
        $classes = $spell->classes->pluck('name')->join(', ');
        $xml .= "<classes>{$classes}</classes>";

        // Reconstruct text with description and source
        $text = $spell->description;
        if ($spell->higher_levels) {
            $text .= "\n\n" . $spell->higher_levels;
        }

        // Add sources
        foreach ($spell->sources as $entitySource) {
            $source = $entitySource->source;
            $text .= "\n\nSource:\t{$source->name} ({$source->publication_year}) p. {$entitySource->pages}";
        }

        $xml .= "<text>" . htmlspecialchars($text, ENT_XML1) . "</text>";

        // Reconstruct roll elements from spell effects
        foreach ($spell->effects()->orderBy('min_character_level')->orderBy('min_spell_slot')->get() as $effect) {
            if (!$effect->dice_formula) {
                continue;
            }

            $level = $effect->min_character_level ?? $effect->min_spell_slot ?? '';
            $xml .= "<roll description=\"" . htmlspecialchars($effect->description, ENT_XML1) . "\"";
            if ($level !== '') {
                $xml .= " level=\"{$level}\"";
            }
            $xml .= ">{$effect->dice_formula}</roll>";
        }

        $xml .= '</spell>';

        return new \SimpleXMLElement($xml);
    }

    #[Test]
    public function it_reconstructs_concentration_spell()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Bless</name>
    <level>1</level>
    <school>EN</school>
    <time>1 action</time>
    <range>30 feet</range>
    <components>V, S, M (a sprinkling of holy water)</components>
    <duration>Concentration, up to 1 minute</duration>
    <classes>Cleric, Paladin</classes>
    <text>You bless up to three creatures of your choice within range. Whenever a target makes an attack roll or a saving throw before the spell ends, the target can roll a d4 and add the number rolled to the attack roll or saving throw.

At Higher Levels: When you cast this spell using a spell slot of 2nd level or higher, you can target one additional creature for each slot level above 1st.

Source:	Player's Handbook (2014) p. 219</text>
  </spell>
</compendium>
XML;

        $this->importer->importFromFile($this->createTempXmlFile($originalXml));
        $spell = Spell::where('name', 'Bless')->first();

        $reconstructed = $this->reconstructSpellXml($spell);

        // Verify concentration flag
        $this->assertTrue($spell->needs_concentration, 'Spell should require concentration');
        $this->assertStringContainsString('Concentration', (string) $reconstructed->duration);

        // Verify material components extracted
        $this->assertEquals('a sprinkling of holy water', $spell->material_components);
        $this->assertStringContainsString('M (a sprinkling of holy water)', (string) $reconstructed->components);
    }

    #[Test]
    public function it_reconstructs_ritual_spell()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Alarm</name>
    <level>1</level>
    <school>A</school>
    <ritual>YES</ritual>
    <time>1 minute</time>
    <range>30 feet</range>
    <components>V, S, M (a tiny bell and a piece of fine silver wire)</components>
    <duration>8 hours</duration>
    <classes>Ranger, Wizard</classes>
    <text>You set an alarm against unwanted intrusion. Choose a door, a window, or an area within range that is no larger than a 20-foot cube.

Source:	Player's Handbook (2014) p. 211</text>
  </spell>
</compendium>
XML;

        $this->importer->importFromFile($this->createTempXmlFile($originalXml));
        $spell = Spell::where('name', 'Alarm')->first();

        $reconstructed = $this->reconstructSpellXml($spell);

        // Verify ritual flag
        $this->assertTrue($spell->is_ritual, 'Spell should be a ritual');
        $this->assertEquals('YES', (string) $reconstructed->ritual);
    }

    #[Test]
    public function it_reconstructs_spell_with_multiple_sources()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Test Multi-Source Spell</name>
    <level>2</level>
    <school>EV</school>
    <time>1 action</time>
    <range>Self</range>
    <components>V, S</components>
    <duration>1 minute</duration>
    <classes>Wizard</classes>
    <text>A test spell appearing in multiple books.

Source:	Player's Handbook (2014) p. 100,
	Tasha's Cauldron of Everything (2020) p. 50</text>
  </spell>
</compendium>
XML;

        $this->importer->importFromFile($this->createTempXmlFile($originalXml));
        $spell = Spell::where('name', 'Test Multi-Source Spell')->first();

        // Verify multiple sources imported
        $this->assertCount(2, $spell->sources, 'Should have 2 source citations');

        $sourceCodes = $spell->sources->pluck('source.code')->toArray();
        $this->assertContains('PHB', $sourceCodes);
        $this->assertContains('TCE', $sourceCodes);

        $pagesData = $spell->sources->keyBy('source.code');
        $this->assertEquals('100', $pagesData['PHB']->pages);
        $this->assertEquals('50', $pagesData['TCE']->pages);

        $reconstructed = $this->reconstructSpellXml($spell);

        // Verify both sources in reconstructed text
        $text = (string) $reconstructed->text;
        $this->assertStringContainsString("Player's Handbook", $text);
        $this->assertStringContainsString('p. 100', $text);
        $this->assertStringContainsString("Tasha's Cauldron of Everything", $text);
        $this->assertStringContainsString('p. 50', $text);
    }

    #[Test]
    public function it_reconstructs_spell_effects_with_damage_types()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Fireball</name>
    <level>3</level>
    <school>EV</school>
    <time>1 action</time>
    <range>150 feet</range>
    <components>V, S, M (a tiny ball of bat guano and sulfur)</components>
    <duration>Instantaneous</duration>
    <classes>Sorcerer, Wizard</classes>
    <text>A bright streak flashes from your pointing finger to a point you choose within range and then blossoms with a low roar into an explosion of flame.

At Higher Levels: When you cast this spell using a spell slot of 4th level or higher, the damage increases by 1d6 for each slot level above 3rd.

Source:	Player's Handbook (2014) p. 241</text>
    <roll description="Fire Damage">8d6</roll>
  </spell>
</compendium>
XML;

        $this->importer->importFromFile($this->createTempXmlFile($originalXml));
        $spell = Spell::where('name', 'Fireball')->first();

        $reconstructed = $this->reconstructSpellXml($spell);

        // Verify spell effect captured
        $this->assertCount(1, $spell->effects);
        $effect = $spell->effects->first();

        $this->assertEquals('8d6', $effect->dice_formula);
        $this->assertEquals('Fire Damage', $effect->description);

        // Verify roll element reconstructed
        $rolls = $reconstructed->roll;
        $this->assertCount(1, $rolls);
        $this->assertEquals('8d6', (string) $rolls[0]);
        $this->assertEquals('Fire Damage', (string) $rolls[0]['description']);
    }

    #[Test]
    public function it_reconstructs_class_associations()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Booming Blade</name>
    <level>0</level>
    <school>EV</school>
    <time>1 action</time>
    <range>Self (5-foot radius)</range>
    <components>S, M (a melee weapon worth at least 1 sp)</components>
    <duration>1 round</duration>
    <classes>Fighter (Eldritch Knight), Rogue (Arcane Trickster), Sorcerer, Warlock, Wizard</classes>
    <text>You brandish the weapon used in the spell's casting and make a melee attack with it against one creature within 5 feet of you.

Source:	Tasha's Cauldron of Everything (2020) p. 106</text>
    <roll description="Thunder Damage" level="0">1d8</roll>
    <roll description="Thunder Damage" level="5">2d8</roll>
    <roll description="Thunder Damage" level="11">3d8</roll>
    <roll description="Thunder Damage" level="17">4d8</roll>
  </spell>
</compendium>
XML;

        $this->importer->importFromFile($this->createTempXmlFile($originalXml));
        $spell = Spell::where('name', 'Booming Blade')->first();

        // Verify class associations (base classes only, subclass stripped)
        $classNames = $spell->classes->pluck('name')->toArray();

        // Should have base classes: Fighter, Rogue, Sorcerer, Warlock, Wizard
        $this->assertCount(5, $classNames);
        $this->assertContains('Fighter', $classNames);
        $this->assertContains('Rogue', $classNames);
        $this->assertContains('Sorcerer', $classNames);
        $this->assertContains('Warlock', $classNames);
        $this->assertContains('Wizard', $classNames);

        $reconstructed = $this->reconstructSpellXml($spell);

        // Verify classes reconstructed (without subclass notation)
        $classesText = (string) $reconstructed->classes;
        $this->assertStringContainsString('Fighter', $classesText);
        $this->assertStringContainsString('Rogue', $classesText);
        $this->assertStringContainsString('Sorcerer', $classesText);
        $this->assertStringContainsString('Warlock', $classesText);
        $this->assertStringContainsString('Wizard', $classesText);

        // Note: Subclass info "(Eldritch Knight)" is intentionally stripped
        // This is a design decision documented in the test plan
    }

    #[Test]
    public function it_reconstructs_higher_levels_text()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Cure Wounds</name>
    <level>1</level>
    <school>EV</school>
    <time>1 action</time>
    <range>Touch</range>
    <components>V, S</components>
    <duration>Instantaneous</duration>
    <classes>Bard, Cleric, Druid, Paladin, Ranger</classes>
    <text>A creature you touch regains a number of hit points equal to 1d8 + your spellcasting ability modifier.

At Higher Levels: When you cast this spell using a spell slot of 2nd level or higher, the healing increases by 1d8 for each slot level above 1st.

Source:	Player's Handbook (2014) p. 230</text>
  </spell>
</compendium>
XML;

        $this->importer->importFromFile($this->createTempXmlFile($originalXml));
        $spell = Spell::where('name', 'Cure Wounds')->first();

        // Note: Parser currently stores "At Higher Levels" in description
        // This is acceptable - we're documenting current behavior
        $this->assertStringContainsString('At Higher Levels', $spell->description);

        $reconstructed = $this->reconstructSpellXml($spell);

        // Verify "At Higher Levels" text preserved in reconstruction
        $text = (string) $reconstructed->text;
        $this->assertStringContainsString('At Higher Levels', $text);
        $this->assertStringContainsString('healing increases by 1d8', $text);
    }

    /**
     * Create temporary XML file for import testing
     */
    private function createTempXmlFile(string $xmlContent): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'spell_test_');
        file_put_contents($tempFile, $xmlContent);
        return $tempFile;
    }
}
