<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\Concerns\ParsesSubclassSpellTables;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-pure')]
class ParsesSubclassSpellTablesTest extends TestCase
{
    use ParsesSubclassSpellTables;

    #[Test]
    public function parses_cleric_domain_spells(): void
    {
        $text = <<<'TEXT'
Domain Spells:
At each indicated cleric level, add the listed spells to your spells prepared.

Life Domain Spells:
Cleric Level | Spells
1st | bless, cure wounds
3rd | lesser restoration, spiritual weapon
5th | beacon of hope, revivify
7th | death ward, guardian of faith
9th | mass cure wounds, raise dead

Source: Player's Handbook (2014) p. 60
TEXT;

        $result = $this->parseSubclassSpellTable($text);

        $this->assertNotNull($result);
        $this->assertCount(5, $result);

        // Check first level
        $this->assertEquals(1, $result[0]['level']);
        $this->assertEquals(['bless', 'cure wounds'], $result[0]['spells']);

        // Check third level
        $this->assertEquals(3, $result[1]['level']);
        $this->assertEquals(['lesser restoration', 'spiritual weapon'], $result[1]['spells']);

        // Check ninth level
        $this->assertEquals(9, $result[4]['level']);
        $this->assertEquals(['mass cure wounds', 'raise dead'], $result[4]['spells']);
    }

    #[Test]
    public function parses_druid_circle_spells(): void
    {
        $text = <<<'TEXT'
Arctic:
At each indicated level, add the listed spells to your druid spell list.

Arctic:
Druid Level | Circle Spells
3rd | hold person, spike growth
5th | sleet storm, slow
7th | freedom of movement, ice storm
9th | commune with nature, cone of cold

Source: Player's Handbook (2014) p. 68
TEXT;

        $result = $this->parseSubclassSpellTable($text);

        $this->assertNotNull($result);
        $this->assertCount(4, $result);

        // Druid circles start at 3rd level
        $this->assertEquals(3, $result[0]['level']);
        $this->assertEquals(['hold person', 'spike growth'], $result[0]['spells']);
    }

    #[Test]
    public function parses_warlock_expanded_spells(): void
    {
        $text = <<<'TEXT'
The Fiend lets you choose from an expanded list of spells when you learn a warlock spell.

Fiend Expanded Spells:
Spell Level | Spells
1st | burning hands, command
2nd | blindness/deafness, scorching ray
3rd | fireball, stinking cloud
4th | fire shield, wall of fire
5th | flame strike, hallow

Source: Player's Handbook (2014) p. 109
TEXT;

        $result = $this->parseSubclassSpellTable($text);

        $this->assertNotNull($result);
        $this->assertCount(5, $result);

        // Warlock uses spell level, not class level
        $this->assertEquals(1, $result[0]['level']);
        $this->assertEquals(['burning hands', 'command'], $result[0]['spells']);

        // Check spell with slash in name
        $this->assertEquals(2, $result[1]['level']);
        $this->assertContains('blindness/deafness', $result[1]['spells']);
    }

    #[Test]
    public function returns_null_for_text_without_spell_table(): void
    {
        $text = <<<'TEXT'
At 1st level, you gain proficiency with heavy armor.

Source: Player's Handbook (2014) p. 60
TEXT;

        $result = $this->parseSubclassSpellTable($text);

        $this->assertNull($result);
    }

    #[Test]
    public function handles_three_spells_per_level(): void
    {
        // Some homebrew or variant rules might have 3 spells
        $text = <<<'TEXT'
Custom Domain Spells:
Cleric Level | Spells
1st | spell one, spell two, spell three

Source: Test
TEXT;

        $result = $this->parseSubclassSpellTable($text);

        $this->assertNotNull($result);
        $this->assertEquals(['spell one', 'spell two', 'spell three'], $result[0]['spells']);
    }

    #[Test]
    public function parses_paladin_oath_spells(): void
    {
        $text = <<<'TEXT'
Oath Spells:
You gain oath spells at the paladin levels listed.

Oath of Devotion Spells:
Paladin Level | Spells
3rd | protection from evil and good, sanctuary
5th | lesser restoration, zone of truth
9th | beacon of hope, dispel magic
13th | freedom of movement, guardian of faith
17th | commune, flame strike

Source: Player's Handbook (2014) p. 86
TEXT;

        $result = $this->parseSubclassSpellTable($text);

        $this->assertNotNull($result);
        $this->assertCount(5, $result);

        // Paladin oath spells start at 3rd level
        $this->assertEquals(3, $result[0]['level']);
        $this->assertEquals(['protection from evil and good', 'sanctuary'], $result[0]['spells']);

        // Check 17th level
        $this->assertEquals(17, $result[4]['level']);
        $this->assertEquals(['commune', 'flame strike'], $result[4]['spells']);
    }

    #[Test]
    public function parses_artificer_spells_singular(): void
    {
        // Artificer uses "Spell" (singular) in some tables
        $text = <<<'TEXT'
Artificer Specialist Spells:
At certain levels you gain access to special spells.

Alchemist Spells:
Artificer Level | Spell
3rd | healing word
5th | flaming sphere

Source: Tasha's Cauldron of Everything p. 14
TEXT;

        $result = $this->parseSubclassSpellTable($text);

        $this->assertNotNull($result);
        $this->assertCount(2, $result);

        $this->assertEquals(3, $result[0]['level']);
        $this->assertEquals(['healing word'], $result[0]['spells']);

        $this->assertEquals(5, $result[1]['level']);
        $this->assertEquals(['flaming sphere'], $result[1]['spells']);
    }

    #[Test]
    public function parses_ranger_spells(): void
    {
        $text = <<<'TEXT'
Ranger Archetype Spells:
Starting at 3rd level, you learn an additional spell.

Fey Wanderer Spells:
Ranger Level | Spells
3rd | charm person
5th | misty step
9th | dispel magic

Source: Tasha's Cauldron of Everything p. 58
TEXT;

        $result = $this->parseSubclassSpellTable($text);

        $this->assertNotNull($result);
        $this->assertCount(3, $result);

        $this->assertEquals(3, $result[0]['level']);
        $this->assertEquals(['charm person'], $result[0]['spells']);
    }

    #[Test]
    public function parses_sorcerer_spells(): void
    {
        $text = <<<'TEXT'
Clockwork Magic:
You learn additional spells when you reach certain levels.

Clockwork Magic Spells:
Sorcerer Level | Spells
1st | alarm, protection from evil and good
3rd | aid, lesser restoration
5th | dispel magic, protection from energy

Source: Tasha's Cauldron of Everything p. 68
TEXT;

        $result = $this->parseSubclassSpellTable($text);

        $this->assertNotNull($result);
        $this->assertCount(3, $result);

        $this->assertEquals(1, $result[0]['level']);
        $this->assertEquals(['alarm', 'protection from evil and good'], $result[0]['spells']);

        $this->assertEquals(5, $result[2]['level']);
        $this->assertEquals(['dispel magic', 'protection from energy'], $result[2]['spells']);
    }
}
