<?php

namespace Tests\Unit\Parsers\Concerns;

use App\Services\Parsers\Concerns\ParsesFeatureChoiceProgressions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class ParsesFeatureChoiceProgressionsTest extends TestCase
{
    use ParsesFeatureChoiceProgressions;

    #[Test]
    public function it_parses_battle_master_maneuvers_from_combat_superiority()
    {
        $features = [
            [
                'name' => 'Combat Superiority (Battle Master)',
                'level' => 3,
                'is_optional' => true,
                'description' => 'When you choose this archetype at 3rd level, you learn maneuvers that are fueled by special dice called superiority dice.

Maneuvers. You learn three maneuvers of your choice. Many maneuvers enhance an attack in some way. You can use only one maneuver per attack.
	You learn two additional maneuvers of your choice at 7th, 10th, and 15th level. Each time you learn new maneuvers, you can also replace one maneuver you know with a different one.',
            ],
        ];

        $counters = $this->parseFeatureChoiceProgressions($features);

        $this->assertNotEmpty($counters);

        // Should have counters at levels 3, 7, 10, 15
        $maneuverCounters = collect($counters)->where('name', 'Maneuvers Known');
        $this->assertCount(4, $maneuverCounters);

        // Check progression: 3 -> 5 -> 7 -> 9
        $this->assertEquals(3, $maneuverCounters->firstWhere('level', 3)['value']);
        $this->assertEquals(5, $maneuverCounters->firstWhere('level', 7)['value']);
        $this->assertEquals(7, $maneuverCounters->firstWhere('level', 10)['value']);
        $this->assertEquals(9, $maneuverCounters->firstWhere('level', 15)['value']);

        // Should have subclass set
        $this->assertEquals('Battle Master', $maneuverCounters->first()['subclass']);
    }

    #[Test]
    public function it_parses_sorcerer_metamagic()
    {
        $features = [
            [
                'name' => 'Metamagic',
                'level' => 3,
                'is_optional' => false,
                'description' => 'At 3rd level, you gain the ability to twist your spells to suit your needs. You gain two of the following Metamagic options of your choice. You gain another one at 10th and 17th level.
	You can use only one Metamagic option on a spell when you cast it, unless otherwise noted.',
            ],
        ];

        $counters = $this->parseFeatureChoiceProgressions($features);

        $metamagicCounters = collect($counters)->where('name', 'Metamagic Known');
        $this->assertCount(3, $metamagicCounters);

        // Check progression: 2 -> 3 -> 4
        $this->assertEquals(2, $metamagicCounters->firstWhere('level', 3)['value']);
        $this->assertEquals(3, $metamagicCounters->firstWhere('level', 10)['value']);
        $this->assertEquals(4, $metamagicCounters->firstWhere('level', 17)['value']);

        // No subclass for Sorcerer base class feature
        $this->assertNull($metamagicCounters->first()['subclass']);
    }

    #[Test]
    public function it_parses_artificer_infusions_from_embedded_table()
    {
        $features = [
            [
                'name' => 'Infuse Item',
                'level' => 2,
                'is_optional' => false,
                'description' => 'At 2nd level, you\'ve gained the ability to imbue mundane items with certain magical infusions, turning those objects into magic items.

Infusions Known:
When you gain this feature, pick four artificer infusions to learn, choosing from the "Artificer Infusions" spell list.

Infusions Table:
Level | Known | Active
2nd | 4 | 2
6th | 6 | 3
10th | 8 | 4
14th | 10 | 5
18th | 12 | 6',
            ],
        ];

        $counters = $this->parseFeatureChoiceProgressions($features);

        $infusionCounters = collect($counters)->where('name', 'Infusions Known');
        $this->assertCount(5, $infusionCounters);

        // Check progression from table
        $this->assertEquals(4, $infusionCounters->firstWhere('level', 2)['value']);
        $this->assertEquals(6, $infusionCounters->firstWhere('level', 6)['value']);
        $this->assertEquals(8, $infusionCounters->firstWhere('level', 10)['value']);
        $this->assertEquals(10, $infusionCounters->firstWhere('level', 14)['value']);
        $this->assertEquals(12, $infusionCounters->firstWhere('level', 18)['value']);
    }

    #[Test]
    public function it_parses_rune_knight_runes_from_embedded_table()
    {
        $features = [
            [
                'name' => 'Rune Carver (Rune Knight)',
                'level' => 3,
                'is_optional' => true,
                'description' => 'You can use magic runes to enhance your gear. You learn two runes of your choice, from among the runes described below, and each time you gain a level in this class, you can replace one rune you know with a different one from this feature.

Runes Known:
Fighter Level | Number of Runes
3rd | 2
7th | 3
10th | 4
15th | 5',
            ],
        ];

        $counters = $this->parseFeatureChoiceProgressions($features);

        $runeCounters = collect($counters)->where('name', 'Runes Known');
        $this->assertCount(4, $runeCounters);

        $this->assertEquals(2, $runeCounters->firstWhere('level', 3)['value']);
        $this->assertEquals(3, $runeCounters->firstWhere('level', 7)['value']);
        $this->assertEquals(4, $runeCounters->firstWhere('level', 10)['value']);
        $this->assertEquals(5, $runeCounters->firstWhere('level', 15)['value']);

        $this->assertEquals('Rune Knight', $runeCounters->first()['subclass']);
    }

    #[Test]
    public function it_parses_arcane_archer_arcane_shots()
    {
        $features = [
            [
                'name' => 'Arcane Shot (Arcane Archer)',
                'level' => 3,
                'is_optional' => true,
                'description' => 'At 3rd level, you learn to unleash special magical effects with some of your shots. When you gain this feature, you learn two Arcane Shot options of your choice.',
            ],
            [
                'name' => 'Additional Arcane Shot Option (Arcane Archer)',
                'level' => 7,
                'is_optional' => true,
                'description' => 'You gain an additional Arcane Shot option of your choice when you reach 7th level.',
            ],
            [
                'name' => 'Additional Arcane Shot Option (Arcane Archer)',
                'level' => 10,
                'is_optional' => true,
                'description' => 'You gain an additional Arcane Shot option of your choice when you reach 10th level.',
            ],
            [
                'name' => 'Additional Arcane Shot Option (Arcane Archer)',
                'level' => 15,
                'is_optional' => true,
                'description' => 'You gain an additional Arcane Shot option of your choice when you reach 15th level.',
            ],
            [
                'name' => 'Additional Arcane Shot Option (Arcane Archer)',
                'level' => 18,
                'is_optional' => true,
                'description' => 'You gain an additional Arcane Shot option of your choice when you reach 18th level.',
            ],
        ];

        $counters = $this->parseFeatureChoiceProgressions($features);

        $arcaneCounters = collect($counters)->where('name', 'Arcane Shots Known');
        $this->assertCount(5, $arcaneCounters);

        // Check progression: 2 -> 3 -> 4 -> 5 -> 6
        $this->assertEquals(2, $arcaneCounters->firstWhere('level', 3)['value']);
        $this->assertEquals(3, $arcaneCounters->firstWhere('level', 7)['value']);
        $this->assertEquals(4, $arcaneCounters->firstWhere('level', 10)['value']);
        $this->assertEquals(5, $arcaneCounters->firstWhere('level', 15)['value']);
        $this->assertEquals(6, $arcaneCounters->firstWhere('level', 18)['value']);

        $this->assertEquals('Arcane Archer', $arcaneCounters->first()['subclass']);
    }

    #[Test]
    public function it_parses_elemental_disciplines_with_should_know_pattern()
    {
        $features = [
            [
                'name' => 'Disciple of the Elements (Way of the Four Elements)',
                'level' => 3,
                'is_optional' => true,
                'description' => 'When you choose this tradition at 3rd level, you learn magical disciplines that harness the power of the four elements.
	You know the Elemental Attunement discipline and one other elemental discipline of your choice.',
            ],
            [
                'name' => 'Extra Elemental Discipline (Way of the Four Elements)',
                'level' => 6,
                'is_optional' => true,
                'description' => 'You learn one additional elemental discipline of your choice. You should know 2 elemental disciplines, as well as Elemental Attunement.',
            ],
            [
                'name' => 'Extra Elemental Discipline (Way of the Four Elements)',
                'level' => 11,
                'is_optional' => true,
                'description' => 'You learn one additional elemental discipline of your choice. You should know 3 elemental disciplines, as well as Elemental Attunement.',
            ],
            [
                'name' => 'Extra Elemental Discipline (Way of the Four Elements)',
                'level' => 17,
                'is_optional' => true,
                'description' => 'You learn one additional elemental discipline of your choice. You should know 4 elemental disciplines, as well as Elemental Attunement.',
            ],
        ];

        $counters = $this->parseFeatureChoiceProgressions($features);

        $disciplineCounters = collect($counters)->where('name', 'Elemental Disciplines Known');
        $this->assertCount(4, $disciplineCounters);

        // Check progression: 1 -> 2 -> 3 -> 4
        $this->assertEquals(1, $disciplineCounters->firstWhere('level', 3)['value']);
        $this->assertEquals(2, $disciplineCounters->firstWhere('level', 6)['value']);
        $this->assertEquals(3, $disciplineCounters->firstWhere('level', 11)['value']);
        $this->assertEquals(4, $disciplineCounters->firstWhere('level', 17)['value']);

        $this->assertEquals('Way of the Four Elements', $disciplineCounters->first()['subclass']);
    }

    #[Test]
    public function it_parses_fighting_style_as_single_choice()
    {
        $features = [
            [
                'name' => 'Fighting Style',
                'level' => 1,
                'is_optional' => false,
                'description' => 'You adopt a particular style of fighting as your specialty. Choose one of the following options. You can\'t take the same Fighting Style option more than once, even if you get to choose again.',
            ],
        ];

        $counters = $this->parseFeatureChoiceProgressions($features);

        $fightingStyleCounters = collect($counters)->where('name', 'Fighting Styles Known');
        $this->assertCount(1, $fightingStyleCounters);

        $this->assertEquals(1, $fightingStyleCounters->first()['value']);
        $this->assertEquals(1, $fightingStyleCounters->first()['level']);
    }

    #[Test]
    public function it_handles_champion_additional_fighting_style()
    {
        $features = [
            [
                'name' => 'Fighting Style',
                'level' => 1,
                'is_optional' => false,
                'description' => 'You adopt a particular style of fighting as your specialty. Choose one of the following options.',
            ],
            [
                'name' => 'Additional Fighting Style (Champion)',
                'level' => 10,
                'is_optional' => true,
                'description' => 'At 10th level, you can choose a second option from the Fighting Style class feature.',
            ],
        ];

        $counters = $this->parseFeatureChoiceProgressions($features);

        $fightingStyleCounters = collect($counters)->where('name', 'Fighting Styles Known')->sortBy('level');
        $this->assertCount(2, $fightingStyleCounters);

        $this->assertEquals(1, $fightingStyleCounters->firstWhere('level', 1)['value']);
        $this->assertEquals(2, $fightingStyleCounters->firstWhere('level', 10)['value']);
    }

    #[Test]
    public function it_returns_empty_array_for_features_without_choices()
    {
        $features = [
            [
                'name' => 'Extra Attack',
                'level' => 5,
                'is_optional' => false,
                'description' => 'Beginning at 5th level, you can attack twice, instead of once, whenever you take the Attack action on your turn.',
            ],
            [
                'name' => 'Indomitable',
                'level' => 9,
                'is_optional' => false,
                'description' => 'Beginning at 9th level, you can reroll a saving throw that you fail.',
            ],
        ];

        $counters = $this->parseFeatureChoiceProgressions($features);

        $this->assertEmpty($counters);
    }

    #[Test]
    public function it_extracts_subclass_name_from_feature_name_parentheses()
    {
        $features = [
            [
                'name' => 'Rune Carver (Custom Subclass)',
                'level' => 3,
                'is_optional' => true,
                'description' => 'You learn two runes of your choice.

Runes Known:
Fighter Level | Number of Runes
3rd | 2',
            ],
        ];

        $counters = $this->parseFeatureChoiceProgressions($features);

        // Should extract "Custom Subclass" from parentheses, overriding the default
        $this->assertEquals('Custom Subclass', $counters[0]['subclass'] ?? null);
    }

    #[Test]
    public function it_parses_levels_from_ordinal_format()
    {
        // Test the level extraction from "7th, 10th, and 15th"
        $levels = $this->extractLevelsFromText('at 7th, 10th, and 15th level');

        $this->assertEquals([7, 10, 15], $levels);
    }

    #[Test]
    public function it_parses_levels_from_single_ordinal()
    {
        $levels = $this->extractLevelsFromText('at 10th level');

        $this->assertEquals([10], $levels);
    }

    #[Test]
    public function it_parses_levels_with_various_ordinal_suffixes()
    {
        $levels = $this->extractLevelsFromText('at 1st, 2nd, 3rd, and 4th level');

        $this->assertEquals([1, 2, 3, 4], $levels);
    }
}
