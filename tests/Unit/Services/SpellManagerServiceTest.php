<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\ClassFeature;
use App\Models\Spell;
use App\Services\SpellManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpellManagerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    private SpellManagerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SpellManagerService::class);
    }

    #[Test]
    public function get_available_spells_returns_base_class_spells(): void
    {
        // Create Warlock class with spells
        $warlock = CharacterClass::factory()->create([
            'name' => 'Warlock',
            'slug' => 'warlock',
            'parent_class_id' => null,
        ]);

        $eldritchBlast = Spell::factory()->cantrip()->create([
            'name' => 'Eldritch Blast',
            'slug' => 'eldritch-blast',
        ]);

        $hex = Spell::factory()->create([
            'name' => 'Hex',
            'slug' => 'hex',
            'level' => 1,
        ]);

        // Link spells to class
        DB::table('class_spells')->insert([
            ['class_id' => $warlock->id, 'spell_id' => $eldritchBlast->id],
            ['class_id' => $warlock->id, 'spell_id' => $hex->id],
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $warlock->slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $availableSpells = $this->service->getAvailableSpells($character);

        $this->assertCount(2, $availableSpells);
        $spellNames = $availableSpells->pluck('name')->toArray();
        $this->assertContains('Eldritch Blast', $spellNames);
        $this->assertContains('Hex', $spellNames);
    }

    #[Test]
    public function get_available_spells_includes_subclass_expanded_spells(): void
    {
        // Create Warlock class with base spell
        $warlock = CharacterClass::factory()->create([
            'name' => 'Warlock',
            'slug' => 'warlock',
            'parent_class_id' => null,
        ]);

        // Create Hexblade subclass
        $hexblade = CharacterClass::factory()->create([
            'name' => 'The Hexblade',
            'slug' => 'the-hexblade',
            'parent_class_id' => $warlock->id,
        ]);

        // Create Expanded Spell List feature
        $expandedSpellFeature = ClassFeature::factory()->create([
            'class_id' => $hexblade->id,
            'feature_name' => 'Expanded Spell List (The Hexblade)',
            'level' => 1,
            'is_optional' => false,
        ]);

        // Base Warlock spell
        $hex = Spell::factory()->create([
            'name' => 'Hex',
            'slug' => 'hex',
            'level' => 1,
        ]);

        // Expanded spell (only available to Hexblade)
        $shield = Spell::factory()->create([
            'name' => 'Shield',
            'slug' => 'shield',
            'level' => 1,
        ]);

        // Link base spell to Warlock class
        DB::table('class_spells')->insert([
            ['class_id' => $warlock->id, 'spell_id' => $hex->id],
        ]);

        // Link expanded spell to Hexblade feature
        DB::table('entity_spells')->insert([
            'reference_type' => ClassFeature::class,
            'reference_id' => $expandedSpellFeature->id,
            'spell_id' => $shield->id,
            'level_requirement' => 1,
            'is_cantrip' => false,
        ]);

        $character = Character::factory()->create();

        // Character is a Hexblade Warlock
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $warlock->slug,
            'subclass_slug' => $hexblade->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        $availableSpells = $this->service->getAvailableSpells($character);

        // Should include both base Warlock spell AND Hexblade expanded spell
        $this->assertCount(2, $availableSpells);
        $spellNames = $availableSpells->pluck('name')->toArray();
        $this->assertContains('Hex', $spellNames);
        $this->assertContains('Shield', $spellNames);
    }

    #[Test]
    public function get_available_spells_excludes_expanded_spells_without_subclass(): void
    {
        // Create Warlock class with base spell
        $warlock = CharacterClass::factory()->create([
            'name' => 'Warlock',
            'slug' => 'warlock',
            'parent_class_id' => null,
        ]);

        // Create Hexblade subclass
        $hexblade = CharacterClass::factory()->create([
            'name' => 'The Hexblade',
            'slug' => 'the-hexblade',
            'parent_class_id' => $warlock->id,
        ]);

        // Create Expanded Spell List feature
        $expandedSpellFeature = ClassFeature::factory()->create([
            'class_id' => $hexblade->id,
            'feature_name' => 'Expanded Spell List (The Hexblade)',
            'level' => 1,
            'is_optional' => false,
        ]);

        // Base Warlock spell
        $hex = Spell::factory()->create([
            'name' => 'Hex',
            'slug' => 'hex',
            'level' => 1,
        ]);

        // Expanded spell (only available to Hexblade)
        $shield = Spell::factory()->create([
            'name' => 'Shield',
            'slug' => 'shield',
            'level' => 1,
        ]);

        // Link base spell to Warlock class
        DB::table('class_spells')->insert([
            ['class_id' => $warlock->id, 'spell_id' => $hex->id],
        ]);

        // Link expanded spell to Hexblade feature
        DB::table('entity_spells')->insert([
            'reference_type' => ClassFeature::class,
            'reference_id' => $expandedSpellFeature->id,
            'spell_id' => $shield->id,
            'level_requirement' => 1,
            'is_cantrip' => false,
        ]);

        $character = Character::factory()->create();

        // Character is a Warlock WITHOUT a subclass selected
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $warlock->slug,
            'subclass_slug' => null, // No subclass
            'level' => 1,
            'is_primary' => true,
        ]);

        $availableSpells = $this->service->getAvailableSpells($character);

        // Should only include base Warlock spell, NOT Hexblade expanded spell
        $this->assertCount(1, $availableSpells);
        $spellNames = $availableSpells->pluck('name')->toArray();
        $this->assertContains('Hex', $spellNames);
        $this->assertNotContains('Shield', $spellNames);
    }

    #[Test]
    public function get_available_spells_respects_level_requirement_for_expanded_spells(): void
    {
        $warlock = CharacterClass::factory()->create([
            'name' => 'Warlock',
            'slug' => 'warlock',
            'parent_class_id' => null,
        ]);

        $hexblade = CharacterClass::factory()->create([
            'name' => 'The Hexblade',
            'slug' => 'the-hexblade',
            'parent_class_id' => $warlock->id,
        ]);

        $expandedSpellFeature = ClassFeature::factory()->create([
            'class_id' => $hexblade->id,
            'feature_name' => 'Expanded Spell List (The Hexblade)',
            'level' => 1,
            'is_optional' => false,
        ]);

        // Level 1 expanded spell
        $shield = Spell::factory()->create([
            'name' => 'Shield',
            'slug' => 'shield',
            'level' => 1,
        ]);

        // Level 3 expanded spell (requires level 5 in class per D&D 5e)
        $blink = Spell::factory()->create([
            'name' => 'Blink',
            'slug' => 'blink',
            'level' => 3,
        ]);

        // Link expanded spells with level requirements
        DB::table('entity_spells')->insert([
            [
                'reference_type' => ClassFeature::class,
                'reference_id' => $expandedSpellFeature->id,
                'spell_id' => $shield->id,
                'level_requirement' => 1, // Available at level 1
                'is_cantrip' => false,
            ],
            [
                'reference_type' => ClassFeature::class,
                'reference_id' => $expandedSpellFeature->id,
                'spell_id' => $blink->id,
                'level_requirement' => 5, // Available at level 5
                'is_cantrip' => false,
            ],
        ]);

        $character = Character::factory()->create();

        // Character is level 1 Hexblade
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $warlock->slug,
            'subclass_slug' => $hexblade->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        // At level 1, only Shield should be available
        $availableSpells = $this->service->getAvailableSpells($character);

        $this->assertCount(1, $availableSpells);
        $this->assertEquals('Shield', $availableSpells->first()->name);
    }

    #[Test]
    public function get_available_spells_excludes_already_known_expanded_spells(): void
    {
        $warlock = CharacterClass::factory()->create([
            'name' => 'Warlock',
            'slug' => 'warlock',
            'parent_class_id' => null,
        ]);

        $hexblade = CharacterClass::factory()->create([
            'name' => 'The Hexblade',
            'slug' => 'the-hexblade',
            'parent_class_id' => $warlock->id,
        ]);

        $expandedSpellFeature = ClassFeature::factory()->create([
            'class_id' => $hexblade->id,
            'feature_name' => 'Expanded Spell List (The Hexblade)',
            'level' => 1,
            'is_optional' => false,
        ]);

        $shield = Spell::factory()->create([
            'name' => 'Shield',
            'slug' => 'shield',
            'level' => 1,
        ]);

        $wrathfulSmite = Spell::factory()->create([
            'name' => 'Wrathful Smite',
            'slug' => 'wrathful-smite',
            'level' => 1,
        ]);

        DB::table('entity_spells')->insert([
            [
                'reference_type' => ClassFeature::class,
                'reference_id' => $expandedSpellFeature->id,
                'spell_id' => $shield->id,
                'level_requirement' => 1,
                'is_cantrip' => false,
            ],
            [
                'reference_type' => ClassFeature::class,
                'reference_id' => $expandedSpellFeature->id,
                'spell_id' => $wrathfulSmite->id,
                'level_requirement' => 1,
                'is_cantrip' => false,
            ],
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $warlock->slug,
            'subclass_slug' => $hexblade->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        // Character already knows Shield
        $character->spells()->create([
            'spell_slug' => $shield->slug,
            'preparation_status' => 'known',
            'source' => 'class',
            'level_acquired' => 1,
        ]);

        $availableSpells = $this->service->getAvailableSpells($character);

        // Should only include Wrathful Smite (Shield is already known)
        $this->assertCount(1, $availableSpells);
        $this->assertEquals('Wrathful Smite', $availableSpells->first()->name);
    }

    #[Test]
    public function get_available_spells_does_not_duplicate_spells_on_both_lists(): void
    {
        // Some spells might be on both the base class list AND expanded list
        $warlock = CharacterClass::factory()->create([
            'name' => 'Warlock',
            'slug' => 'warlock',
            'parent_class_id' => null,
        ]);

        $hexblade = CharacterClass::factory()->create([
            'name' => 'The Hexblade',
            'slug' => 'the-hexblade',
            'parent_class_id' => $warlock->id,
        ]);

        $expandedSpellFeature = ClassFeature::factory()->create([
            'class_id' => $hexblade->id,
            'feature_name' => 'Expanded Spell List (The Hexblade)',
            'level' => 1,
            'is_optional' => false,
        ]);

        // A spell that's on both Warlock base list and Hexblade expanded (hypothetically)
        $hex = Spell::factory()->create([
            'name' => 'Hex',
            'slug' => 'hex',
            'level' => 1,
        ]);

        // Link to base class
        DB::table('class_spells')->insert([
            ['class_id' => $warlock->id, 'spell_id' => $hex->id],
        ]);

        // Also link to expanded list (shouldn't happen in practice, but test deduplication)
        DB::table('entity_spells')->insert([
            'reference_type' => ClassFeature::class,
            'reference_id' => $expandedSpellFeature->id,
            'spell_id' => $hex->id,
            'level_requirement' => 1,
            'is_cantrip' => false,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $warlock->slug,
            'subclass_slug' => $hexblade->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        $availableSpells = $this->service->getAvailableSpells($character);

        // Should only appear once
        $this->assertCount(1, $availableSpells);
        $this->assertEquals('Hex', $availableSpells->first()->name);
    }

    // =========================================================================
    // getCharacterSpells Tests
    // =========================================================================

    #[Test]
    public function get_character_spells_returns_all_known_spells(): void
    {
        $character = Character::factory()->create();

        $spell1 = Spell::factory()->create(['name' => 'Magic Missile', 'slug' => 'test:magic-missile']);
        $spell2 = Spell::factory()->create(['name' => 'Shield', 'slug' => 'test:shield']);

        $character->spells()->create([
            'spell_slug' => $spell1->slug,
            'preparation_status' => 'known',
            'source' => 'class',
            'level_acquired' => 1,
        ]);

        $character->spells()->create([
            'spell_slug' => $spell2->slug,
            'preparation_status' => 'prepared',
            'source' => 'class',
            'level_acquired' => 1,
        ]);

        $spells = $this->service->getCharacterSpells($character);

        $this->assertCount(2, $spells);
    }

    #[Test]
    public function get_character_spells_returns_empty_collection_for_character_with_no_spells(): void
    {
        $character = Character::factory()->create();

        $spells = $this->service->getCharacterSpells($character);

        $this->assertCount(0, $spells);
    }

    // =========================================================================
    // learnSpell Tests
    // =========================================================================

    #[Test]
    public function learn_spell_adds_spell_to_character(): void
    {
        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'test:wizard',
            'parent_class_id' => null,
        ]);

        $spell = Spell::factory()->create([
            'name' => 'Magic Missile',
            'slug' => 'test:magic-missile',
            'level' => 1,
        ]);

        DB::table('class_spells')->insert([
            ['class_id' => $wizard->id, 'spell_id' => $spell->id],
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $wizard->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        $characterSpell = $this->service->learnSpell($character, $spell);

        $this->assertEquals($spell->slug, $characterSpell->spell_slug);
        $this->assertEquals('known', $characterSpell->preparation_status);
        $this->assertEquals('class', $characterSpell->source);
        $this->assertDatabaseHas('character_spells', [
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
        ]);
    }

    #[Test]
    public function learn_spell_throws_exception_when_spell_not_on_class_list(): void
    {
        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'test:wizard',
            'parent_class_id' => null,
        ]);

        // Spell NOT linked to wizard class
        $spell = Spell::factory()->create([
            'name' => 'Cure Wounds',
            'slug' => 'test:cure-wounds',
            'level' => 1,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $wizard->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        $this->expectException(\App\Exceptions\SpellManagementException::class);
        $this->expectExceptionMessage("not available for this character's class");

        $this->service->learnSpell($character, $spell);
    }

    #[Test]
    public function learn_spell_throws_exception_when_spell_level_too_high(): void
    {
        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'test:wizard',
            'parent_class_id' => null,
        ]);

        // Level 9 spell - too high for level 1 character
        $spell = Spell::factory()->create([
            'name' => 'Wish',
            'slug' => 'test:wish',
            'level' => 9,
        ]);

        DB::table('class_spells')->insert([
            ['class_id' => $wizard->id, 'spell_id' => $spell->id],
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $wizard->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        $this->expectException(\App\Exceptions\SpellManagementException::class);
        $this->expectExceptionMessage('Maximum spell level');

        $this->service->learnSpell($character, $spell);
    }

    #[Test]
    public function learn_spell_throws_exception_when_spell_already_known(): void
    {
        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'test:wizard',
            'parent_class_id' => null,
        ]);

        $spell = Spell::factory()->create([
            'name' => 'Magic Missile',
            'slug' => 'test:magic-missile',
            'level' => 1,
        ]);

        DB::table('class_spells')->insert([
            ['class_id' => $wizard->id, 'spell_id' => $spell->id],
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $wizard->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        // Already knows the spell
        $character->spells()->create([
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
            'level_acquired' => 1,
        ]);

        $this->expectException(\App\Exceptions\SpellManagementException::class);
        $this->expectExceptionMessage('already known');

        $this->service->learnSpell($character, $spell);
    }

    // =========================================================================
    // forgetSpell Tests
    // =========================================================================

    #[Test]
    public function forget_spell_removes_spell_from_character(): void
    {
        $character = Character::factory()->create();

        $spell = Spell::factory()->create([
            'name' => 'Magic Missile',
            'slug' => 'test:magic-missile',
            'level' => 1,
        ]);

        $character->spells()->create([
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
            'level_acquired' => 1,
        ]);

        $this->assertDatabaseHas('character_spells', [
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
        ]);

        $this->service->forgetSpell($character, $spell);

        $this->assertDatabaseMissing('character_spells', [
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
        ]);
    }

    #[Test]
    public function forget_spell_throws_exception_when_spell_not_known(): void
    {
        $character = Character::factory()->create();

        $spell = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'test:fireball',
            'level' => 3,
        ]);

        $this->expectException(\App\Exceptions\SpellManagementException::class);
        $this->expectExceptionMessage('not known by this character');

        $this->service->forgetSpell($character, $spell);
    }

    // =========================================================================
    // prepareSpell Tests
    // =========================================================================

    #[Test]
    public function prepare_spell_changes_status_to_prepared(): void
    {
        $character = Character::factory()->create(['intelligence' => 16]); // +3 modifier

        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'test:wizard',
            'parent_class_id' => null,
            'spellcasting_ability_id' => \App\Models\AbilityScore::where('code', 'INT')->first()?->id,
        ]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $wizard->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        $spell = Spell::factory()->create([
            'name' => 'Magic Missile',
            'slug' => 'test:magic-missile',
            'level' => 1,
        ]);

        $character->spells()->create([
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
            'level_acquired' => 1,
        ]);

        $characterSpell = $this->service->prepareSpell($character, $spell);

        $this->assertEquals('prepared', $characterSpell->preparation_status);
    }

    #[Test]
    public function prepare_spell_throws_exception_for_cantrip(): void
    {
        $character = Character::factory()->create();

        $cantrip = Spell::factory()->cantrip()->create([
            'name' => 'Fire Bolt',
            'slug' => 'test:fire-bolt',
        ]);

        $character->spells()->create([
            'spell_slug' => $cantrip->slug,
            'preparation_status' => 'known',
            'source' => 'class',
            'level_acquired' => 1,
        ]);

        $this->expectException(\App\Exceptions\SpellManagementException::class);
        $this->expectExceptionMessage('Cantrips cannot be prepared');

        $this->service->prepareSpell($character, $cantrip);
    }

    #[Test]
    public function prepare_spell_throws_exception_when_spell_not_known(): void
    {
        $character = Character::factory()->create();

        $spell = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'test:fireball',
            'level' => 3,
        ]);

        $this->expectException(\App\Exceptions\SpellManagementException::class);
        $this->expectExceptionMessage('not known by this character');

        $this->service->prepareSpell($character, $spell);
    }

    // =========================================================================
    // unprepareSpell Tests
    // =========================================================================

    #[Test]
    public function unprepare_spell_changes_status_to_known(): void
    {
        $character = Character::factory()->create();

        $spell = Spell::factory()->create([
            'name' => 'Magic Missile',
            'slug' => 'test:magic-missile',
            'level' => 1,
        ]);

        $character->spells()->create([
            'spell_slug' => $spell->slug,
            'preparation_status' => 'prepared',
            'source' => 'class',
            'level_acquired' => 1,
        ]);

        $characterSpell = $this->service->unprepareSpell($character, $spell);

        $this->assertEquals('known', $characterSpell->preparation_status);
    }

    #[Test]
    public function unprepare_spell_throws_exception_when_spell_not_known(): void
    {
        $character = Character::factory()->create();

        $spell = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'test:fireball',
            'level' => 3,
        ]);

        $this->expectException(\App\Exceptions\SpellManagementException::class);
        $this->expectExceptionMessage('not known by this character');

        $this->service->unprepareSpell($character, $spell);
    }

    // =========================================================================
    // getSpellSlots Tests
    // =========================================================================

    #[Test]
    public function get_spell_slots_returns_empty_for_character_without_class(): void
    {
        $character = Character::factory()->create();

        $slots = $this->service->getSpellSlots($character);

        $this->assertEquals([], $slots['slots']);
        $this->assertNull($slots['pact_magic']);
        $this->assertNull($slots['preparation_limit']);
        $this->assertEquals(0, $slots['prepared_count']);
    }

    #[Test]
    public function get_spell_slots_returns_slot_data_for_standard_caster(): void
    {
        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'test:wizard',
            'parent_class_id' => null,
            'spellcasting_ability_id' => \App\Models\AbilityScore::where('code', 'INT')->first()?->id,
        ]);

        $character = Character::factory()->create(['intelligence' => 16]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $wizard->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        $slots = $this->service->getSpellSlots($character);

        // Level 1 wizard has 2 first level slots
        $this->assertArrayHasKey('1', $slots['slots']);
        $this->assertNull($slots['pact_magic']);
    }
}
