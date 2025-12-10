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
            'full_slug' => 'test:eldritch-blast',
        ]);

        $hex = Spell::factory()->create([
            'name' => 'Hex',
            'slug' => 'hex',
            'full_slug' => 'test:hex',
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
            'class_slug' => $warlock->full_slug,
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
            'full_slug' => 'test:hex',
            'level' => 1,
        ]);

        // Expanded spell (only available to Hexblade)
        $shield = Spell::factory()->create([
            'name' => 'Shield',
            'slug' => 'shield',
            'full_slug' => 'test:shield',
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
            'class_slug' => $warlock->full_slug,
            'subclass_slug' => $hexblade->full_slug,
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
            'full_slug' => 'test:hex',
            'level' => 1,
        ]);

        // Expanded spell (only available to Hexblade)
        $shield = Spell::factory()->create([
            'name' => 'Shield',
            'slug' => 'shield',
            'full_slug' => 'test:shield',
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
            'class_slug' => $warlock->full_slug,
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
            'full_slug' => 'test:shield',
            'level' => 1,
        ]);

        // Level 3 expanded spell (requires level 5 in class per D&D 5e)
        $blink = Spell::factory()->create([
            'name' => 'Blink',
            'slug' => 'blink',
            'full_slug' => 'test:blink',
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
            'class_slug' => $warlock->full_slug,
            'subclass_slug' => $hexblade->full_slug,
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
            'full_slug' => 'test:shield',
            'level' => 1,
        ]);

        $wrathfulSmite = Spell::factory()->create([
            'name' => 'Wrathful Smite',
            'slug' => 'wrathful-smite',
            'full_slug' => 'test:wrathful-smite',
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
            'class_slug' => $warlock->full_slug,
            'subclass_slug' => $hexblade->full_slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        // Character already knows Shield
        $character->spells()->create([
            'spell_slug' => 'test:shield',
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
            'full_slug' => 'test:hex',
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
            'class_slug' => $warlock->full_slug,
            'subclass_slug' => $hexblade->full_slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        $availableSpells = $this->service->getAvailableSpells($character);

        // Should only appear once
        $this->assertCount(1, $availableSpells);
        $this->assertEquals('Hex', $availableSpells->first()->name);
    }
}
