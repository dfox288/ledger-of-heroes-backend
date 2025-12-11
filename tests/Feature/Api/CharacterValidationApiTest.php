<?php

use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterCondition;
use App\Models\CharacterEquipment;
use App\Models\CharacterLanguage;
use App\Models\CharacterSpell;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Single Character Validation', function () {
    it('returns valid for character with all references resolved', function () {
        $race = Race::factory()->create(['slug' => 'phb:human']);
        $background = Background::factory()->create(['slug' => 'phb:acolyte']);
        $class = CharacterClass::factory()->create(['slug' => 'phb:fighter']);

        $character = Character::factory()->create([
            'race_slug' => 'phb:human',
            'background_slug' => 'phb:acolyte',
        ]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => 'phb:fighter',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/validate");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'valid' => true,
                    'dangling_references' => [],
                ],
            ]);
    });

    it('detects dangling race reference', function () {
        $character = Character::factory()->create([
            'race_slug' => 'phb:nonexistent-race',
            'background_slug' => null,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/validate");

        $response->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.dangling_references.race.0.reference', 'phb:nonexistent-race')
            ->assertJsonPath('data.dangling_references.race.0.type', 'race')
            ->assertJsonPath('data.dangling_references.race.0.message', 'Race "phb:nonexistent-race" not found');
    });

    it('detects dangling background reference', function () {
        $race = Race::factory()->create(['slug' => 'phb:elf']);
        $character = Character::factory()->create([
            'race_slug' => 'phb:elf',
            'background_slug' => 'phb:nonexistent-background',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/validate");

        $response->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.dangling_references.background.0.reference', 'phb:nonexistent-background')
            ->assertJsonPath('data.dangling_references.background.0.type', 'background')
            ->assertJsonPath('data.dangling_references.background.0.message', 'Background "phb:nonexistent-background" not found');
    });

    it('detects dangling class references', function () {
        $character = Character::factory()->create([
            'race_slug' => null,
            'background_slug' => null,
        ]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => 'phb:nonexistent-class',
            'subclass_slug' => null,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/validate");

        $response->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.dangling_references.classes.0.reference', 'phb:nonexistent-class')
            ->assertJsonPath('data.dangling_references.classes.0.type', 'class')
            ->assertJsonPath('data.dangling_references.classes.0.message', 'Class "phb:nonexistent-class" not found');
    });

    it('detects dangling subclass references', function () {
        $class = CharacterClass::factory()->create(['slug' => 'phb:wizard']);
        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => 'phb:wizard',
            'subclass_slug' => 'phb:nonexistent-subclass',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/validate");

        $response->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.dangling_references.subclasses.0.reference', 'phb:nonexistent-subclass')
            ->assertJsonPath('data.dangling_references.subclasses.0.type', 'subclass')
            ->assertJsonPath('data.dangling_references.subclasses.0.message', 'Subclass "phb:nonexistent-subclass" not found');
    });

    it('detects dangling spell references', function () {
        $character = Character::factory()->create();

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => 'phb:nonexistent-spell',
            'preparation_status' => 'known',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/validate");

        $response->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.dangling_references.spells.0.reference', 'phb:nonexistent-spell')
            ->assertJsonPath('data.dangling_references.spells.0.type', 'spell')
            ->assertJsonPath('data.dangling_references.spells.0.message', 'Spell "phb:nonexistent-spell" not found');
    });

    it('detects dangling item references', function () {
        $character = Character::factory()->create();

        CharacterEquipment::factory()->create([
            'character_id' => $character->id,
            'item_slug' => 'phb:nonexistent-item',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/validate");

        $response->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.dangling_references.items.0.reference', 'phb:nonexistent-item')
            ->assertJsonPath('data.dangling_references.items.0.type', 'item')
            ->assertJsonPath('data.dangling_references.items.0.message', 'Item "phb:nonexistent-item" not found');
    });

    it('detects dangling language references', function () {
        $character = Character::factory()->create();

        CharacterLanguage::factory()->create([
            'character_id' => $character->id,
            'language_slug' => 'phb:nonexistent-language',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/validate");

        $response->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.dangling_references.languages.0.reference', 'phb:nonexistent-language')
            ->assertJsonPath('data.dangling_references.languages.0.type', 'language')
            ->assertJsonPath('data.dangling_references.languages.0.message', 'Language "phb:nonexistent-language" not found');
    });

    it('detects dangling condition references', function () {
        $character = Character::factory()->create();

        CharacterCondition::factory()->create([
            'character_id' => $character->id,
            'condition_slug' => 'phb:nonexistent-condition',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/validate");

        $response->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.dangling_references.conditions.0.reference', 'phb:nonexistent-condition')
            ->assertJsonPath('data.dangling_references.conditions.0.type', 'condition')
            ->assertJsonPath('data.dangling_references.conditions.0.message', 'Condition "phb:nonexistent-condition" not found');
    });

    it('detects multiple dangling references at once', function () {
        $character = Character::factory()->create([
            'race_slug' => 'phb:missing-race',
            'background_slug' => 'phb:missing-background',
        ]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => 'phb:missing-class',
        ]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => 'phb:missing-spell',
            'preparation_status' => 'known',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/validate");

        $response->assertOk()
            ->assertJsonPath('data.valid', false);

        $data = $response->json('data.dangling_references');
        expect($data)->toHaveKey('race')
            ->toHaveKey('background')
            ->toHaveKey('classes')
            ->toHaveKey('spells');
    });

    it('returns 404 for nonexistent character', function () {
        $response = $this->getJson('/api/v1/characters/nonexistent-id/validate');

        $response->assertNotFound();
    });

    it('includes summary statistics in response', function () {
        $race = Race::factory()->create(['slug' => 'phb:dwarf']);
        $character = Character::factory()->create([
            'race_slug' => 'phb:dwarf',
        ]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => 'phb:missing-spell-1',
            'preparation_status' => 'known',
        ]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => 'phb:missing-spell-2',
            'preparation_status' => 'known',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/validate");

        $response->assertOk()
            ->assertJsonPath('data.summary.total_references', 3)  // 1 race + 2 spells
            ->assertJsonPath('data.summary.valid_references', 1)  // race only
            ->assertJsonPath('data.summary.dangling_count', 2);   // 2 spells
    });
});

describe('Bulk Character Validation', function () {
    it('returns validation status for all characters', function () {
        $race = Race::factory()->create(['slug' => 'phb:halfling']);

        // Valid character
        $validCharacter = Character::factory()->create([
            'race_slug' => 'phb:halfling',
            'background_slug' => null,
        ]);

        // Invalid character
        $invalidCharacter = Character::factory()->create([
            'race_slug' => 'phb:nonexistent',
            'background_slug' => null,
        ]);

        $response = $this->getJson('/api/v1/characters/validate-all');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total',
                    'valid',
                    'invalid',
                    'characters',
                ],
            ]);

        $data = $response->json('data');
        expect($data['total'])->toBe(2)
            ->and($data['valid'])->toBe(1)
            ->and($data['invalid'])->toBe(1);
    });

    it('only includes invalid characters in the characters array', function () {
        $race = Race::factory()->create(['slug' => 'phb:gnome']);

        $validCharacter = Character::factory()->create([
            'race_slug' => 'phb:gnome',
        ]);

        $invalidCharacter = Character::factory()->create([
            'race_slug' => 'phb:nonexistent',
        ]);

        $response = $this->getJson('/api/v1/characters/validate-all');

        $characters = $response->json('data.characters');
        expect($characters)->toHaveCount(1)
            ->and($characters[0]['public_id'])->toBe($invalidCharacter->public_id);
    });

    it('returns empty characters array when all are valid', function () {
        $race = Race::factory()->create(['slug' => 'phb:tiefling']);

        Character::factory()->create(['race_slug' => 'phb:tiefling']);
        Character::factory()->create(['race_slug' => 'phb:tiefling']);

        $response = $this->getJson('/api/v1/characters/validate-all');

        $response->assertOk()
            ->assertJsonPath('data.invalid', 0)
            ->assertJsonPath('data.characters', []);
    });

    it('includes dangling references for each invalid character', function () {
        $invalidCharacter = Character::factory()->create([
            'race_slug' => 'phb:missing-race',
        ]);

        $response = $this->getJson('/api/v1/characters/validate-all');

        $characters = $response->json('data.characters');
        expect($characters[0])->toHaveKey('dangling_references')
            ->and($characters[0]['dangling_references']['race'][0]['reference'])->toBe('phb:missing-race')
            ->and($characters[0]['dangling_references']['race'][0]['type'])->toBe('race')
            ->and($characters[0]['dangling_references']['race'][0]['message'])->toBe('Race "phb:missing-race" not found');
    });

    it('validates many characters efficiently with eager loading', function () {
        $race = Race::factory()->create(['slug' => 'phb:human']);

        // Create 50 characters - should use eager loading, not N+1 queries
        Character::factory()->count(50)->create([
            'race_slug' => 'phb:human',
        ]);

        // Enable query logging
        \Illuminate\Support\Facades\DB::enableQueryLog();

        $response = $this->getJson('/api/v1/characters/validate-all');

        $queryCount = count(\Illuminate\Support\Facades\DB::getQueryLog());

        $response->assertOk()
            ->assertJsonPath('data.total', 50)
            ->assertJsonPath('data.valid', 50);

        // With proper eager loading, should be ~15 queries (1 for characters + 7 for relations + some lookups)
        // Without eager loading, would be 50 * 8 = 400+ queries
        expect($queryCount)->toBeLessThan(30);
    });
});
