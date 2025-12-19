<?php

namespace Tests\Feature\Api;

use App\Enums\SpellSlotType;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterCondition;
use App\Models\CharacterCounter;
use App\Models\CharacterSpell;
use App\Models\CharacterSpellSlot;
use App\Models\Condition;
use App\Models\Race;
use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the unified combat endpoint.
 *
 * GET /api/v1/characters/{id}/combat
 *
 * Returns all combat-relevant data in a single response for battle interfaces.
 */
class CharacterCombatEndpointTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // Structure Tests
    // =====================

    #[Test]
    public function it_returns_combat_data_structure(): void
    {
        $race = Race::factory()->create();
        $character = Character::factory()
            ->withStandardArray()
            ->create([
                'race_slug' => $race->slug,
                'current_hit_points' => 35,
                'max_hit_points' => 45,
                'temp_hit_points' => 5,
            ]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 5,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/combat");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'character' => ['id', 'public_id', 'name', 'level'],
                    'combat_stats' => [
                        'armor_class',
                        'hit_points' => ['current', 'max', 'temp'],
                        'initiative_bonus',
                        'proficiency_bonus',
                        'speed',
                    ],
                    'saving_throws',
                    'weapons',
                    'unarmed_strike',
                    'spell_slots',
                    'prepared_spells',
                    'resources',
                    'conditions',
                    'death_saves' => ['successes', 'failures', 'is_conscious', 'is_dead'],
                    'defenses' => [
                        'resistances',
                        'immunities',
                        'vulnerabilities',
                        'condition_immunities',
                    ],
                    'spellcasting',
                ],
            ]);
    }

    #[Test]
    public function it_returns_basic_character_info(): void
    {
        $character = Character::factory()
            ->withStandardArray()
            ->create(['name' => 'Thorin Battleaxe']);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 7,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/combat");

        $response->assertOk()
            ->assertJsonPath('data.character.name', 'Thorin Battleaxe')
            ->assertJsonPath('data.character.level', 7)
            ->assertJsonPath('data.character.id', $character->id)
            ->assertJsonPath('data.character.public_id', $character->public_id);
    }

    // =====================
    // Combat Stats Tests
    // =====================

    #[Test]
    public function it_returns_hit_points_state(): void
    {
        $character = Character::factory()
            ->withStandardArray()
            ->create([
                'current_hit_points' => 25,
                'max_hit_points' => 50,
                'temp_hit_points' => 10,
            ]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 5,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/combat");

        $response->assertOk()
            ->assertJsonPath('data.combat_stats.hit_points.current', 25)
            ->assertJsonPath('data.combat_stats.hit_points.max', 50)
            ->assertJsonPath('data.combat_stats.hit_points.temp', 10);
    }

    #[Test]
    public function it_returns_proficiency_bonus_based_on_level(): void
    {
        $character = Character::factory()->withStandardArray()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 5, // Level 5 = +3 proficiency bonus
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/combat");

        $response->assertOk()
            ->assertJsonPath('data.combat_stats.proficiency_bonus', 3);
    }

    // =====================
    // Death Saves Tests
    // =====================

    #[Test]
    public function it_returns_death_saves_state(): void
    {
        $character = Character::factory()
            ->withStandardArray()
            ->create([
                'current_hit_points' => 0,
                'death_save_successes' => 2,
                'death_save_failures' => 1,
            ]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/combat");

        $response->assertOk()
            ->assertJsonPath('data.death_saves.successes', 2)
            ->assertJsonPath('data.death_saves.failures', 1)
            ->assertJsonPath('data.death_saves.is_conscious', false)
            ->assertJsonPath('data.death_saves.is_dead', false);
    }

    #[Test]
    public function it_shows_conscious_when_hp_above_zero(): void
    {
        $character = Character::factory()
            ->withStandardArray()
            ->create(['current_hit_points' => 10]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/combat");

        $response->assertOk()
            ->assertJsonPath('data.death_saves.is_conscious', true);
    }

    #[Test]
    public function it_shows_dead_when_character_is_dead(): void
    {
        $character = Character::factory()
            ->withStandardArray()
            ->create([
                'current_hit_points' => 0,
                'is_dead' => true,
            ]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/combat");

        $response->assertOk()
            ->assertJsonPath('data.death_saves.is_dead', true);
    }

    // =====================
    // Conditions Tests
    // =====================

    #[Test]
    public function it_returns_active_conditions(): void
    {
        $character = Character::factory()->withStandardArray()->create();
        $poisoned = Condition::factory()->create(['slug' => 'test:poisoned-'.uniqid(), 'name' => 'Poisoned']);
        $blinded = Condition::factory()->create(['slug' => 'test:blinded-'.uniqid(), 'name' => 'Blinded']);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        CharacterCondition::factory()->create([
            'character_id' => $character->id,
            'condition_slug' => $poisoned->slug,
        ]);

        CharacterCondition::factory()->create([
            'character_id' => $character->id,
            'condition_slug' => $blinded->slug,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/combat");

        $response->assertOk();

        $conditions = $response->json('data.conditions');
        $this->assertCount(2, $conditions);
    }

    #[Test]
    public function it_returns_empty_conditions_when_none_active(): void
    {
        $character = Character::factory()->withStandardArray()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/combat");

        $response->assertOk()
            ->assertJsonPath('data.conditions', []);
    }

    // =====================
    // Resources Tests
    // =====================

    #[Test]
    public function it_returns_class_resources(): void
    {
        $character = Character::factory()->withStandardArray()->create();
        $class = CharacterClass::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 2,
            'is_primary' => true,
        ]);

        // Create Action Surge counter
        CharacterCounter::create([
            'character_id' => $character->id,
            'source_type' => 'class',
            'source_slug' => $class->slug,
            'counter_name' => 'Action Surge',
            'current_uses' => 1,
            'max_uses' => 1,
            'reset_timing' => 'S', // Short rest
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/combat");

        $response->assertOk();

        $resources = $response->json('data.resources');
        $this->assertCount(1, $resources);
        $this->assertEquals('Action Surge', $resources[0]['name']);
        $this->assertEquals(1, $resources[0]['uses']);
        $this->assertEquals(1, $resources[0]['max']);
        $this->assertEquals('short_rest', $resources[0]['recharge']);
    }

    #[Test]
    public function it_returns_empty_resources_when_none_exist(): void
    {
        $character = Character::factory()->withStandardArray()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/combat");

        $response->assertOk()
            ->assertJsonPath('data.resources', []);
    }

    // =====================
    // Spell Slots Tests
    // =====================

    #[Test]
    public function it_returns_spell_slots(): void
    {
        $character = Character::factory()->withStandardArray()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 5,
            'is_primary' => true,
        ]);

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 1,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 2,
            'max_slots' => 3,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/combat");

        $response->assertOk();

        $spellSlots = $response->json('data.spell_slots');
        $this->assertArrayHasKey('standard', $spellSlots);
    }

    // =====================
    // Prepared Spells Tests
    // =====================

    #[Test]
    public function it_returns_only_prepared_spells(): void
    {
        $character = Character::factory()->withStandardArray()->create();
        $school = SpellSchool::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 5,
            'is_primary' => true,
        ]);

        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'test:fireball-'.uniqid(),
            'level' => 3,
            'needs_concentration' => false,
            'spell_school_id' => $school->id,
        ]);

        $shield = Spell::factory()->create([
            'name' => 'Shield',
            'slug' => 'test:shield-'.uniqid(),
            'level' => 1,
            'needs_concentration' => false,
            'spell_school_id' => $school->id,
        ]);

        $unprepared = Spell::factory()->create([
            'name' => 'Magic Missile',
            'slug' => 'test:magic-missile-'.uniqid(),
            'level' => 1,
            'spell_school_id' => $school->id,
        ]);

        // Prepared spell
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $fireball->slug,
            'preparation_status' => 'prepared',
            'source' => 'class',
        ]);

        // Always prepared spell
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $shield->slug,
            'preparation_status' => 'always_prepared',
            'source' => 'class',
        ]);

        // Not prepared spell (known but not prepared)
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $unprepared->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/combat");

        $response->assertOk();

        $preparedSpells = $response->json('data.prepared_spells');
        $this->assertCount(2, $preparedSpells);

        $spellNames = collect($preparedSpells)->pluck('name')->toArray();
        $this->assertContains('Fireball', $spellNames);
        $this->assertContains('Shield', $spellNames);
        $this->assertNotContains('Magic Missile', $spellNames);
    }

    #[Test]
    public function it_returns_prepared_spell_details(): void
    {
        $character = Character::factory()->withStandardArray()->create();
        $school = SpellSchool::factory()->create(['name' => 'Evocation']);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 5,
            'is_primary' => true,
        ]);

        $spell = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'test:fireball-'.uniqid(),
            'level' => 3,
            'needs_concentration' => false,
            'is_ritual' => false,
            'casting_time' => '1 action',
            'range' => '150 feet',
            'spell_school_id' => $school->id,
        ]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'prepared',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/combat");

        $response->assertOk();

        $preparedSpells = $response->json('data.prepared_spells');
        $fireball = $preparedSpells[0];

        $this->assertEquals('Fireball', $fireball['name']);
        $this->assertEquals(3, $fireball['level']);
        $this->assertEquals('Evocation', $fireball['school']);
        $this->assertFalse($fireball['concentration']);
        $this->assertFalse($fireball['ritual']);
        $this->assertEquals('1 action', $fireball['casting_time']);
        $this->assertEquals('150 feet', $fireball['range']);
    }

    // =====================
    // Access Tests
    // =====================

    #[Test]
    public function it_returns_404_for_nonexistent_character(): void
    {
        $response = $this->getJson('/api/v1/characters/99999/combat');

        $response->assertNotFound();
    }

    #[Test]
    public function it_works_with_public_id(): void
    {
        $character = Character::factory()->withStandardArray()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/combat");

        $response->assertOk()
            ->assertJsonPath('data.character.id', $character->id);
    }
}
