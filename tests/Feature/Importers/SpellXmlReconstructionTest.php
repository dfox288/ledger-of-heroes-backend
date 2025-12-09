<?php

namespace Tests\Feature\Importers;

use App\Models\CharacterClass;
use App\Models\Spell;
use App\Services\Importers\SpellImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('importers')]
class SpellXmlReconstructionTest extends TestCase
{
    use RefreshDatabase;

    private SpellImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new SpellImporter;
    }

    #[Test]
    public function it_reconstructs_simple_cantrip()
    {
        // Create required classes for spell associations
        CharacterClass::factory()->create(['name' => 'Sorcerer', 'slug' => 'sorcerer']);
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

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
            $text .= "\n\n".$spell->higher_levels;
        }

        // Add sources
        foreach ($spell->sources as $entitySource) {
            $source = $entitySource->source;
            $text .= "\n\nSource:\t{$source->name} ({$source->publication_year}) p. {$entitySource->pages}";
        }

        $xml .= '<text>'.htmlspecialchars($text, ENT_XML1).'</text>';

        // Reconstruct roll elements from spell effects
        foreach ($spell->effects()->orderBy('min_character_level')->orderBy('min_spell_slot')->get() as $effect) {
            if (! $effect->dice_formula) {
                continue;
            }

            $level = $effect->min_character_level ?? $effect->min_spell_slot ?? '';
            $xml .= '<roll description="'.htmlspecialchars($effect->description, ENT_XML1).'"';
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
        // Create required classes for spell associations
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);
        CharacterClass::factory()->create(['name' => 'Sorcerer', 'slug' => 'sorcerer']);

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
        // Create required base classes
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter']);
        $rogue = CharacterClass::factory()->create(['name' => 'Rogue', 'slug' => 'rogue']);
        CharacterClass::factory()->create(['name' => 'Sorcerer', 'slug' => 'sorcerer']);
        CharacterClass::factory()->create(['name' => 'Warlock', 'slug' => 'warlock']);
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        // Create subclasses (needed for subclass-specific spells)
        CharacterClass::factory()->create([
            'name' => 'Eldritch Knight',
            'slug' => 'eldritch-knight',
            'parent_class_id' => $fighter->id,
        ]);
        CharacterClass::factory()->create([
            'name' => 'Arcane Trickster',
            'slug' => 'arcane-trickster',
            'parent_class_id' => $rogue->id,
        ]);

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

        // Verify class associations (subclasses when specified in parentheses)
        $classNames = $spell->classes->pluck('name')->sort()->values()->toArray();

        // Should have: Eldritch Knight, Arcane Trickster (subclasses), Sorcerer, Warlock, Wizard (base classes)
        $this->assertCount(5, $classNames);
        $this->assertEquals([
            'Arcane Trickster',
            'Eldritch Knight',
            'Sorcerer',
            'Warlock',
            'Wizard',
        ], $classNames, 'Should use subclasses when specified in XML');

        $reconstructed = $this->reconstructSpellXml($spell);

        // Verify classes reconstructed
        $classesText = (string) $reconstructed->classes;
        $this->assertStringContainsString('Eldritch Knight', $classesText);
        $this->assertStringContainsString('Arcane Trickster', $classesText);
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

        // Verify "At Higher Levels" is extracted to separate field
        $this->assertNotNull($spell->higher_levels);
        $this->assertStringContainsString('2nd level or higher', $spell->higher_levels);
        $this->assertStringContainsString('healing increases by 1d8', $spell->higher_levels);

        // Should NOT be in description anymore
        $this->assertStringNotContainsString('At Higher Levels', $spell->description);

        $reconstructed = $this->reconstructSpellXml($spell);

        // Verify reconstruction includes higher_levels data (in text for now)
        $text = (string) $reconstructed->text;
        $this->assertStringContainsString('healing increases by 1d8', $text);
    }

    #[Test]
    public function it_reconstructs_spell_effects_with_damage_type_associations()
    {
        // Create required classes
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);
        CharacterClass::factory()->create(['name' => 'Sorcerer', 'slug' => 'sorcerer']);

        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Magic Missile</name>
    <level>1</level>
    <school>EV</school>
    <time>1 action</time>
    <range>120 feet</range>
    <components>V, S</components>
    <duration>Instantaneous</duration>
    <classes>Sorcerer, Wizard</classes>
    <text>You create three glowing darts of magical force. Each dart hits a creature of your choice that you can see within range.

At Higher Levels: When you cast this spell using a spell slot of 2nd level or higher, the spell creates one more dart for each slot level above 1st.

Source:	Player's Handbook (2014) p. 257</text>
    <roll description="Force Damage">1d4+1</roll>
  </spell>
</compendium>
XML;

        $this->importer->importFromFile($this->createTempXmlFile($originalXml));
        $spell = Spell::where('name', 'Magic Missile')->first();

        // Verify spell effect has damage type FK populated
        $this->assertCount(1, $spell->effects);
        $effect = $spell->effects->first();

        $this->assertEquals('1d4+1', $effect->dice_formula);
        $this->assertEquals('Force Damage', $effect->description);

        // NEW: Verify damage_type_id is set (not NULL)
        $this->assertNotNull($effect->damage_type_id, 'Damage type FK should be populated');
        $this->assertEquals('Force', $effect->damageType->name);
        $this->assertEquals('FC', $effect->damageType->code);
    }

    #[Test]
    public function it_reconstructs_spell_with_tags()
    {
        // Create required classes
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Simulacrum</name>
    <level>7</level>
    <school>I</school>
    <time>12 hours</time>
    <range>Touch</range>
    <components>V, S, M (snow or ice in quantities sufficient to made a life-size copy of the duplicated creature; some hair, fingernail clippings, or other piece of that creature's body placed inside the snow or ice; and powdered ruby worth 1,500 gp, sprinkled over the duplicate and consumed by the spell)</components>
    <duration>Until dispelled</duration>
    <classes>Touch Spells, Wizard</classes>
    <text>You shape an illusory duplicate of one beast or humanoid that is within range for the entire casting time of the spell.

Source:	Player's Handbook (2014) p. 276</text>
  </spell>
</compendium>
XML;

        $this->importer->importFromFile($this->createTempXmlFile($originalXml));
        $spell = Spell::where('name', 'Simulacrum')->first();

        // Verify class associations (only real classes, not tags)
        $classNames = $spell->classes->pluck('name')->toArray();
        $this->assertContains('Wizard', $classNames);
        $this->assertNotContains('Touch Spells', $classNames, 'Touch Spells should be a tag, not a class');

        // NEW: Verify tag system
        $this->assertCount(1, $spell->tags, 'Should have 1 tag');
        $this->assertEquals('Touch Spells', $spell->tags->first()->name);

        // Verify range is "Touch" (why it's tagged as Touch Spells)
        $this->assertEquals('Touch', $spell->range);
    }

    #[Test]
    public function it_reconstructs_spell_with_subclass_alias_mapping()
    {
        // Create Druid base class
        $druid = CharacterClass::factory()->create(['name' => 'Druid', 'slug' => 'druid']);
        CharacterClass::factory()->create(['name' => 'Sorcerer', 'slug' => 'sorcerer']);
        CharacterClass::factory()->create(['name' => 'Warlock', 'slug' => 'warlock']);
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        // Create Circle of the Land subclass (the REAL subclass)
        CharacterClass::factory()->create([
            'name' => 'Circle of the Land',
            'slug' => 'circle-of-the-land',
            'parent_class_id' => $druid->id,
        ]);

        // Create Paladin and subclasses
        $paladin = CharacterClass::factory()->create(['name' => 'Paladin', 'slug' => 'paladin']);
        CharacterClass::factory()->create([
            'name' => 'Oath of the Ancients',
            'slug' => 'oath-of-the-ancients',
            'parent_class_id' => $paladin->id,
        ]);
        CharacterClass::factory()->create([
            'name' => 'Oath of Vengeance',
            'slug' => 'oath-of-vengeance',
            'parent_class_id' => $paladin->id,
        ]);

        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Misty Step</name>
    <level>2</level>
    <school>C</school>
    <time>1 bonus action</time>
    <range>Self</range>
    <components>V</components>
    <duration>Instantaneous</duration>
    <classes>Sorcerer, Warlock, Wizard, Druid (Coast), Paladin (Ancients), Paladin (Vengeance)</classes>
    <text>Briefly surrounded by silvery mist, you teleport up to 30 feet to an unoccupied space that you can see.

Source:	Player's Handbook (2014) p. 260</text>
  </spell>
</compendium>
XML;

        $this->importer->importFromFile($this->createTempXmlFile($originalXml));
        $spell = Spell::where('name', 'Misty Step')->first();

        // Verify alias mapping worked
        $classNames = $spell->classes->pluck('name')->sort()->values()->toArray();

        // "Coast" should map to "Circle of the Land"
        $this->assertContains('Circle of the Land', $classNames, 'Coast should map to Circle of the Land');

        // "Ancients" should map to "Oath of the Ancients"
        $this->assertContains('Oath of the Ancients', $classNames, 'Ancients should map to Oath of the Ancients');

        // "Vengeance" should map to "Oath of Vengeance"
        $this->assertContains('Oath of Vengeance', $classNames, 'Vengeance should map to Oath of Vengeance');

        // Also verify base classes
        $this->assertContains('Sorcerer', $classNames);
        $this->assertContains('Warlock', $classNames);
        $this->assertContains('Wizard', $classNames);
    }

    #[Test]
    public function it_reconstructs_spell_with_fuzzy_subclass_matching()
    {
        // Create Warlock base class
        $warlock = CharacterClass::factory()->create(['name' => 'Warlock', 'slug' => 'warlock']);
        CharacterClass::factory()->create(['name' => 'Bard', 'slug' => 'bard']);
        CharacterClass::factory()->create(['name' => 'Sorcerer', 'slug' => 'sorcerer']);
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        // Create subclass with "The" prefix (database has "The Archfey", XML has "Archfey")
        CharacterClass::factory()->create([
            'name' => 'The Archfey',
            'slug' => 'the-archfey',
            'parent_class_id' => $warlock->id,
        ]);

        // Create Rogue and subclass
        $rogue = CharacterClass::factory()->create(['name' => 'Rogue', 'slug' => 'rogue']);
        CharacterClass::factory()->create([
            'name' => 'Arcane Trickster',
            'slug' => 'arcane-trickster',
            'parent_class_id' => $rogue->id,
        ]);

        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Sleep</name>
    <level>1</level>
    <school>EN</school>
    <time>1 action</time>
    <range>90 feet</range>
    <components>V, S, M (a pinch of fine sand, rose petals, or a cricket)</components>
    <duration>1 minute</duration>
    <classes>Bard, Sorcerer, Wizard, Rogue (Arcane Trickster), Warlock (Archfey)</classes>
    <text>This spell sends creatures into a magical slumber. Roll 5d8; the total is how many hit points of creatures this spell can affect.

At Higher Levels: When you cast this spell using a spell slot of 2nd level or higher, roll an additional 2d8 for each slot level above 1st.

Source:	Player's Handbook (2014) p. 276</text>
  </spell>
</compendium>
XML;

        $this->importer->importFromFile($this->createTempXmlFile($originalXml));
        $spell = Spell::where('name', 'Sleep')->first();

        // Verify fuzzy matching worked
        $classNames = $spell->classes->pluck('name')->sort()->values()->toArray();

        // "Archfey" should match "The Archfey" via fuzzy LIKE
        $this->assertContains('The Archfey', $classNames, 'Archfey should fuzzy match The Archfey');

        // Also verify other classes
        $this->assertContains('Arcane Trickster', $classNames);
        $this->assertContains('Bard', $classNames);
        $this->assertContains('Sorcerer', $classNames);
        $this->assertContains('Wizard', $classNames);

        // Should have 5 classes total
        $this->assertCount(5, $classNames);
    }

    #[Test]
    public function it_reconstructs_spell_with_multiple_damage_types()
    {
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Chaos Bolt</name>
    <level>1</level>
    <school>EV</school>
    <time>1 action</time>
    <range>120 feet</range>
    <components>V, S</components>
    <duration>Instantaneous</duration>
    <classes>Wizard</classes>
    <text>You hurl an undulating, warbling mass of chaotic energy at one creature in range.

At Higher Levels: When you cast this spell using a spell slot of 2nd level or higher, each target takes an extra 1d6 damage of the type rolled for each slot level above 1st.

Source:	Xanathar's Guide to Everything (2017) p. 151</text>
    <roll description="Acid Damage">2d8+1d6</roll>
    <roll description="Cold Damage">2d8+1d6</roll>
    <roll description="Fire Damage">2d8+1d6</roll>
    <roll description="Force Damage">2d8+1d6</roll>
    <roll description="Lightning Damage">2d8+1d6</roll>
    <roll description="Poison Damage">2d8+1d6</roll>
    <roll description="Psychic Damage">2d8+1d6</roll>
    <roll description="Thunder Damage">2d8+1d6</roll>
  </spell>
</compendium>
XML;

        $this->importer->importFromFile($this->createTempXmlFile($originalXml));
        $spell = Spell::where('name', 'Chaos Bolt')->first();

        // Verify all damage type effects imported
        $this->assertCount(8, $spell->effects, 'Should have 8 effects for 8 damage types');

        // Verify each effect has proper damage_type_id
        $damageTypes = $spell->effects->pluck('damageType.name')->sort()->values()->toArray();
        $expectedTypes = ['Acid', 'Cold', 'Fire', 'Force', 'Lightning', 'Poison', 'Psychic', 'Thunder'];

        $this->assertEquals($expectedTypes, $damageTypes, 'All damage types should be properly associated');

        // Verify all effects have the same formula
        foreach ($spell->effects as $effect) {
            $this->assertEquals('2d8+1d6', $effect->dice_formula);
            $this->assertNotNull($effect->damage_type_id, 'Each effect should have damage type FK');
        }
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
