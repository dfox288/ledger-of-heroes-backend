<?php

/**
 * Official D&D 5e Rules Reference
 *
 * This config defines official progression tables for optional features,
 * spellcasting, and other class mechanics. Used for:
 * - Data audit: Compare database counters against official rules
 * - Test validation: Verify level-up flow generates correct choices
 *
 * Sources: PHB, XGE, TCE, ERLW
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Optional Feature Progressions
    |--------------------------------------------------------------------------
    |
    | Each entry defines when a class/subclass gains optional features.
    | 'progression' maps level => total features known at that level.
    |
    */

    'optional_features' => [
        'eldritch_invocation' => [
            'name' => 'Eldritch Invocations',
            'class' => 'phb:warlock',
            'subclass' => null,
            'counter_names' => ['Eldritch Invocations Known', 'Eldritch Invocations'],
            'progression' => [
                2 => 2,
                5 => 3,
                7 => 4,
                9 => 5,
                12 => 6,
                15 => 7,
                18 => 8,
            ],
        ],

        'metamagic' => [
            'name' => 'Metamagic Options',
            'class' => 'phb:sorcerer',
            'subclass' => null,
            'counter_names' => ['Metamagic Known'],
            'progression' => [
                3 => 2,
                10 => 3,
                17 => 4,
            ],
        ],

        'artificer_infusion' => [
            'name' => 'Artificer Infusions',
            'class' => 'erlw:artificer',
            'subclass' => null,
            'counter_names' => ['Infusions Known'],
            'progression' => [
                2 => 4,
                6 => 6,
                10 => 8,
                14 => 10,
                18 => 12,
            ],
        ],

        'maneuver' => [
            'name' => 'Battle Master Maneuvers',
            'class' => 'phb:fighter',
            'subclass' => 'phb:fighter-battle-master',
            'counter_names' => ['Maneuvers Known'],
            'progression' => [
                3 => 3,
                7 => 5,
                10 => 7,
                15 => 9,
            ],
        ],

        'arcane_shot' => [
            'name' => 'Arcane Shot Options',
            'class' => 'phb:fighter',
            'subclass' => 'xge:fighter-arcane-archer',
            'counter_names' => ['Arcane Shots Known'],
            'progression' => [
                3 => 2,
                7 => 3,
                10 => 4,
                15 => 5,
                18 => 6,
            ],
        ],

        'rune' => [
            'name' => 'Rune Knight Runes',
            'class' => 'phb:fighter',
            'subclass' => 'tce:fighter-rune-knight',
            'counter_names' => ['Runes Known'],
            'progression' => [
                3 => 2,
                7 => 3,
                10 => 4,
                15 => 5,
            ],
        ],

        'elemental_discipline' => [
            'name' => 'Elemental Disciplines',
            'class' => 'phb:monk',
            'subclass' => 'phb:monk-way-of-the-four-elements',
            'counter_names' => ['Elemental Disciplines Known'],
            'progression' => [
                3 => 1,
                6 => 2,
                11 => 3,
                17 => 4,
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Fighting Styles
        |--------------------------------------------------------------------------
        |
        | Fighting styles are gained by multiple classes at different levels.
        | Some subclasses also grant additional fighting styles.
        |
        */

        'fighting_style_fighter' => [
            'name' => 'Fighter Fighting Styles',
            'class' => 'phb:fighter',
            'subclass' => null,
            'counter_names' => ['Fighting Styles Known'],
            'progression' => [
                1 => 1,
                // Champion gets additional at 10
            ],
        ],

        'fighting_style_champion' => [
            'name' => 'Champion Fighting Styles',
            'class' => 'phb:fighter',
            'subclass' => 'phb:fighter-champion',
            'counter_names' => ['Fighting Styles Known'],
            'progression' => [
                10 => 2,
            ],
        ],

        'fighting_style_paladin' => [
            'name' => 'Paladin Fighting Styles',
            'class' => 'phb:paladin',
            'subclass' => null,
            'counter_names' => ['Fighting Styles Known'],
            'progression' => [
                2 => 1,
            ],
        ],

        'fighting_style_ranger' => [
            'name' => 'Ranger Fighting Styles',
            'class' => 'phb:ranger',
            'subclass' => null,
            'counter_names' => ['Fighting Styles Known'],
            'progression' => [
                2 => 1,
            ],
        ],

        'fighting_style_college_of_swords' => [
            'name' => 'College of Swords Fighting Style',
            'class' => 'phb:bard',
            'subclass' => 'xge:bard-college-of-swords',
            'counter_names' => ['Fighting Styles Known'],
            'progression' => [
                3 => 1,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Spellcasting Progressions
    |--------------------------------------------------------------------------
    |
    | Defines spell slots, cantrips known, and spells known by level.
    | Used for validating spellcaster character progression.
    |
    */

    'spellcasting' => [
        // Full casters
        'wizard' => [
            'type' => 'spellbook',
            'class' => 'phb:wizard',
            'cantrips' => [
                1 => 3, 4 => 4, 10 => 5,
            ],
            'spells_known' => [
                // Wizards use spellbook: 6 at L1, +2 per level
                1 => 6, 2 => 8, 3 => 10, 4 => 12, 5 => 14,
                6 => 16, 7 => 18, 8 => 20, 9 => 22, 10 => 24,
                11 => 26, 12 => 28, 13 => 30, 14 => 32, 15 => 34,
                16 => 36, 17 => 38, 18 => 40, 19 => 42, 20 => 44,
            ],
        ],

        'sorcerer' => [
            'type' => 'known',
            'class' => 'phb:sorcerer',
            'cantrips' => [
                1 => 4, 4 => 5, 10 => 6,
            ],
            'spells_known' => [
                1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 6,
                6 => 7, 7 => 8, 8 => 9, 9 => 10, 10 => 11,
                11 => 12, 13 => 13, 15 => 14, 17 => 15,
            ],
        ],

        'bard' => [
            'type' => 'known',
            'class' => 'phb:bard',
            'cantrips' => [
                1 => 2, 4 => 3, 10 => 4,
            ],
            'spells_known' => [
                1 => 4, 2 => 5, 3 => 6, 4 => 7, 5 => 8,
                6 => 9, 7 => 10, 8 => 11, 9 => 12, 10 => 14,
                11 => 15, 13 => 16, 15 => 18, 17 => 19, 18 => 20,
                19 => 21, 20 => 22,
            ],
        ],

        'cleric' => [
            'type' => 'prepared',
            'class' => 'phb:cleric',
            'cantrips' => [
                1 => 3, 4 => 4, 10 => 5,
            ],
            // Prepared casters: WIS mod + cleric level
        ],

        'druid' => [
            'type' => 'prepared',
            'class' => 'phb:druid',
            'cantrips' => [
                1 => 2, 4 => 3, 10 => 4,
            ],
            // Prepared casters: WIS mod + druid level
        ],

        'warlock' => [
            'type' => 'known',
            'class' => 'phb:warlock',
            'cantrips' => [
                1 => 2, 4 => 3, 10 => 4,
            ],
            'spells_known' => [
                1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 6,
                6 => 7, 7 => 8, 8 => 9, 9 => 10, 11 => 11,
                13 => 12, 15 => 13, 17 => 14, 19 => 15,
            ],
        ],

        // Half casters
        'paladin' => [
            'type' => 'prepared',
            'class' => 'phb:paladin',
            // No cantrips
            // Prepared: CHA mod + half paladin level
        ],

        'ranger' => [
            'type' => 'known',
            'class' => 'phb:ranger',
            // No cantrips (base class)
            'spells_known' => [
                2 => 2, 3 => 3, 5 => 4, 7 => 5, 9 => 6,
                11 => 7, 13 => 8, 15 => 9, 17 => 10, 19 => 11,
            ],
        ],

        'artificer' => [
            'type' => 'prepared',
            'class' => 'erlw:artificer',
            'cantrips' => [
                1 => 2, 10 => 3, 14 => 4,
            ],
            // Prepared: INT mod + half artificer level
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Spell Slot Progressions
    |--------------------------------------------------------------------------
    |
    | Standard spell slot tables for full, half, and third casters.
    |
    */

    'spell_slots' => [
        'full_caster' => [
            // Level => [1st, 2nd, 3rd, 4th, 5th, 6th, 7th, 8th, 9th]
            1 => [2, 0, 0, 0, 0, 0, 0, 0, 0],
            2 => [3, 0, 0, 0, 0, 0, 0, 0, 0],
            3 => [4, 2, 0, 0, 0, 0, 0, 0, 0],
            4 => [4, 3, 0, 0, 0, 0, 0, 0, 0],
            5 => [4, 3, 2, 0, 0, 0, 0, 0, 0],
            6 => [4, 3, 3, 0, 0, 0, 0, 0, 0],
            7 => [4, 3, 3, 1, 0, 0, 0, 0, 0],
            8 => [4, 3, 3, 2, 0, 0, 0, 0, 0],
            9 => [4, 3, 3, 3, 1, 0, 0, 0, 0],
            10 => [4, 3, 3, 3, 2, 0, 0, 0, 0],
            11 => [4, 3, 3, 3, 2, 1, 0, 0, 0],
            12 => [4, 3, 3, 3, 2, 1, 0, 0, 0],
            13 => [4, 3, 3, 3, 2, 1, 1, 0, 0],
            14 => [4, 3, 3, 3, 2, 1, 1, 0, 0],
            15 => [4, 3, 3, 3, 2, 1, 1, 1, 0],
            16 => [4, 3, 3, 3, 2, 1, 1, 1, 0],
            17 => [4, 3, 3, 3, 2, 1, 1, 1, 1],
            18 => [4, 3, 3, 3, 3, 1, 1, 1, 1],
            19 => [4, 3, 3, 3, 3, 2, 1, 1, 1],
            20 => [4, 3, 3, 3, 3, 2, 2, 1, 1],
        ],

        'half_caster' => [
            // Paladin, Ranger (start at level 2)
            2 => [2, 0, 0, 0, 0],
            3 => [3, 0, 0, 0, 0],
            4 => [3, 0, 0, 0, 0],
            5 => [4, 2, 0, 0, 0],
            6 => [4, 2, 0, 0, 0],
            7 => [4, 3, 0, 0, 0],
            8 => [4, 3, 0, 0, 0],
            9 => [4, 3, 2, 0, 0],
            10 => [4, 3, 2, 0, 0],
            11 => [4, 3, 3, 0, 0],
            12 => [4, 3, 3, 0, 0],
            13 => [4, 3, 3, 1, 0],
            14 => [4, 3, 3, 1, 0],
            15 => [4, 3, 3, 2, 0],
            16 => [4, 3, 3, 2, 0],
            17 => [4, 3, 3, 3, 1],
            18 => [4, 3, 3, 3, 1],
            19 => [4, 3, 3, 3, 2],
            20 => [4, 3, 3, 3, 2],
        ],

        'warlock' => [
            // Pact Magic: fewer slots, all same level, recharge on short rest
            1 => ['slots' => 1, 'level' => 1],
            2 => ['slots' => 2, 'level' => 1],
            3 => ['slots' => 2, 'level' => 2],
            4 => ['slots' => 2, 'level' => 2],
            5 => ['slots' => 2, 'level' => 3],
            6 => ['slots' => 2, 'level' => 3],
            7 => ['slots' => 2, 'level' => 4],
            8 => ['slots' => 2, 'level' => 4],
            9 => ['slots' => 2, 'level' => 5],
            10 => ['slots' => 2, 'level' => 5],
            11 => ['slots' => 3, 'level' => 5],
            12 => ['slots' => 3, 'level' => 5],
            13 => ['slots' => 3, 'level' => 5],
            14 => ['slots' => 3, 'level' => 5],
            15 => ['slots' => 3, 'level' => 5],
            16 => ['slots' => 3, 'level' => 5],
            17 => ['slots' => 4, 'level' => 5],
            18 => ['slots' => 4, 'level' => 5],
            19 => ['slots' => 4, 'level' => 5],
            20 => ['slots' => 4, 'level' => 5],
        ],

        'artificer' => [
            // Half caster, rounded up (unique to Artificer)
            1 => [2, 0, 0, 0, 0],
            2 => [2, 0, 0, 0, 0],
            3 => [3, 0, 0, 0, 0],
            4 => [3, 0, 0, 0, 0],
            5 => [4, 2, 0, 0, 0],
            6 => [4, 2, 0, 0, 0],
            7 => [4, 3, 0, 0, 0],
            8 => [4, 3, 0, 0, 0],
            9 => [4, 3, 2, 0, 0],
            10 => [4, 3, 2, 0, 0],
            11 => [4, 3, 3, 0, 0],
            12 => [4, 3, 3, 0, 0],
            13 => [4, 3, 3, 1, 0],
            14 => [4, 3, 3, 1, 0],
            15 => [4, 3, 3, 2, 0],
            16 => [4, 3, 3, 2, 0],
            17 => [4, 3, 3, 3, 1],
            18 => [4, 3, 3, 3, 1],
            19 => [4, 3, 3, 3, 2],
            20 => [4, 3, 3, 3, 2],
        ],
    ],
];
