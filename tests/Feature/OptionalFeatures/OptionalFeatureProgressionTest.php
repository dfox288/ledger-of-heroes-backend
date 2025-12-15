<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\CharacterClass;
use Tests\Traits\CreatesTestCharacters;

uses(Tests\TestCase::class, CreatesTestCharacters::class)->group('integration');

/*
|--------------------------------------------------------------------------
| Optional Feature Progression Tests
|--------------------------------------------------------------------------
|
| These tests validate that characters gain the correct number of optional
| features (invocations, metamagic, maneuvers, etc.) at each level according
| to official D&D 5e rules defined in config/dnd-rules.php.
|
| IMPORTANT: These are integration tests that run against the MySQL database
| with imported D&D data. They do NOT use RefreshDatabase because they need
| real imported classes, races, backgrounds, and optional features.
|
| Run with: ./vendor/bin/pest --group=integration
|
| Characters created during tests are cleaned up in afterEach().
|
*/

beforeEach(function () {
    // Force MySQL connection for integration tests (phpunit.xml defaults to SQLite)
    // Must hardcode values since phpunit.xml overrides env vars
    config(['database.default' => 'mysql']);
    config(['database.connections.mysql.host' => 'mysql']);
    config(['database.connections.mysql.database' => 'dnd_compendium']);
    config(['database.connections.mysql.username' => 'dnd_user']);
    config(['database.connections.mysql.password' => 'dnd_password']);

    // Purge existing connections and reconnect
    \DB::purge('mysql');
    \DB::reconnect('mysql');

    // Skip if required classes aren't imported
    $classCount = CharacterClass::count();
    if ($classCount < 10) {
        $this->markTestSkipped('Database not seeded with imported classes. Run import:all first.');
    }

    // Track characters for cleanup
    $this->createdCharacters = [];
});

afterEach(function () {
    // Clean up any characters created during tests
    if (! empty($this->createdCharacters)) {
        Character::whereIn('id', $this->createdCharacters)->delete();
    }
});

describe('Warlock Eldritch Invocations', function () {
    it('gains 2 invocations at level 2', function () {
        $character = $this->createAndLevelCharacter('phb:warlock', null, 2);

        $this->assertOptionalFeatureCount($character, 'eldritch_invocation', 2);
    });

    it('gains 3 invocations at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:warlock', null, 5);

        $this->assertOptionalFeatureCount($character, 'eldritch_invocation', 3);
    });

    it('gains 5 invocations at level 9', function () {
        $character = $this->createAndLevelCharacter('phb:warlock', null, 9);

        $this->assertOptionalFeatureCount($character, 'eldritch_invocation', 5);
    });

    it('gains 8 invocations at level 18', function () {
        $character = $this->createAndLevelCharacter('phb:warlock', null, 18);

        $this->assertOptionalFeatureCount($character, 'eldritch_invocation', 8);
    });

    it('gains 8 invocations at level 20', function () {
        $character = $this->createAndLevelCharacter('phb:warlock', null, 20);

        $this->assertOptionalFeatureCount($character, 'eldritch_invocation', 8);
    });
});

describe('Sorcerer Metamagic', function () {
    it('gains 2 metamagic at level 3', function () {
        $character = $this->createAndLevelCharacter('phb:sorcerer', null, 3);

        $this->assertOptionalFeatureCount($character, 'metamagic', 2);
    });

    it('gains 3 metamagic at level 10', function () {
        $character = $this->createAndLevelCharacter('phb:sorcerer', null, 10);

        $this->assertOptionalFeatureCount($character, 'metamagic', 3);
    });

    it('gains 4 metamagic at level 17', function () {
        $character = $this->createAndLevelCharacter('phb:sorcerer', null, 17);

        $this->assertOptionalFeatureCount($character, 'metamagic', 4);
    });

    it('gains 4 metamagic at level 20', function () {
        $character = $this->createAndLevelCharacter('phb:sorcerer', null, 20);

        $this->assertOptionalFeatureCount($character, 'metamagic', 4);
    });
});

describe('Artificer Infusions', function () {
    it('gains 4 infusions at level 2', function () {
        $character = $this->createAndLevelCharacter('erlw:artificer', null, 2);

        $this->assertOptionalFeatureCount($character, 'artificer_infusion', 4);
    });

    it('gains 6 infusions at level 6', function () {
        $character = $this->createAndLevelCharacter('erlw:artificer', null, 6);

        $this->assertOptionalFeatureCount($character, 'artificer_infusion', 6);
    });

    it('gains 8 infusions at level 10', function () {
        $character = $this->createAndLevelCharacter('erlw:artificer', null, 10);

        $this->assertOptionalFeatureCount($character, 'artificer_infusion', 8);
    });

    it('gains 12 infusions at level 18', function () {
        $character = $this->createAndLevelCharacter('erlw:artificer', null, 18);

        $this->assertOptionalFeatureCount($character, 'artificer_infusion', 12);
    });

    it('gains 12 infusions at level 20', function () {
        $character = $this->createAndLevelCharacter('erlw:artificer', null, 20);

        $this->assertOptionalFeatureCount($character, 'artificer_infusion', 12);
    });
});

describe('Fighter Battle Master Maneuvers', function () {
    it('gains 3 maneuvers at level 3', function () {
        $character = $this->createAndLevelCharacter(
            'phb:fighter',
            'phb:fighter-battle-master',
            3
        );

        $this->assertOptionalFeatureCount($character, 'maneuver', 3);
    });

    it('gains 5 maneuvers at level 7', function () {
        $character = $this->createAndLevelCharacter(
            'phb:fighter',
            'phb:fighter-battle-master',
            7
        );

        $this->assertOptionalFeatureCount($character, 'maneuver', 5);
    });

    it('gains 7 maneuvers at level 10', function () {
        $character = $this->createAndLevelCharacter(
            'phb:fighter',
            'phb:fighter-battle-master',
            10
        );

        $this->assertOptionalFeatureCount($character, 'maneuver', 7);
    });

    it('gains 9 maneuvers at level 15', function () {
        $character = $this->createAndLevelCharacter(
            'phb:fighter',
            'phb:fighter-battle-master',
            15
        );

        $this->assertOptionalFeatureCount($character, 'maneuver', 9);
    });

    it('gains 9 maneuvers at level 20', function () {
        $character = $this->createAndLevelCharacter(
            'phb:fighter',
            'phb:fighter-battle-master',
            20
        );

        $this->assertOptionalFeatureCount($character, 'maneuver', 9);
    });
});

describe('Fighter Arcane Archer Arcane Shots', function () {
    it('gains 2 arcane shots at level 3', function () {
        $character = $this->createAndLevelCharacter(
            'phb:fighter',
            'xge:fighter-arcane-archer',
            3
        );

        $this->assertOptionalFeatureCount($character, 'arcane_shot', 2);
    });

    it('gains 4 arcane shots at level 10', function () {
        $character = $this->createAndLevelCharacter(
            'phb:fighter',
            'xge:fighter-arcane-archer',
            10
        );

        $this->assertOptionalFeatureCount($character, 'arcane_shot', 4);
    });

    it('gains 6 arcane shots at level 18', function () {
        $character = $this->createAndLevelCharacter(
            'phb:fighter',
            'xge:fighter-arcane-archer',
            18
        );

        $this->assertOptionalFeatureCount($character, 'arcane_shot', 6);
    });

    it('gains 6 arcane shots at level 20', function () {
        $character = $this->createAndLevelCharacter(
            'phb:fighter',
            'xge:fighter-arcane-archer',
            20
        );

        $this->assertOptionalFeatureCount($character, 'arcane_shot', 6);
    });
});

describe('Fighter Rune Knight Runes', function () {
    it('gains 2 runes at level 3', function () {
        $character = $this->createAndLevelCharacter(
            'phb:fighter',
            'tce:fighter-rune-knight',
            3
        );

        $this->assertOptionalFeatureCount($character, 'rune', 2);
    });

    it('gains 4 runes at level 10', function () {
        $character = $this->createAndLevelCharacter(
            'phb:fighter',
            'tce:fighter-rune-knight',
            10
        );

        $this->assertOptionalFeatureCount($character, 'rune', 4);
    });

    it('gains 5 runes at level 15', function () {
        $character = $this->createAndLevelCharacter(
            'phb:fighter',
            'tce:fighter-rune-knight',
            15
        );

        $this->assertOptionalFeatureCount($character, 'rune', 5);
    });

    it('gains 5 runes at level 20', function () {
        $character = $this->createAndLevelCharacter(
            'phb:fighter',
            'tce:fighter-rune-knight',
            20
        );

        $this->assertOptionalFeatureCount($character, 'rune', 5);
    });
});

describe('Monk Way of the Four Elements Disciplines', function () {
    it('gains 1 discipline at level 3', function () {
        $character = $this->createAndLevelCharacter(
            'phb:monk',
            'phb:monk-way-of-the-four-elements',
            3
        );

        $this->assertOptionalFeatureCount($character, 'elemental_discipline', 1);
    });

    it('gains 2 disciplines at level 6', function () {
        $character = $this->createAndLevelCharacter(
            'phb:monk',
            'phb:monk-way-of-the-four-elements',
            6
        );

        $this->assertOptionalFeatureCount($character, 'elemental_discipline', 2);
    });

    it('gains 3 disciplines at level 11', function () {
        $character = $this->createAndLevelCharacter(
            'phb:monk',
            'phb:monk-way-of-the-four-elements',
            11
        );

        $this->assertOptionalFeatureCount($character, 'elemental_discipline', 3);
    });

    it('gains 4 disciplines at level 17', function () {
        $character = $this->createAndLevelCharacter(
            'phb:monk',
            'phb:monk-way-of-the-four-elements',
            17
        );

        $this->assertOptionalFeatureCount($character, 'elemental_discipline', 4);
    });

    it('gains 4 disciplines at level 20', function () {
        $character = $this->createAndLevelCharacter(
            'phb:monk',
            'phb:monk-way-of-the-four-elements',
            20
        );

        $this->assertOptionalFeatureCount($character, 'elemental_discipline', 4);
    });
});
