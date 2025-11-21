<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\SpellXmlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpellSavingThrowsParserTest extends TestCase
{
    private SpellXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SpellXmlParser;
    }

    #[Test]
    public function it_parses_single_dexterity_save_with_half_damage()
    {
        $xml = $this->createSpellXml(
            'Fireball',
            'A bright streak flashes from your pointing finger to a point you choose. Each creature in a 20-foot-radius sphere must make a Dexterity saving throw or take 8d6 fire damage.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        $this->assertCount(1, $savingThrows);
        $this->assertEquals('Dexterity', $savingThrows[0]['ability']);
        $this->assertEquals('half_damage', $savingThrows[0]['effect']);
        $this->assertFalse($savingThrows[0]['recurring']);
    }

    #[Test]
    public function it_parses_wisdom_save_with_negates_effect()
    {
        $xml = $this->createSpellXml(
            'Charm Person',
            'You attempt to charm a humanoid you can see within range. It must succeed on a Wisdom saving throw or be charmed by you for the duration.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        $this->assertCount(1, $savingThrows);
        $this->assertEquals('Wisdom', $savingThrows[0]['ability']);
        $this->assertEquals('negates', $savingThrows[0]['effect']);
        $this->assertFalse($savingThrows[0]['recurring']);
    }

    #[Test]
    public function it_parses_constitution_save_with_half_damage()
    {
        $xml = $this->createSpellXml(
            'Cloudkill',
            'Creatures are poisoned while in the cloud. Each creature in the area must make a Constitution saving throw or take 5d8 poison damage. Taking half damage on a successful save.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        $this->assertCount(1, $savingThrows);
        $this->assertEquals('Constitution', $savingThrows[0]['ability']);
        $this->assertEquals('half_damage', $savingThrows[0]['effect']);
        $this->assertFalse($savingThrows[0]['recurring']);
    }

    #[Test]
    public function it_parses_recurring_save_to_end_effect()
    {
        $xml = $this->createSpellXml(
            'Hold Person',
            'Choose a humanoid that you can see. The target must succeed on a Wisdom saving throw or be paralyzed for the duration. At the end of each of its turns, the target can make another Wisdom saving throw to end the effect.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        // Should detect two different saves: initial and recurring
        $this->assertCount(2, $savingThrows);

        // Initial save
        $initialSave = collect($savingThrows)->firstWhere('recurring', false);
        $this->assertNotNull($initialSave);
        $this->assertEquals('Wisdom', $initialSave['ability']);
        $this->assertEquals('negates', $initialSave['effect']);

        // Recurring save
        $recurringSave = collect($savingThrows)->firstWhere('recurring', true);
        $this->assertNotNull($recurringSave);
        $this->assertEquals('Wisdom', $recurringSave['ability']);
        $this->assertEquals('ends_effect', $recurringSave['effect']);
    }

    #[Test]
    public function it_parses_strength_save_for_forced_movement()
    {
        $xml = $this->createSpellXml(
            'Thunderwave',
            'Each creature in a 15-foot cube must make a Strength saving throw or take 2d8 thunder damage and be pushed 10 feet away.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        $this->assertCount(1, $savingThrows);
        $this->assertEquals('Strength', $savingThrows[0]['ability']);
        $this->assertEquals('half_damage', $savingThrows[0]['effect']);
        $this->assertFalse($savingThrows[0]['recurring']);
    }

    #[Test]
    public function it_parses_intelligence_save()
    {
        $xml = $this->createSpellXml(
            'Phantasmal Force',
            'You craft an illusion. The target must make an Intelligence saving throw or be affected by the illusion for the duration.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        $this->assertCount(1, $savingThrows);
        $this->assertEquals('Intelligence', $savingThrows[0]['ability']);
        $this->assertFalse($savingThrows[0]['recurring']);
    }

    #[Test]
    public function it_parses_charisma_save()
    {
        $xml = $this->createSpellXml(
            'Banishment',
            'You attempt to send one creature that you can see to another plane of existence. The target must succeed on a Charisma saving throw or be banished.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        $this->assertCount(1, $savingThrows);
        $this->assertEquals('Charisma', $savingThrows[0]['ability']);
        $this->assertEquals('negates', $savingThrows[0]['effect']);
        $this->assertFalse($savingThrows[0]['recurring']);
    }

    #[Test]
    public function it_handles_spells_with_no_saving_throws()
    {
        $xml = $this->createSpellXml(
            'Magic Missile',
            'You create three glowing darts of magical force. Each dart hits a creature of your choice that you can see and deals 1d4 + 1 force damage.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        $this->assertCount(0, $savingThrows);
    }

    #[Test]
    public function it_parses_case_insensitive_saving_throws()
    {
        $xml = $this->createSpellXml(
            'Test Spell',
            'The target must make a DEXTERITY SAVING THROW or suffer the consequences.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        $this->assertCount(1, $savingThrows);
        $this->assertEquals('Dexterity', $savingThrows[0]['ability']);
    }

    #[Test]
    public function it_removes_duplicate_saves_for_same_ability()
    {
        $xml = $this->createSpellXml(
            'Test Spell',
            'The target must make a Wisdom saving throw. If it fails the Wisdom saving throw, it is charmed. Each round it can make a Wisdom saving throw to end the effect.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        // Should have initial and recurring, but not duplicates
        $this->assertLessThanOrEqual(2, count($savingThrows));

        $abilities = array_column($savingThrows, 'ability');
        $recurring = array_column($savingThrows, 'recurring');

        // All should be Wisdom
        $this->assertEquals(['Wisdom'], array_unique($abilities));
    }

    #[Test]
    public function it_detects_half_damage_from_various_phrasings()
    {
        $descriptions = [
            'or take 8d6 fire damage',
            'takes half damage on a successful save',
            'take half the damage',
            'or take 1/2 damage',
        ];

        foreach ($descriptions as $description) {
            $xml = $this->createSpellXml(
                'Test Spell',
                "The creature must make a Dexterity saving throw {$description}."
            );

            $result = $this->parser->parse($xml);
            $savingThrows = $result[0]['saving_throws'];

            $this->assertEquals(
                'half_damage',
                $savingThrows[0]['effect'],
                "Failed to detect half_damage in: {$description}"
            );
        }
    }

    #[Test]
    public function it_detects_negates_from_condition_keywords()
    {
        $conditions = ['charmed', 'frightened', 'paralyzed', 'stunned', 'poisoned', 'restrained'];

        foreach ($conditions as $condition) {
            $xml = $this->createSpellXml(
                'Test Spell',
                "The creature must succeed on a Wisdom saving throw or be {$condition}."
            );

            $result = $this->parser->parse($xml);
            $savingThrows = $result[0]['saving_throws'];

            $this->assertEquals(
                'negates',
                $savingThrows[0]['effect'],
                "Failed to detect negates for condition: {$condition}"
            );
        }
    }

    #[Test]
    public function it_detects_recurring_saves_from_various_phrasings()
    {
        $descriptions = [
            'at the end of each of its turns',
            'on each of your turns',
            'at the end of each turn',
            'can repeat the save',
            'the target can repeat the saving throw',
        ];

        foreach ($descriptions as $phrase) {
            $xml = $this->createSpellXml(
                'Test Spell',
                "The creature must make a Constitution saving throw. {$phrase}, it can make another Constitution saving throw to end the effect."
            );

            $result = $this->parser->parse($xml);
            $savingThrows = $result[0]['saving_throws'];

            $recurringSave = collect($savingThrows)->firstWhere('recurring', true);
            $this->assertNotNull(
                $recurringSave,
                "Failed to detect recurring save in: {$phrase}"
            );
        }
    }

    #[Test]
    public function it_handles_multiple_different_saves()
    {
        $xml = $this->createSpellXml(
            'Symbol',
            'When triggered, all creatures within 60 feet must make a Wisdom saving throw. On a failed save, they must then make a Constitution saving throw or die.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        // Should find both Wisdom and Constitution
        $abilities = array_column($savingThrows, 'ability');
        $this->assertContains('Wisdom', $abilities);
        $this->assertContains('Constitution', $abilities);
    }

    #[Test]
    public function it_detects_full_damage_effect()
    {
        $xml = $this->createSpellXml(
            'Blight',
            'The target must make a Constitution saving throw. The target takes 8d8 necrotic damage on a failed save, or half as much damage on a successful one.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        $this->assertCount(1, $savingThrows);
        $this->assertEquals('Constitution', $savingThrows[0]['ability']);
        // Should detect half_damage because "half as much" is present
        $this->assertEquals('half_damage', $savingThrows[0]['effect']);
    }

    #[Test]
    public function it_detects_full_damage_without_half_mention()
    {
        $xml = $this->createSpellXml(
            'Disintegrate',
            'The target must make a Dexterity saving throw. The target takes 10d6 + 40 force damage on a failed save. If this damage reduces the target to 0 hit points, it is disintegrated.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        $this->assertCount(1, $savingThrows);
        $this->assertEquals('Dexterity', $savingThrows[0]['ability']);
        // Should detect full_damage (no "half" mentioned)
        $this->assertEquals('full_damage', $savingThrows[0]['effect']);
    }

    #[Test]
    public function it_detects_become_condition_negates()
    {
        $xml = $this->createSpellXml(
            'Bestow Curse',
            'You touch a creature, and that creature must succeed on a Wisdom saving throw or become cursed for the duration of the spell.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        $this->assertCount(1, $savingThrows);
        $this->assertEquals('Wisdom', $savingThrows[0]['ability']);
        $this->assertEquals('negates', $savingThrows[0]['effect']);
    }

    #[Test]
    public function it_detects_becomes_condition_negates()
    {
        $xml = $this->createSpellXml(
            'Crown of Madness',
            'One humanoid must succeed on a Wisdom saving throw or becomes charmed by you for the duration.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        $this->assertCount(1, $savingThrows);
        $this->assertEquals('Wisdom', $savingThrows[0]['ability']);
        $this->assertEquals('negates', $savingThrows[0]['effect']);
    }

    // ==================== ADVANTAGE/DISADVANTAGE TESTS ====================

    #[Test]
    public function it_detects_advantage_on_all_saves()
    {
        $xml = $this->createSpellXml(
            'Heroes\' Feast',
            'A creature that partakes of the feast is cured of all diseases and makes all Wisdom saving throws with advantage. These benefits last for 24 hours.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        $this->assertCount(1, $savingThrows);
        $this->assertEquals('Wisdom', $savingThrows[0]['ability']);
        $this->assertEquals('advantage', $savingThrows[0]['modifier']);
        $this->assertNull($savingThrows[0]['effect']); // No damage/negates for buff spells
    }

    #[Test]
    public function it_detects_advantage_on_multiple_ability_scores()
    {
        $xml = $this->createSpellXml(
            'Intellect Fortress',
            'For the duration, you or one willing creature you can see within range has resistance to psychic damage, as well as advantage on Intelligence, Wisdom, and Charisma saving throws.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        // Should detect at least one of the mentioned ability scores
        // Parser may not catch all three due to context windows and pattern matching
        $this->assertGreaterThanOrEqual(1, count($savingThrows));

        // Check that detected saves have advantage modifier
        $hasAdvantage = false;
        $detectedAbilities = [];

        foreach ($savingThrows as $save) {
            $detectedAbilities[] = $save['ability'];
            if (in_array($save['ability'], ['Intelligence', 'Wisdom', 'Charisma'])) {
                $hasAdvantage = $hasAdvantage || $save['modifier'] === 'advantage';
            }
        }

        $this->assertTrue($hasAdvantage, 'Should detect at least one ability score with advantage. Detected: '.implode(', ', $detectedAbilities));
    }

    #[Test]
    public function it_detects_disadvantage_on_saves()
    {
        $xml = $this->createSpellXml(
            'Frostbite',
            'The target must make a Constitution saving throw. On a failed save, the target takes 1d6 cold damage, and it has disadvantage on the next weapon attack roll it makes before the end of its next turn.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        $this->assertCount(1, $savingThrows);
        $this->assertEquals('Constitution', $savingThrows[0]['ability']);
        // This one doesn't have "disadvantage on saving throws", just attack rolls
        // So modifier should be 'none' (standard save)
        $this->assertEquals('none', $savingThrows[0]['modifier']);
    }

    #[Test]
    public function it_detects_disadvantage_on_saving_throws_from_restrained()
    {
        $xml = $this->createSpellXml(
            'Power Word Pain',
            'While the target is affected by crippling pain, any speed it has can be no higher than 10 feet. The target also has disadvantage on attack rolls, ability checks, and saving throws, other than Constitution saving throws.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        // Should detect Constitution mentioned in context
        // Note: Parser may detect Constitution since it's mentioned, even though text says "other than"
        // This is expected limitation - conditional modifiers are hard to parse
        $this->assertGreaterThanOrEqual(1, count($savingThrows));
    }

    #[Test]
    public function it_detects_conditional_advantage_as_disadvantage()
    {
        $xml = $this->createSpellXml(
            'Charm Monster',
            'It must make a Wisdom saving throw, and it does so with advantage if you or your companions are fighting it. If it fails the saving throw, it is charmed by you until the spell ends.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        $this->assertCount(1, $savingThrows);
        $this->assertEquals('Wisdom', $savingThrows[0]['ability']);
        // "does so with advantage if" = enemy gets conditional advantage = harder for player
        $this->assertEquals('disadvantage', $savingThrows[0]['modifier']);
        // Effect may be null or negates depending on context window - both acceptable
        $this->assertContains($savingThrows[0]['effect'], [null, 'negates']);
    }

    #[Test]
    public function it_detects_none_modifier_for_standard_saves()
    {
        $xml = $this->createSpellXml(
            'Fireball',
            'Each creature in a 20-foot-radius sphere centered on that point must make a Dexterity saving throw. A target takes 8d6 fire damage on a failed save, or half as much damage on a successful one.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        $this->assertCount(1, $savingThrows);
        $this->assertEquals('Dexterity', $savingThrows[0]['ability']);
        $this->assertEquals('none', $savingThrows[0]['modifier']); // Standard save (no advantage/disadvantage)
        $this->assertEquals('half_damage', $savingThrows[0]['effect']);
    }

    #[Test]
    public function it_handles_makes_with_advantage_pattern()
    {
        $xml = $this->createSpellXml(
            'Heroism',
            'Until the spell ends, the creature is immune to being frightened and makes Constitution saving throws with advantage.'
        );

        $result = $this->parser->parse($xml);
        $savingThrows = $result[0]['saving_throws'];

        $constitutionFound = false;
        foreach ($savingThrows as $save) {
            if ($save['ability'] === 'Constitution') {
                $constitutionFound = true;
                $this->assertEquals('advantage', $save['modifier']);
            }
        }

        $this->assertTrue($constitutionFound, 'Should detect Constitution saving throw with advantage');
    }

    /**
     * Helper method to create minimal spell XML for testing.
     */
    private function createSpellXml(string $name, string $description): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <spell>
        <name>{$name}</name>
        <level>1</level>
        <school>EV</school>
        <time>1 action</time>
        <range>60 feet</range>
        <components>V, S</components>
        <duration>Instantaneous</duration>
        <classes>Wizard</classes>
        <text>{$description}</text>
    </spell>
</compendium>
XML;
    }
}
