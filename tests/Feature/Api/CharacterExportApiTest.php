<?php

use App\Enums\NoteCategory;
use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterCondition;
use App\Models\CharacterEquipment;
use App\Models\CharacterLanguage;
use App\Models\CharacterNote;
use App\Models\CharacterProficiency;
use App\Models\CharacterSpell;
use App\Models\Condition;
use App\Models\FeatureSelection;
use App\Models\Item;
use App\Models\Language;
use App\Models\OptionalFeature;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Models\Skill;
use App\Models\Spell;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Character Export', function () {
    it('exports a complete character as portable JSON', function () {
        $race = Race::factory()->create(['slug' => 'phb:human', 'name' => 'Human']);
        $background = Background::factory()->create(['slug' => 'phb:sage', 'name' => 'Sage']);
        $class = CharacterClass::factory()->create(['slug' => 'phb:wizard', 'name' => 'Wizard']);
        $subclass = CharacterClass::factory()->create(['slug' => 'phb:school-of-evocation', 'name' => 'School of Evocation']);

        $character = Character::factory()->create([
            'public_id' => 'brave-wizard-x7k2',
            'name' => 'Gandalf',
            'race_slug' => 'phb:human',
            'background_slug' => 'phb:sage',
            'alignment' => 'Neutral Good',
            'strength' => 10,
            'dexterity' => 14,
            'constitution' => 12,
            'intelligence' => 20,
            'wisdom' => 16,
            'charisma' => 14,
        ]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => 'phb:wizard',
            'subclass_slug' => 'phb:school-of-evocation',
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/export");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'format_version',
                    'exported_at',
                    'character' => [
                        'public_id',
                        'name',
                        'race',
                        'background',
                        'alignment',
                        'ability_scores',
                        'classes',
                    ],
                ],
            ]);

        $data = $response->json('data');
        expect($data['format_version'])->toBe('1.0')
            ->and($data['character']['public_id'])->toBe('brave-wizard-x7k2')
            ->and($data['character']['name'])->toBe('Gandalf')
            ->and($data['character']['race'])->toBe('phb:human')
            ->and($data['character']['background'])->toBe('phb:sage')
            ->and($data['character']['ability_scores']['strength'])->toBe(10)
            ->and($data['character']['classes'][0]['class'])->toBe('phb:wizard')
            ->and($data['character']['classes'][0]['subclass'])->toBe('phb:school-of-evocation')
            ->and($data['character']['classes'][0]['level'])->toBe(5);
    });

    it('exports character spells with preparation status', function () {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create(['slug' => 'phb:fireball']);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => 'phb:fireball',
            'preparation_status' => 'prepared',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/export");

        $response->assertOk();
        $spells = $response->json('data.character.spells');
        expect($spells)->toHaveCount(1)
            ->and($spells[0]['spell'])->toBe('phb:fireball')
            ->and($spells[0]['preparation_status'])->toBe('prepared')
            ->and($spells[0]['source'])->toBe('class');
    });

    it('exports character equipment including custom items', function () {
        $character = Character::factory()->create();
        $item = Item::factory()->create(['slug' => 'dmg:staff-of-power', 'name' => 'Staff of Power']);

        // Standard item
        CharacterEquipment::factory()->create([
            'character_id' => $character->id,
            'item_slug' => 'dmg:staff-of-power',
            'equipped' => true,
            'quantity' => 1,
        ]);

        // Custom item (no item_slug)
        CharacterEquipment::factory()->create([
            'character_id' => $character->id,
            'item_slug' => null,
            'custom_name' => 'Pipe of Gandalf',
            'custom_description' => "A wizard's pipe",
            'quantity' => 1,
            'equipped' => false,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/export");

        $response->assertOk();
        $equipment = $response->json('data.character.equipment');
        expect($equipment)->toHaveCount(2);

        // Standard item
        $standardItem = collect($equipment)->firstWhere('item', 'dmg:staff-of-power');
        expect($standardItem)->not->toBeNull()
            ->and($standardItem['equipped'])->toBeTrue()
            ->and($standardItem['quantity'])->toBe(1);

        // Custom item
        $customItem = collect($equipment)->firstWhere('custom_name', 'Pipe of Gandalf');
        expect($customItem)->not->toBeNull()
            ->and($customItem['custom_description'])->toBe("A wizard's pipe")
            ->and($customItem['item'])->toBeNull();
    });

    it('exports character languages with source', function () {
        $character = Character::factory()->create();
        // Use seeded language (core:common from LanguageSeeder)
        $language = Language::where('slug', 'core:common')->first();
        expect($language)->not->toBeNull('Common language should be seeded');

        CharacterLanguage::factory()->create([
            'character_id' => $character->id,
            'language_slug' => 'core:common',
            'source' => 'race',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/export");

        $response->assertOk();
        $languages = $response->json('data.character.languages');
        expect($languages)->toHaveCount(1)
            ->and($languages[0]['language'])->toBe('core:common')
            ->and($languages[0]['source'])->toBe('race');
    });

    it('exports character proficiencies', function () {
        $character = Character::factory()->create();
        // Use seeded skill (core:arcana from SkillSeeder)
        $skill = Skill::where('slug', 'core:arcana')->first();
        expect($skill)->not->toBeNull('Arcana skill should be seeded');

        // Use seeded proficiency type
        $profType = ProficiencyType::first();
        expect($profType)->not->toBeNull('Proficiency types should be seeded');

        // Skill proficiency
        CharacterProficiency::factory()->create([
            'character_id' => $character->id,
            'skill_slug' => 'core:arcana',
            'proficiency_type_slug' => null,
            'source' => 'class',
            'expertise' => false,
        ]);

        // Type proficiency
        CharacterProficiency::factory()->create([
            'character_id' => $character->id,
            'skill_slug' => null,
            'proficiency_type_slug' => $profType->slug,
            'source' => 'class',
            'expertise' => false,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/export");

        $response->assertOk();
        $proficiencies = $response->json('data.character.proficiencies');

        expect($proficiencies['skills'])->toHaveCount(1)
            ->and($proficiencies['skills'][0]['skill'])->toBe('core:arcana')
            ->and($proficiencies['types'])->toHaveCount(1)
            ->and($proficiencies['types'][0]['type'])->toBe($profType->slug);
    });

    it('exports character conditions', function () {
        $character = Character::factory()->create();
        $condition = Condition::factory()->create(['slug' => 'phb:frightened', 'name' => 'Frightened']);

        CharacterCondition::factory()->create([
            'character_id' => $character->id,
            'condition_slug' => 'phb:frightened',
            'level' => 1,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/export");

        $response->assertOk();
        $conditions = $response->json('data.character.conditions');
        expect($conditions)->toHaveCount(1)
            ->and($conditions[0]['condition'])->toBe('phb:frightened');
    });

    it('exports character notes', function () {
        $character = Character::factory()->create();

        CharacterNote::factory()->create([
            'character_id' => $character->id,
            'category' => NoteCategory::Backstory,
            'title' => 'Origins',
            'content' => 'Born in the Shire...',
            'sort_order' => 1,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/export");

        $response->assertOk();
        $notes = $response->json('data.character.notes');
        expect($notes)->toHaveCount(1)
            ->and($notes[0]['category'])->toBe('backstory')
            ->and($notes[0]['title'])->toBe('Origins')
            ->and($notes[0]['content'])->toBe('Born in the Shire...');
    });

    it('exports feature selections', function () {
        $character = Character::factory()->create();
        $class = CharacterClass::factory()->create(['slug' => 'phb:warlock', 'name' => 'Warlock']);
        $feature = OptionalFeature::factory()->create(['slug' => 'xge:eldritch-smite', 'name' => 'Eldritch Smite']);

        FeatureSelection::factory()->create([
            'character_id' => $character->id,
            'optional_feature_slug' => 'xge:eldritch-smite',
            'class_slug' => 'phb:warlock',
            'level_acquired' => 5,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/export");

        $response->assertOk();
        $features = $response->json('data.character.feature_selections');
        expect($features)->toHaveCount(1)
            ->and($features[0]['feature'])->toBe('xge:eldritch-smite')
            ->and($features[0]['class'])->toBe('phb:warlock');
    });

    it('returns 404 for nonexistent character', function () {
        $response = $this->getJson('/api/v1/characters/nonexistent/export');

        $response->assertNotFound();
    });

    it('includes hit points and combat stats in export', function () {
        $character = Character::factory()->create([
            'max_hit_points' => 45,
            'current_hit_points' => 30,
            'temp_hit_points' => 5,
            'death_save_successes' => 1,
            'death_save_failures' => 2,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/export");

        $response->assertOk();
        $stats = $response->json('data.character');
        expect($stats['max_hit_points'])->toBe(45)
            ->and($stats['current_hit_points'])->toBe(30)
            ->and($stats['temp_hit_points'])->toBe(5)
            ->and($stats['death_save_successes'])->toBe(1)
            ->and($stats['death_save_failures'])->toBe(2);
    });
});

describe('Character Import', function () {
    it('imports a character from valid export JSON', function () {
        $user = User::factory()->create();

        $race = Race::factory()->create(['slug' => 'phb:human']);
        $background = Background::factory()->create(['slug' => 'phb:sage']);
        $class = CharacterClass::factory()->create(['slug' => 'phb:wizard']);

        $exportData = [
            'format_version' => '1.0',
            'character' => [
                'public_id' => 'imported-wizard-abc1',
                'name' => 'Imported Wizard',
                'race' => 'phb:human',
                'background' => 'phb:sage',
                'alignment' => 'Lawful Good',
                'ability_scores' => [
                    'strength' => 10,
                    'dexterity' => 14,
                    'constitution' => 12,
                    'intelligence' => 18,
                    'wisdom' => 15,
                    'charisma' => 8,
                ],
                'classes' => [
                    ['class' => 'phb:wizard', 'subclass' => null, 'level' => 3, 'is_primary' => true],
                ],
                'spells' => [],
                'equipment' => [],
                'languages' => [],
                'proficiencies' => ['skills' => [], 'types' => []],
                'conditions' => [],
                'feature_selections' => [],
                'notes' => [],
            ],
        ];

        $response = $this->postJson('/api/v1/characters/import', $exportData);

        $response->assertCreated()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.character.name', 'Imported Wizard')
            ->assertJsonPath('data.character.race', 'phb:human')
            ->assertJsonPath('data.warnings', []);

        // Verify character was created
        $character = Character::where('public_id', 'imported-wizard-abc1')->first();
        expect($character)->not->toBeNull()
            ->and($character->name)->toBe('Imported Wizard')
            ->and($character->race_slug)->toBe('phb:human')
            ->and($character->intelligence)->toBe(18);
    });

    it('imports character with spells', function () {
        $race = Race::factory()->create(['slug' => 'phb:elf']);
        $class = CharacterClass::factory()->create(['slug' => 'phb:wizard']);
        $spell = Spell::factory()->create(['slug' => 'phb:magic-missile']);

        $exportData = [
            'format_version' => '1.0',
            'character' => [
                'public_id' => 'spell-caster-xyz1',
                'name' => 'Spell Caster',
                'race' => 'phb:elf',
                'background' => null,
                'alignment' => null,
                'ability_scores' => [
                    'strength' => 8,
                    'dexterity' => 16,
                    'constitution' => 14,
                    'intelligence' => 18,
                    'wisdom' => 12,
                    'charisma' => 10,
                ],
                'classes' => [
                    ['class' => 'phb:wizard', 'subclass' => null, 'level' => 1, 'is_primary' => true],
                ],
                'spells' => [
                    ['spell' => 'phb:magic-missile', 'source' => 'class', 'preparation_status' => 'known'],
                ],
                'equipment' => [],
                'languages' => [],
                'proficiencies' => ['skills' => [], 'types' => []],
                'conditions' => [],
                'feature_selections' => [],
                'notes' => [],
            ],
        ];

        $response = $this->postJson('/api/v1/characters/import', $exportData);

        $response->assertCreated();

        $character = Character::where('public_id', 'spell-caster-xyz1')->first();
        expect($character->spells)->toHaveCount(1)
            ->and($character->spells->first()->spell_slug)->toBe('phb:magic-missile');
    });

    it('imports character with custom equipment', function () {
        $race = Race::factory()->create(['slug' => 'phb:dwarf']);
        $class = CharacterClass::factory()->create(['slug' => 'phb:fighter']);

        $exportData = [
            'format_version' => '1.0',
            'character' => [
                'public_id' => 'custom-equipped-123',
                'name' => 'Custom Equipped',
                'race' => 'phb:dwarf',
                'background' => null,
                'alignment' => null,
                'ability_scores' => [
                    'strength' => 16,
                    'dexterity' => 10,
                    'constitution' => 16,
                    'intelligence' => 8,
                    'wisdom' => 12,
                    'charisma' => 10,
                ],
                'classes' => [
                    ['class' => 'phb:fighter', 'subclass' => null, 'level' => 1, 'is_primary' => true],
                ],
                'spells' => [],
                'equipment' => [
                    ['item' => null, 'custom_name' => 'Lucky Coin', 'custom_description' => 'A special coin', 'quantity' => 1, 'equipped' => false],
                ],
                'languages' => [],
                'proficiencies' => ['skills' => [], 'types' => []],
                'conditions' => [],
                'feature_selections' => [],
                'notes' => [],
            ],
        ];

        $response = $this->postJson('/api/v1/characters/import', $exportData);

        $response->assertCreated();

        $character = Character::where('public_id', 'custom-equipped-123')->first();
        $customItem = $character->equipment->first();
        expect($customItem)->not->toBeNull()
            ->and($customItem->custom_name)->toBe('Lucky Coin')
            ->and($customItem->item_slug)->toBeNull();
    });

    it('reports warnings for dangling references during import', function () {
        $race = Race::factory()->create(['slug' => 'phb:human']);
        $class = CharacterClass::factory()->create(['slug' => 'phb:wizard']);

        $exportData = [
            'format_version' => '1.0',
            'character' => [
                'public_id' => 'dangling-ref-char',
                'name' => 'Dangling Refs',
                'race' => 'phb:human',
                'background' => null,
                'alignment' => null,
                'ability_scores' => [
                    'strength' => 10,
                    'dexterity' => 10,
                    'constitution' => 10,
                    'intelligence' => 10,
                    'wisdom' => 10,
                    'charisma' => 10,
                ],
                'classes' => [
                    ['class' => 'phb:wizard', 'subclass' => null, 'level' => 1, 'is_primary' => true],
                ],
                'spells' => [
                    ['spell' => 'phb:nonexistent-spell', 'source' => 'class', 'preparation_status' => 'known'],
                ],
                'equipment' => [],
                'languages' => [],
                'proficiencies' => ['skills' => [], 'types' => []],
                'conditions' => [],
                'feature_selections' => [],
                'notes' => [],
            ],
        ];

        $response = $this->postJson('/api/v1/characters/import', $exportData);

        $response->assertCreated()
            ->assertJsonPath('data.success', true);

        $warnings = $response->json('data.warnings');
        expect($warnings)->toHaveCount(1)
            ->and($warnings[0])->toContain('phb:nonexistent-spell');

        // Character should still be created with the dangling reference
        $character = Character::where('public_id', 'dangling-ref-char')->first();
        expect($character->spells)->toHaveCount(1)
            ->and($character->spells->first()->spell_slug)->toBe('phb:nonexistent-spell');
    });

    it('generates new public_id on conflict', function () {
        $race = Race::factory()->create(['slug' => 'phb:halfling']);
        $class = CharacterClass::factory()->create(['slug' => 'phb:rogue']);

        // Create existing character with the same public_id
        Character::factory()->create(['public_id' => 'existing-char-123']);

        $exportData = [
            'format_version' => '1.0',
            'character' => [
                'public_id' => 'existing-char-123', // Conflicts!
                'name' => 'New Character',
                'race' => 'phb:halfling',
                'background' => null,
                'alignment' => null,
                'ability_scores' => [
                    'strength' => 8,
                    'dexterity' => 18,
                    'constitution' => 14,
                    'intelligence' => 10,
                    'wisdom' => 12,
                    'charisma' => 14,
                ],
                'classes' => [
                    ['class' => 'phb:rogue', 'subclass' => null, 'level' => 1, 'is_primary' => true],
                ],
                'spells' => [],
                'equipment' => [],
                'languages' => [],
                'proficiencies' => ['skills' => [], 'types' => []],
                'conditions' => [],
                'feature_selections' => [],
                'notes' => [],
            ],
        ];

        $response = $this->postJson('/api/v1/characters/import', $exportData);

        $response->assertCreated()
            ->assertJsonPath('data.success', true);

        // New character should have a different public_id
        $newPublicId = $response->json('data.character.public_id');
        expect($newPublicId)->not->toBe('existing-char-123');

        // Both characters should exist
        expect(Character::count())->toBe(2);
    });

    it('validates format version', function () {
        $exportData = [
            'format_version' => '99.0', // Unsupported version
            'character' => [
                'public_id' => 'test-char',
                'name' => 'Test',
                'race' => 'phb:human',
                'background' => null,
                'alignment' => null,
                'ability_scores' => [
                    'strength' => 10,
                    'dexterity' => 10,
                    'constitution' => 10,
                    'intelligence' => 10,
                    'wisdom' => 10,
                    'charisma' => 10,
                ],
                'classes' => [],
                'spells' => [],
                'equipment' => [],
                'languages' => [],
                'proficiencies' => ['skills' => [], 'types' => []],
                'conditions' => [],
                'feature_selections' => [],
                'notes' => [],
            ],
        ];

        $response = $this->postJson('/api/v1/characters/import', $exportData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['format_version']);
    });

    it('validates required fields', function () {
        $exportData = [
            'format_version' => '1.0',
            // Missing 'character' key
        ];

        $response = $this->postJson('/api/v1/characters/import', $exportData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['character']);
    });

    it('imports character notes', function () {
        $race = Race::factory()->create(['slug' => 'phb:tiefling']);
        $class = CharacterClass::factory()->create(['slug' => 'phb:sorcerer']);

        $exportData = [
            'format_version' => '1.0',
            'character' => [
                'public_id' => 'noted-char-456',
                'name' => 'Noted Character',
                'race' => 'phb:tiefling',
                'background' => null,
                'alignment' => null,
                'ability_scores' => [
                    'strength' => 8,
                    'dexterity' => 14,
                    'constitution' => 14,
                    'intelligence' => 10,
                    'wisdom' => 10,
                    'charisma' => 18,
                ],
                'classes' => [
                    ['class' => 'phb:sorcerer', 'subclass' => null, 'level' => 1, 'is_primary' => true],
                ],
                'spells' => [],
                'equipment' => [],
                'languages' => [],
                'proficiencies' => ['skills' => [], 'types' => []],
                'conditions' => [],
                'feature_selections' => [],
                'notes' => [
                    ['category' => 'backstory', 'title' => 'My Story', 'content' => 'A tragic tale...', 'sort_order' => 1],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/characters/import', $exportData);

        $response->assertCreated();

        $character = Character::where('public_id', 'noted-char-456')->first();
        expect($character->notes)->toHaveCount(1)
            ->and($character->notes->first()->title)->toBe('My Story');
    });
});

describe('Round-trip Export/Import', function () {
    it('produces identical export after import', function () {
        // Create a complete character
        $race = Race::factory()->create(['slug' => 'phb:elf', 'name' => 'Elf']);
        $background = Background::factory()->create(['slug' => 'phb:noble', 'name' => 'Noble']);
        $class = CharacterClass::factory()->create(['slug' => 'phb:bard', 'name' => 'Bard']);
        $spell = Spell::factory()->create(['slug' => 'phb:cure-wounds', 'name' => 'Cure Wounds']);
        // Use seeded language (core:elvish from LanguageSeeder)
        $language = Language::where('slug', 'core:elvish')->first();
        expect($language)->not->toBeNull('Elvish language should be seeded');

        $character = Character::factory()->create([
            'public_id' => 'round-trip-test',
            'name' => 'Round Trip',
            'race_slug' => 'phb:elf',
            'background_slug' => 'phb:noble',
            'alignment' => 'Chaotic Good',
            'strength' => 8,
            'dexterity' => 16,
            'constitution' => 12,
            'intelligence' => 14,
            'wisdom' => 10,
            'charisma' => 16,
        ]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => 'phb:bard',
            'level' => 3,
            'is_primary' => true,
        ]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => 'phb:cure-wounds',
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        CharacterLanguage::factory()->create([
            'character_id' => $character->id,
            'language_slug' => 'core:elvish',
            'source' => 'race',
        ]);

        CharacterNote::factory()->create([
            'character_id' => $character->id,
            'category' => NoteCategory::Backstory,
            'title' => 'My Journey',
            'content' => 'It all began...',
            'sort_order' => 1,
        ]);

        // Step 1: Export
        $exportResponse = $this->getJson("/api/v1/characters/{$character->public_id}/export");
        $exportResponse->assertOk();
        $exportData = $exportResponse->json('data');

        // Remove the exported_at timestamp for comparison
        unset($exportData['exported_at']);

        // Step 2: Delete the original character
        $character->spells()->delete();
        $character->languages()->delete();
        $character->notes()->delete();
        $character->characterClasses()->delete();
        $character->delete();

        // Step 3: Import
        $importResponse = $this->postJson('/api/v1/characters/import', $exportData);
        $importResponse->assertCreated();

        $newPublicId = $importResponse->json('data.character.public_id');

        // Step 4: Export again
        $reExportResponse = $this->getJson("/api/v1/characters/{$newPublicId}/export");
        $reExportResponse->assertOk();
        $reExportData = $reExportResponse->json('data');

        // Remove timestamps for comparison
        unset($reExportData['exported_at']);

        // Step 5: Compare (public_id might differ due to conflict resolution logic)
        expect($reExportData['character']['name'])->toBe($exportData['character']['name'])
            ->and($reExportData['character']['race'])->toBe($exportData['character']['race'])
            ->and($reExportData['character']['background'])->toBe($exportData['character']['background'])
            ->and($reExportData['character']['ability_scores'])->toBe($exportData['character']['ability_scores'])
            ->and(count($reExportData['character']['spells']))->toBe(count($exportData['character']['spells']))
            ->and(count($reExportData['character']['languages']))->toBe(count($exportData['character']['languages']))
            ->and(count($reExportData['character']['notes']))->toBe(count($exportData['character']['notes']));
    });
});
