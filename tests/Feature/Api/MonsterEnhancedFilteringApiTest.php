<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
use App\Models\MonsterSpellcasting;
use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for enhanced Monster filtering features:
 * - OR logic for spell filtering
 * - Spell level filtering
 * - Spellcasting ability filtering
 * - Combined filter scenarios
 */
class MonsterEnhancedFilteringApiTest extends TestCase
{
    use RefreshDatabase;

    // ========================================
    // OR Logic Tests
    // ========================================

    #[Test]
    public function can_filter_monsters_by_spells_with_or_logic()
    {
        // Create test data
        $source = $this->getSource('PHB');
        $school = SpellSchool::factory()->create(['code' => 'EVO']);

        $fireball = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball', 'spell_school_id' => $school->id]);
        $lightning = Spell::factory()->create(['name' => 'Lightning Bolt', 'slug' => 'lightning-bolt', 'spell_school_id' => $school->id]);
        $teleport = Spell::factory()->create(['name' => 'Teleport', 'slug' => 'teleport', 'spell_school_id' => $school->id]);

        // Monster with Fireball only
        $archmage = Monster::factory()->create(['name' => 'Archmage']);
        $this->createEntitySource($archmage, $source);
        $archmage->entitySpells()->attach([$fireball->id]);

        // Monster with Lightning Bolt only
        $stormGiant = Monster::factory()->create(['name' => 'Storm Giant']);
        $this->createEntitySource($stormGiant, $source);
        $stormGiant->entitySpells()->attach([$lightning->id]);

        // Monster with both Fireball and Lightning Bolt
        $lich = Monster::factory()->create(['name' => 'Lich']);
        $this->createEntitySource($lich, $source);
        $lich->entitySpells()->attach([$fireball->id, $lightning->id]);

        // Monster with Teleport only
        $mage = Monster::factory()->create(['name' => 'Mage']);
        $this->createEntitySource($mage, $source);
        $mage->entitySpells()->attach([$teleport->id]);

        // Monster with no spells
        Monster::factory()->create(['name' => 'Goblin']);

        // Test OR logic: should return monsters with Fireball OR Lightning Bolt
        $response = $this->getJson('/api/v1/monsters?spells=fireball,lightning-bolt&spells_operator=OR');

        $response->assertOk();
        $response->assertJsonCount(3, 'data'); // Archmage, Storm Giant, Lich

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Archmage', $names);
        $this->assertContains('Storm Giant', $names);
        $this->assertContains('Lich', $names);
        $this->assertNotContains('Mage', $names);
        $this->assertNotContains('Goblin', $names);
    }

    #[Test]
    public function or_logic_returns_more_results_than_and_logic()
    {
        $source = $this->getSource('PHB');
        $school = SpellSchool::factory()->create(['code' => 'EVO']);

        $fireball = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball', 'spell_school_id' => $school->id]);
        $lightning = Spell::factory()->create(['name' => 'Lightning Bolt', 'slug' => 'lightning-bolt', 'spell_school_id' => $school->id]);

        // Monster with Fireball only
        $archmage = Monster::factory()->create(['name' => 'Archmage']);
        $this->createEntitySource($archmage, $source);
        $archmage->entitySpells()->attach([$fireball->id]);

        // Monster with both spells
        $lich = Monster::factory()->create(['name' => 'Lich']);
        $this->createEntitySource($lich, $source);
        $lich->entitySpells()->attach([$fireball->id, $lightning->id]);

        // AND logic: only Lich has both
        $andResponse = $this->getJson('/api/v1/monsters?spells=fireball,lightning-bolt');
        $andResponse->assertJsonCount(1, 'data');

        // OR logic: both Archmage and Lich have at least one
        $orResponse = $this->getJson('/api/v1/monsters?spells=fireball,lightning-bolt&spells_operator=OR');
        $orResponse->assertJsonCount(2, 'data');
    }

    #[Test]
    public function can_filter_monsters_by_three_or_more_spells_with_or_logic()
    {
        $source = $this->getSource('PHB');
        $school = SpellSchool::factory()->create(['code' => 'EVO']);

        $fireball = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball', 'spell_school_id' => $school->id]);
        $lightning = Spell::factory()->create(['name' => 'Lightning Bolt', 'slug' => 'lightning-bolt', 'spell_school_id' => $school->id]);
        $cone = Spell::factory()->create(['name' => 'Cone of Cold', 'slug' => 'cone-of-cold', 'spell_school_id' => $school->id]);

        // Different monsters with different spells
        $mage1 = Monster::factory()->create(['name' => 'Fire Mage']);
        $this->createEntitySource($mage1, $source);
        $mage1->entitySpells()->attach([$fireball->id]);

        $mage2 = Monster::factory()->create(['name' => 'Storm Mage']);
        $this->createEntitySource($mage2, $source);
        $mage2->entitySpells()->attach([$lightning->id]);

        $mage3 = Monster::factory()->create(['name' => 'Ice Mage']);
        $this->createEntitySource($mage3, $source);
        $mage3->entitySpells()->attach([$cone->id]);

        Monster::factory()->create(['name' => 'Goblin']);

        // OR with 3 spells
        $response = $this->getJson('/api/v1/monsters?spells=fireball,lightning-bolt,cone-of-cold&spells_operator=OR');

        $response->assertOk();
        $response->assertJsonCount(3, 'data'); // All three mages
    }

    #[Test]
    public function or_operator_with_single_spell_behaves_like_normal()
    {
        $source = $this->getSource('PHB');
        $school = SpellSchool::factory()->create(['code' => 'EVO']);

        $fireball = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball', 'spell_school_id' => $school->id]);

        $archmage = Monster::factory()->create(['name' => 'Archmage']);
        $this->createEntitySource($archmage, $source);
        $archmage->entitySpells()->attach([$fireball->id]);

        Monster::factory()->create(['name' => 'Goblin']);

        // OR with single spell
        $response = $this->getJson('/api/v1/monsters?spells=fireball&spells_operator=OR');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Archmage');
    }

    #[Test]
    public function and_operator_is_default_for_backward_compatibility()
    {
        $source = $this->getSource('PHB');
        $school = SpellSchool::factory()->create(['code' => 'EVO']);

        $fireball = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball', 'spell_school_id' => $school->id]);
        $lightning = Spell::factory()->create(['name' => 'Lightning Bolt', 'slug' => 'lightning-bolt', 'spell_school_id' => $school->id]);

        // Monster with both spells
        $lich = Monster::factory()->create(['name' => 'Lich']);
        $this->createEntitySource($lich, $source);
        $lich->entitySpells()->attach([$fireball->id, $lightning->id]);

        // Monster with only one spell
        $archmage = Monster::factory()->create(['name' => 'Archmage']);
        $this->createEntitySource($archmage, $source);
        $archmage->entitySpells()->attach([$fireball->id]);

        // Without operator parameter (should default to AND)
        $response = $this->getJson('/api/v1/monsters?spells=fireball,lightning-bolt');

        $response->assertOk();
        $response->assertJsonCount(1, 'data'); // Only Lich has both
        $response->assertJsonPath('data.0.name', 'Lich');
    }

    #[Test]
    public function or_logic_with_nonexistent_spell_slug_returns_partial_results()
    {
        $source = $this->getSource('PHB');
        $school = SpellSchool::factory()->create(['code' => 'EVO']);

        $fireball = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball', 'spell_school_id' => $school->id]);

        $archmage = Monster::factory()->create(['name' => 'Archmage']);
        $this->createEntitySource($archmage, $source);
        $archmage->entitySpells()->attach([$fireball->id]);

        // OR with one valid and one invalid spell
        $response = $this->getJson('/api/v1/monsters?spells=fireball,nonexistent-spell&spells_operator=OR');

        $response->assertOk();
        $response->assertJsonCount(1, 'data'); // Still finds Archmage with Fireball
    }

    // ========================================
    // Spell Level Filtering Tests
    // ========================================

    #[Test]
    public function can_filter_monsters_by_cantrips()
    {
        $source = $this->getSource('PHB');
        $school = SpellSchool::factory()->create(['code' => 'EVO']);

        $cantrip = Spell::factory()->create(['name' => 'Fire Bolt', 'slug' => 'fire-bolt', 'level' => 0, 'spell_school_id' => $school->id]);
        $levelOne = Spell::factory()->create(['name' => 'Magic Missile', 'slug' => 'magic-missile', 'level' => 1, 'spell_school_id' => $school->id]);

        // Monster with cantrips
        $apprentice = Monster::factory()->create(['name' => 'Apprentice Wizard']);
        $this->createEntitySource($apprentice, $source);
        $apprentice->entitySpells()->attach([$cantrip->id]);

        // Monster with 1st level spells
        $adept = Monster::factory()->create(['name' => 'Adept']);
        $this->createEntitySource($adept, $source);
        $adept->entitySpells()->attach([$levelOne->id]);

        $response = $this->getJson('/api/v1/monsters?spell_level=0');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Apprentice Wizard');
    }

    #[Test]
    public function can_filter_monsters_by_low_level_spells()
    {
        $source = $this->getSource('PHB');
        $school = SpellSchool::factory()->create(['code' => 'EVO']);

        $level1 = Spell::factory()->create(['name' => 'Magic Missile', 'slug' => 'magic-missile', 'level' => 1, 'spell_school_id' => $school->id]);
        $level2 = Spell::factory()->create(['name' => 'Scorching Ray', 'slug' => 'scorching-ray', 'level' => 2, 'spell_school_id' => $school->id]);
        $level3 = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball', 'level' => 3, 'spell_school_id' => $school->id]);

        // Monster with 2nd level spells
        $priest = Monster::factory()->create(['name' => 'Priest']);
        $this->createEntitySource($priest, $source);
        $priest->entitySpells()->attach([$level1->id, $level2->id]);

        // Monster with 3rd level spells
        $mage = Monster::factory()->create(['name' => 'Mage']);
        $this->createEntitySource($mage, $source);
        $mage->entitySpells()->attach([$level3->id]);

        $response = $this->getJson('/api/v1/monsters?spell_level=2');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Priest');
    }

    #[Test]
    public function can_filter_monsters_by_mid_level_spells()
    {
        $source = $this->getSource('PHB');
        $school = SpellSchool::factory()->create(['code' => 'EVO']);

        $level3 = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball', 'level' => 3, 'spell_school_id' => $school->id]);
        $level5 = Spell::factory()->create(['name' => 'Cone of Cold', 'slug' => 'cone-of-cold', 'level' => 5, 'spell_school_id' => $school->id]);

        // Monster with mid-level spells
        $veteran = Monster::factory()->create(['name' => 'Veteran Mage']);
        $this->createEntitySource($veteran, $source);
        $veteran->entitySpells()->attach([$level5->id]);

        // Lower level caster
        $novice = Monster::factory()->create(['name' => 'Novice Mage']);
        $this->createEntitySource($novice, $source);
        $novice->entitySpells()->attach([$level3->id]);

        $response = $this->getJson('/api/v1/monsters?spell_level=5');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Veteran Mage');
    }

    #[Test]
    public function can_filter_monsters_by_high_level_spells()
    {
        $source = $this->getSource('PHB');
        $school = SpellSchool::factory()->create(['code' => 'EVO']);

        $level7 = Spell::factory()->create(['name' => 'Finger of Death', 'slug' => 'finger-of-death', 'level' => 7, 'spell_school_id' => $school->id]);
        $level9 = Spell::factory()->create(['name' => 'Wish', 'slug' => 'wish', 'level' => 9, 'spell_school_id' => $school->id]);

        // Legendary archmage with 9th level
        $archmage = Monster::factory()->create(['name' => 'Archmage']);
        $this->createEntitySource($archmage, $source);
        $archmage->entitySpells()->attach([$level9->id]);

        // Lich with 7th level
        $lich = Monster::factory()->create(['name' => 'Lich']);
        $this->createEntitySource($lich, $source);
        $lich->entitySpells()->attach([$level7->id, $level9->id]);

        // Filter for 9th level spells
        $response = $this->getJson('/api/v1/monsters?spell_level=9');

        $response->assertOk();
        $response->assertJsonCount(2, 'data'); // Both have 9th level

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Archmage', $names);
        $this->assertContains('Lich', $names);
    }

    #[Test]
    public function spell_level_filter_combined_with_spell_name()
    {
        $source = $this->getSource('PHB');
        $school = SpellSchool::factory()->create(['code' => 'EVO']);

        $fireball = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball', 'level' => 3, 'spell_school_id' => $school->id]);
        $lightning = Spell::factory()->create(['name' => 'Lightning Bolt', 'slug' => 'lightning-bolt', 'level' => 3, 'spell_school_id' => $school->id]);

        // Monster with 3rd level spells including Fireball
        $mage1 = Monster::factory()->create(['name' => 'Fire Mage']);
        $this->createEntitySource($mage1, $source);
        $mage1->entitySpells()->attach([$fireball->id]);

        // Monster with 3rd level but NOT Fireball
        $mage2 = Monster::factory()->create(['name' => 'Storm Mage']);
        $this->createEntitySource($mage2, $source);
        $mage2->entitySpells()->attach([$lightning->id]);

        // Both filters should narrow results
        $response = $this->getJson('/api/v1/monsters?spell_level=3&spells=fireball');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Fire Mage');
    }

    #[Test]
    public function spell_level_above_nine_returns_validation_error()
    {
        // 10th level doesn't exist in D&D 5e - should be rejected by validation
        $response = $this->getJson('/api/v1/monsters?spell_level=10');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['spell_level']);
    }

    #[Test]
    public function negative_spell_level_returns_validation_error()
    {
        // Negative spell levels are invalid - should be rejected by validation
        $response = $this->getJson('/api/v1/monsters?spell_level=-1');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['spell_level']);
    }

    // ========================================
    // Spellcasting Ability Filtering Tests
    // ========================================

    #[Test]
    public function can_filter_monsters_by_intelligence_casters()
    {
        $source = $this->getSource('PHB');

        // INT caster (wizard-type)
        $wizard = Monster::factory()->create(['name' => 'Wizard']);
        $this->createEntitySource($wizard, $source);
        MonsterSpellcasting::factory()->create([
            'monster_id' => $wizard->id,
            'spellcasting_ability' => 'Intelligence',
        ]);

        // WIS caster
        $cleric = Monster::factory()->create(['name' => 'Cleric']);
        $this->createEntitySource($cleric, $source);
        MonsterSpellcasting::factory()->create([
            'monster_id' => $cleric->id,
            'spellcasting_ability' => 'Wisdom',
        ]);

        $response = $this->getJson('/api/v1/monsters?spellcasting_ability=INT');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Wizard');
    }

    #[Test]
    public function can_filter_monsters_by_wisdom_casters()
    {
        $source = $this->getSource('PHB');

        // WIS caster (cleric/druid type)
        $cleric = Monster::factory()->create(['name' => 'Cleric']);
        $this->createEntitySource($cleric, $source);
        MonsterSpellcasting::factory()->create([
            'monster_id' => $cleric->id,
            'spellcasting_ability' => 'Wisdom',
        ]);

        $druid = Monster::factory()->create(['name' => 'Druid']);
        $this->createEntitySource($druid, $source);
        MonsterSpellcasting::factory()->create([
            'monster_id' => $druid->id,
            'spellcasting_ability' => 'Wisdom',
        ]);

        // CHA caster
        $warlock = Monster::factory()->create(['name' => 'Warlock']);
        $this->createEntitySource($warlock, $source);
        MonsterSpellcasting::factory()->create([
            'monster_id' => $warlock->id,
            'spellcasting_ability' => 'Charisma',
        ]);

        $response = $this->getJson('/api/v1/monsters?spellcasting_ability=WIS');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Cleric', $names);
        $this->assertContains('Druid', $names);
        $this->assertNotContains('Warlock', $names);
    }

    #[Test]
    public function can_filter_monsters_by_charisma_casters()
    {
        $source = $this->getSource('PHB');

        // CHA casters (sorcerer/warlock type)
        $sorcerer = Monster::factory()->create(['name' => 'Sorcerer']);
        $this->createEntitySource($sorcerer, $source);
        MonsterSpellcasting::factory()->create([
            'monster_id' => $sorcerer->id,
            'spellcasting_ability' => 'Charisma',
        ]);

        // INT caster
        $wizard = Monster::factory()->create(['name' => 'Wizard']);
        $this->createEntitySource($wizard, $source);
        MonsterSpellcasting::factory()->create([
            'monster_id' => $wizard->id,
            'spellcasting_ability' => 'Intelligence',
        ]);

        $response = $this->getJson('/api/v1/monsters?spellcasting_ability=CHA');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Sorcerer');
    }

    #[Test]
    public function spellcasting_ability_filter_combined_with_cr()
    {
        $source = $this->getSource('PHB');

        // High CR INT caster
        $archmage = Monster::factory()->create(['name' => 'Archmage', 'challenge_rating' => '12']);
        $this->createEntitySource($archmage, $source);
        MonsterSpellcasting::factory()->create([
            'monster_id' => $archmage->id,
            'spellcasting_ability' => 'Intelligence',
        ]);

        // Low CR INT caster
        $apprentice = Monster::factory()->create(['name' => 'Apprentice', 'challenge_rating' => '1']);
        $this->createEntitySource($apprentice, $source);
        MonsterSpellcasting::factory()->create([
            'monster_id' => $apprentice->id,
            'spellcasting_ability' => 'Intelligence',
        ]);

        // High CR WIS caster
        $archdruid = Monster::factory()->create(['name' => 'Archdruid', 'challenge_rating' => '12']);
        $this->createEntitySource($archdruid, $source);
        MonsterSpellcasting::factory()->create([
            'monster_id' => $archdruid->id,
            'spellcasting_ability' => 'Wisdom',
        ]);

        // Filter for high CR INT casters
        $response = $this->getJson('/api/v1/monsters?spellcasting_ability=INT&min_cr=10');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Archmage');
    }

    #[Test]
    public function spellcasting_ability_requires_uppercase()
    {
        $source = $this->getSource('PHB');

        $wizard = Monster::factory()->create(['name' => 'Wizard']);
        $this->createEntitySource($wizard, $source);
        MonsterSpellcasting::factory()->create([
            'monster_id' => $wizard->id,
            'spellcasting_ability' => 'Intelligence',
        ]);

        // Valid uppercase
        $response = $this->getJson('/api/v1/monsters?spellcasting_ability=INT');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        // Lowercase should fail validation
        $response = $this->getJson('/api/v1/monsters?spellcasting_ability=int');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['spellcasting_ability']);
    }

    #[Test]
    public function invalid_spellcasting_ability_returns_validation_error()
    {
        // STR is not a spellcasting ability - should be rejected by validation
        $response = $this->getJson('/api/v1/monsters?spellcasting_ability=STR');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['spellcasting_ability']);
    }

    // ========================================
    // Combined Filter Tests
    // ========================================

    #[Test]
    public function can_combine_or_logic_with_spell_level()
    {
        $source = $this->getSource('PHB');
        $school = SpellSchool::factory()->create(['code' => 'EVO']);

        $fireball = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball', 'level' => 3, 'spell_school_id' => $school->id]);
        $lightning = Spell::factory()->create(['name' => 'Lightning Bolt', 'slug' => 'lightning-bolt', 'level' => 3, 'spell_school_id' => $school->id]);
        $wish = Spell::factory()->create(['name' => 'Wish', 'slug' => 'wish', 'level' => 9, 'spell_school_id' => $school->id]);

        // Monster with 3rd level Fireball
        $mage1 = Monster::factory()->create(['name' => 'Fire Mage']);
        $this->createEntitySource($mage1, $source);
        $mage1->entitySpells()->attach([$fireball->id]);

        // Monster with 3rd level Lightning Bolt
        $mage2 = Monster::factory()->create(['name' => 'Storm Mage']);
        $this->createEntitySource($mage2, $source);
        $mage2->entitySpells()->attach([$lightning->id]);

        // Monster with 9th level Wish (should be excluded)
        $archmage = Monster::factory()->create(['name' => 'Archmage']);
        $this->createEntitySource($archmage, $source);
        $archmage->entitySpells()->attach([$wish->id]);

        // OR logic + spell level 3
        $response = $this->getJson('/api/v1/monsters?spells=fireball,lightning-bolt&spells_operator=OR&spell_level=3');

        $response->assertOk();
        $response->assertJsonCount(2, 'data'); // Both 3rd level casters

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Fire Mage', $names);
        $this->assertContains('Storm Mage', $names);
        $this->assertNotContains('Archmage', $names);
    }

    #[Test]
    public function can_combine_spell_level_with_spellcasting_ability()
    {
        $source = $this->getSource('PHB');
        $school = SpellSchool::factory()->create(['code' => 'EVO']);

        $wish = Spell::factory()->create(['name' => 'Wish', 'slug' => 'wish', 'level' => 9, 'spell_school_id' => $school->id]);

        // INT caster with 9th level
        $wizard = Monster::factory()->create(['name' => 'Archmage']);
        $this->createEntitySource($wizard, $source);
        $wizard->entitySpells()->attach([$wish->id]);
        MonsterSpellcasting::factory()->create([
            'monster_id' => $wizard->id,
            'spellcasting_ability' => 'Intelligence',
        ]);

        // WIS caster with 9th level
        $druid = Monster::factory()->create(['name' => 'Archdruid']);
        $this->createEntitySource($druid, $source);
        $druid->entitySpells()->attach([$wish->id]);
        MonsterSpellcasting::factory()->create([
            'monster_id' => $druid->id,
            'spellcasting_ability' => 'Wisdom',
        ]);

        // Filter for 9th level INT casters
        $response = $this->getJson('/api/v1/monsters?spell_level=9&spellcasting_ability=INT');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Archmage');
    }

    #[Test]
    public function can_combine_all_three_enhanced_filters()
    {
        $source = $this->getSource('PHB');
        $school = SpellSchool::factory()->create(['code' => 'EVO']);

        $fireball = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball', 'level' => 3, 'spell_school_id' => $school->id]);
        $lightning = Spell::factory()->create(['name' => 'Lightning Bolt', 'slug' => 'lightning-bolt', 'level' => 3, 'spell_school_id' => $school->id]);

        // INT caster with 3rd level Fireball
        $wizard = Monster::factory()->create(['name' => 'Evoker Wizard']);
        $this->createEntitySource($wizard, $source);
        $wizard->entitySpells()->attach([$fireball->id]);
        MonsterSpellcasting::factory()->create([
            'monster_id' => $wizard->id,
            'spellcasting_ability' => 'Intelligence',
        ]);

        // INT caster with 3rd level Lightning Bolt
        $stormWizard = Monster::factory()->create(['name' => 'Storm Wizard']);
        $this->createEntitySource($stormWizard, $source);
        $stormWizard->entitySpells()->attach([$lightning->id]);
        MonsterSpellcasting::factory()->create([
            'monster_id' => $stormWizard->id,
            'spellcasting_ability' => 'Intelligence',
        ]);

        // CHA caster with 3rd level Fireball (should be excluded)
        $sorcerer = Monster::factory()->create(['name' => 'Sorcerer']);
        $this->createEntitySource($sorcerer, $source);
        $sorcerer->entitySpells()->attach([$fireball->id]);
        MonsterSpellcasting::factory()->create([
            'monster_id' => $sorcerer->id,
            'spellcasting_ability' => 'Charisma',
        ]);

        // Combine: OR logic + spell level 3 + INT casters
        $response = $this->getJson('/api/v1/monsters?spells=fireball,lightning-bolt&spells_operator=OR&spell_level=3&spellcasting_ability=INT');

        $response->assertOk();
        $response->assertJsonCount(2, 'data'); // Both INT wizards

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Evoker Wizard', $names);
        $this->assertContains('Storm Wizard', $names);
        $this->assertNotContains('Sorcerer', $names);
    }

    #[Test]
    public function enhanced_filters_work_with_multiple_base_filters()
    {
        $source = $this->getSource('PHB');
        $school = SpellSchool::factory()->create(['code' => 'EVO']);

        $fireball = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball', 'level' => 3, 'spell_school_id' => $school->id]);

        // Dragon with Fireball and high CR
        $dragon = Monster::factory()->create(['name' => 'Ancient Red Dragon', 'type' => 'dragon', 'challenge_rating' => '20']);
        $this->createEntitySource($dragon, $source);
        $dragon->entitySpells()->attach([$fireball->id]);

        // Humanoid mage with Fireball but low CR
        $mage = Monster::factory()->create(['name' => 'Fire Mage', 'type' => 'humanoid', 'challenge_rating' => '6']);
        $this->createEntitySource($mage, $source);
        $mage->entitySpells()->attach([$fireball->id]);

        // Dragon without Fireball
        $blueDragon = Monster::factory()->create(['name' => 'Ancient Blue Dragon', 'type' => 'dragon', 'challenge_rating' => '20']);
        $this->createEntitySource($blueDragon, $source);

        // Combine type filter + spell filter
        $response = $this->getJson('/api/v1/monsters?type=dragon&spells=fireball');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Ancient Red Dragon');
    }

    #[Test]
    public function enhanced_filters_work_with_cr_range()
    {
        $source = $this->getSource('PHB');
        $school = SpellSchool::factory()->create(['code' => 'EVO']);

        $fireball = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball', 'level' => 3, 'spell_school_id' => $school->id]);

        // High CR with Fireball
        $archmage = Monster::factory()->create(['name' => 'Archmage', 'challenge_rating' => '12']);
        $this->createEntitySource($archmage, $source);
        $archmage->entitySpells()->attach([$fireball->id]);

        // Low CR with Fireball
        $mage = Monster::factory()->create(['name' => 'Mage', 'challenge_rating' => '6']);
        $this->createEntitySource($mage, $source);
        $mage->entitySpells()->attach([$fireball->id]);

        // Filter: CR 10+ with Fireball
        $response = $this->getJson('/api/v1/monsters?spells=fireball&min_cr=10');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Archmage');
    }

    #[Test]
    public function enhanced_filters_work_with_type_filter()
    {
        $source = $this->getSource('PHB');
        $school = SpellSchool::factory()->create(['code' => 'EVO']);

        $fireball = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball', 'level' => 3, 'spell_school_id' => $school->id]);

        // Undead with Fireball
        $lich = Monster::factory()->create(['name' => 'Lich', 'type' => 'undead']);
        $this->createEntitySource($lich, $source);
        $lich->entitySpells()->attach([$fireball->id]);

        // Humanoid with Fireball
        $mage = Monster::factory()->create(['name' => 'Mage', 'type' => 'humanoid']);
        $this->createEntitySource($mage, $source);
        $mage->entitySpells()->attach([$fireball->id]);

        // Filter: Undead with Fireball
        $response = $this->getJson('/api/v1/monsters?spells=fireball&type=undead');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Lich');
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Get or create a source for testing
     */
    protected function getSource(string $code): \App\Models\Source
    {
        return \App\Models\Source::where('code', $code)->first()
            ?? \App\Models\Source::factory()->create(['code' => $code, 'name' => "Test Source {$code}"]);
    }

    /**
     * Create an entity source relationship
     */
    protected function createEntitySource(Monster $monster, \App\Models\Source $source): void
    {
        \App\Models\EntitySource::create([
            'reference_type' => Monster::class,
            'reference_id' => $monster->id,
            'source_id' => $source->id,
            'pages' => '1',
        ]);
    }
}
