<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\CharacterClass;
use Tests\Traits\CreatesTestCharacters;

uses(Tests\TestCase::class, CreatesTestCharacters::class)->group('integration');

/*
|--------------------------------------------------------------------------
| Spellcaster Progression Tests
|--------------------------------------------------------------------------
|
| These tests validate that spellcaster characters gain the correct number
| of cantrips, spells known, and spell slots at each level according to
| official D&D 5e rules defined in config/dnd-rules.php.
|
| IMPORTANT: These are integration tests that run against the MySQL database
| with imported D&D data. They do NOT use RefreshDatabase because they need
| real imported classes, races, backgrounds, and spells.
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

// ============================================================================
// Sorcerer (Known Caster)
// ============================================================================

describe('Sorcerer Spellcasting', function () {
    it('has correct cantrips at level 1', function () {
        $character = $this->createAndLevelCharacter('phb:sorcerer', null, 1);

        $this->assertCantripCount($character, 4);
    });

    it('has correct spells known at level 1', function () {
        $character = $this->createAndLevelCharacter('phb:sorcerer', null, 1);

        $this->assertSpellsKnownCount($character, 2);
    });

    it('has correct cantrips at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:sorcerer', null, 5);

        $this->assertCantripCount($character, 5);
    });

    it('has correct spells known at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:sorcerer', null, 5);

        $this->assertSpellsKnownCount($character, 6);
    });

    it('has correct cantrips at level 10', function () {
        $character = $this->createAndLevelCharacter('phb:sorcerer', null, 10);

        $this->assertCantripCount($character, 6);
    });

    it('has correct spells known at level 10', function () {
        $character = $this->createAndLevelCharacter('phb:sorcerer', null, 10);

        $this->assertSpellsKnownCount($character, 11);
    });

    it('has correct spell slots at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:sorcerer', null, 5);

        $this->assertSpellSlots($character, [1 => 4, 2 => 3, 3 => 2]);
    });
});

// ============================================================================
// Bard (Known Caster)
// ============================================================================

describe('Bard Spellcasting', function () {
    it('has correct cantrips at level 1', function () {
        $character = $this->createAndLevelCharacter('phb:bard', null, 1);

        $this->assertCantripCount($character, 2);
    });

    it('has correct spells known at level 1', function () {
        $character = $this->createAndLevelCharacter('phb:bard', null, 1);

        $this->assertSpellsKnownCount($character, 4);
    });

    it('has correct cantrips at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:bard', null, 5);

        $this->assertCantripCount($character, 3);
    });

    it('has correct spells known at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:bard', null, 5);

        $this->assertSpellsKnownCount($character, 8);
    });

    it('has correct spell slots at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:bard', null, 5);

        $this->assertSpellSlots($character, [1 => 4, 2 => 3, 3 => 2]);
    });
});

// ============================================================================
// Warlock (Known Caster - Pact Magic)
// ============================================================================

describe('Warlock Spellcasting', function () {
    it('has correct cantrips at level 1', function () {
        $character = $this->createAndLevelCharacter('phb:warlock', null, 1);

        $this->assertCantripCount($character, 2);
    });

    it('has correct spells known at level 1', function () {
        $character = $this->createAndLevelCharacter('phb:warlock', null, 1);

        $this->assertSpellsKnownCount($character, 2);
    });

    it('has correct cantrips at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:warlock', null, 5);

        $this->assertCantripCount($character, 3);
    });

    it('has correct spells known at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:warlock', null, 5);

        $this->assertSpellsKnownCount($character, 6);
    });

    // Warlock has unique Pact Magic slots
    it('has correct spell slots at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:warlock', null, 5);

        // Warlock at L5 has 2 3rd-level slots
        $this->assertSpellSlots($character, [3 => 2]);
    });

    it('has correct spell slots at level 11', function () {
        $character = $this->createAndLevelCharacter('phb:warlock', null, 11);

        // Warlock at L11 has 3 5th-level slots
        $this->assertSpellSlots($character, [5 => 3]);
    });
});

// ============================================================================
// Wizard (Spellbook Caster)
// ============================================================================

describe('Wizard Spellcasting', function () {
    it('has correct cantrips at level 1', function () {
        $character = $this->createAndLevelCharacter('phb:wizard', null, 1);

        $this->assertCantripCount($character, 3);
    });

    it('has correct spells known at level 1', function () {
        $character = $this->createAndLevelCharacter('phb:wizard', null, 1);

        // Wizard starts with 6 spells in spellbook
        $this->assertSpellsKnownCount($character, 6);
    });

    it('has correct cantrips at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:wizard', null, 5);

        $this->assertCantripCount($character, 4);
    });

    it('has correct spells known at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:wizard', null, 5);

        // 6 + (4 * 2) = 14 spells in spellbook at level 5
        $this->assertSpellsKnownCount($character, 14);
    });

    it('has correct spell slots at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:wizard', null, 5);

        $this->assertSpellSlots($character, [1 => 4, 2 => 3, 3 => 2]);
    });
});

// ============================================================================
// Cleric (Prepared Caster)
// ============================================================================

describe('Cleric Spellcasting', function () {
    it('has correct cantrips at level 1', function () {
        $character = $this->createAndLevelCharacter('phb:cleric', null, 1);

        $this->assertCantripCount($character, 3);
    });

    it('has correct cantrips at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:cleric', null, 5);

        $this->assertCantripCount($character, 4);
    });

    it('has correct spell slots at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:cleric', null, 5);

        $this->assertSpellSlots($character, [1 => 4, 2 => 3, 3 => 2]);
    });

    // Prepared casters don't have fixed spells known
    // They prepare from their full spell list
});

// ============================================================================
// Druid (Prepared Caster)
// ============================================================================

describe('Druid Spellcasting', function () {
    it('has correct cantrips at level 1', function () {
        $character = $this->createAndLevelCharacter('phb:druid', null, 1);

        $this->assertCantripCount($character, 2);
    });

    it('has correct cantrips at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:druid', null, 5);

        $this->assertCantripCount($character, 3);
    });

    it('has correct spell slots at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:druid', null, 5);

        $this->assertSpellSlots($character, [1 => 4, 2 => 3, 3 => 2]);
    });
});

// ============================================================================
// Ranger (Half Caster - Known)
// ============================================================================

describe('Ranger Spellcasting', function () {
    it('has correct spells known at level 2', function () {
        $character = $this->createAndLevelCharacter('phb:ranger', null, 2);

        $this->assertSpellsKnownCount($character, 2);
    });

    it('has correct spells known at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:ranger', null, 5);

        $this->assertSpellsKnownCount($character, 4);
    });

    it('has correct spell slots at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:ranger', null, 5);

        // Half caster at L5
        $this->assertSpellSlots($character, [1 => 4, 2 => 2]);
    });

    // Rangers don't get cantrips by default
});

// ============================================================================
// Paladin (Half Caster - Prepared)
// ============================================================================

describe('Paladin Spellcasting', function () {
    it('has correct spell slots at level 2', function () {
        $character = $this->createAndLevelCharacter('phb:paladin', null, 2);

        $this->assertSpellSlots($character, [1 => 2]);
    });

    it('has correct spell slots at level 5', function () {
        $character = $this->createAndLevelCharacter('phb:paladin', null, 5);

        $this->assertSpellSlots($character, [1 => 4, 2 => 2]);
    });

    // Paladins don't get cantrips
    // Paladins prepare spells (no fixed spells known)
});

// ============================================================================
// Artificer (Half Caster - Prepared, Unique Progression)
// ============================================================================

describe('Artificer Spellcasting', function () {
    it('has correct cantrips at level 1', function () {
        $character = $this->createAndLevelCharacter('erlw:artificer', null, 1);

        $this->assertCantripCount($character, 2);
    });

    it('has correct cantrips at level 10', function () {
        $character = $this->createAndLevelCharacter('erlw:artificer', null, 10);

        $this->assertCantripCount($character, 3);
    });

    it('has correct spell slots at level 5', function () {
        $character = $this->createAndLevelCharacter('erlw:artificer', null, 5);

        // Artificer rounds UP for half caster calculation
        $this->assertSpellSlots($character, [1 => 4, 2 => 2]);
    });
});
