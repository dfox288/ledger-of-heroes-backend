<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
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
    // REMOVED: These tests relied on monster_spellcasting table which was deleted
    // as it was never populated (0 rows). The feature was replaced by entity_spells
    // polymorphic relationship which provides richer spell associations.

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

    // REMOVED: Test relied on monster_spellcasting table

    // REMOVED: Test relied on monster_spellcasting table

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

    // ========================================
    // Tag-Based Filtering Tests
    // ========================================

    #[Test]
    public function can_filter_monsters_by_single_tag()
    {
        $source = $this->getSource('MM');

        // Monster with fire_immune tag
        $balor = Monster::factory()->create(['name' => 'Balor']);
        $this->createEntitySource($balor, $source);
        $balor->attachTag('Fire Immune', 'immunity');

        // Monster with poison_immune tag
        $devil = Monster::factory()->create(['name' => 'Devil']);
        $this->createEntitySource($devil, $source);
        $devil->attachTag('Poison Immune', 'immunity');

        // Monster with no tags
        Monster::factory()->create(['name' => 'Goblin']);

        // Index monsters for search
        Monster::where('name', 'Balor')->first()->searchable();
        Monster::where('name', 'Devil')->first()->searchable();
        Monster::where('name', 'Goblin')->first()->searchable();
        sleep(1); // Give Meilisearch time to index

        // Filter by fire-immune tag
        $response = $this->getJson('/api/v1/monsters?filter=tag_slugs IN [fire-immune]');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Balor');

        // Verify tags are included in response
        $tags = $response->json('data.0.tags');
        $this->assertCount(1, $tags);
        $this->assertEquals('Fire Immune', $tags[0]['name']);
        $this->assertEquals('fire-immune', $tags[0]['slug']);
        $this->assertEquals('immunity', $tags[0]['type']);
    }

    #[Test]
    public function can_filter_monsters_by_multiple_tags_or_logic()
    {
        $source = $this->getSource('MM');

        // Fiend with fire immunity
        $balor = Monster::factory()->create(['name' => 'Balor']);
        $this->createEntitySource($balor, $source);
        $balor->attachTag('Fiend', 'creature_type');
        $balor->attachTag('Fire Immune', 'immunity');

        // Fiend without fire immunity
        $devil = Monster::factory()->create(['name' => 'Devil']);
        $this->createEntitySource($devil, $source);
        $devil->attachTag('Fiend', 'creature_type');

        // Fire immune non-fiend
        $salamander = Monster::factory()->create(['name' => 'Salamander']);
        $this->createEntitySource($salamander, $source);
        $salamander->attachTag('Fire Immune', 'immunity');

        // Index monsters for search
        $balor->searchable();
        $devil->searchable();
        $salamander->searchable();
        sleep(1); // Give Meilisearch time to index

        // Filter: fiend OR fire-immune (should get all 3)
        $response = $this->getJson('/api/v1/monsters?filter=tag_slugs IN [fiend, fire-immune]');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Balor', $names);
        $this->assertContains('Devil', $names);
        $this->assertContains('Salamander', $names);
    }

    #[Test]
    public function can_filter_monsters_by_tags_and_challenge_rating()
    {
        $source = $this->getSource('MM');

        // CR 20 fiend (high CR)
        $highCrFiend = Monster::factory()->create(['name' => 'Pit Fiend', 'challenge_rating' => '20']);
        $this->createEntitySource($highCrFiend, $source);
        $highCrFiend->attachTag('Fiend', 'creature_type');

        // CR 2 fiend (low CR)
        $lowCrFiend = Monster::factory()->create(['name' => 'Imp', 'challenge_rating' => '2']);
        $this->createEntitySource($lowCrFiend, $source);
        $lowCrFiend->attachTag('Fiend', 'creature_type');

        // CR 20 non-fiend
        $dragon = Monster::factory()->create(['name' => 'Dragon', 'challenge_rating' => '20']);
        $this->createEntitySource($dragon, $source);
        $dragon->attachTag('Dragon', 'creature_type');

        // Index monsters for search
        $highCrFiend->searchable();
        $lowCrFiend->searchable();
        $dragon->searchable();
        sleep(1); // Give Meilisearch time to index

        // Filter: fiend AND exact CR
        $response = $this->getJson('/api/v1/monsters?filter=tag_slugs IN [fiend] AND challenge_rating = 20');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Pit Fiend');
    }

    #[Test]
    public function tag_filter_returns_empty_when_no_matches()
    {
        $source = $this->getSource('MM');

        // Create monsters without the searched tag
        $goblin = Monster::factory()->create(['name' => 'Goblin']);
        $this->createEntitySource($goblin, $source);
        $goblin->attachTag('Humanoid', 'creature_type');

        // Index monster for search
        $goblin->searchable();
        sleep(1); // Give Meilisearch time to index

        // Search for non-existent tag (using unique tag that won't exist in real data)
        $response = $this->getJson('/api/v1/monsters?filter=tag_slugs IN [test-nonexistent-tag-xyz]');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('meta.total', 0);
    }

    #[Test]
    public function can_combine_tag_filter_with_type_filter()
    {
        $source = $this->getSource('MM');

        // Dragon with fire immunity
        $redDragon = Monster::factory()->create(['name' => 'Red Dragon', 'type' => 'dragon']);
        $this->createEntitySource($redDragon, $source);
        $redDragon->attachTag('Fire Immune', 'immunity');

        // Fiend with fire immunity
        $balor = Monster::factory()->create(['name' => 'Balor', 'type' => 'fiend']);
        $this->createEntitySource($balor, $source);
        $balor->attachTag('Fire Immune', 'immunity');

        // Dragon without fire immunity
        $blueDragon = Monster::factory()->create(['name' => 'Blue Dragon', 'type' => 'dragon']);
        $this->createEntitySource($blueDragon, $source);

        // Index monsters for search
        $redDragon->searchable();
        $balor->searchable();
        $blueDragon->searchable();
        sleep(1); // Give Meilisearch time to index

        // Filter: type=dragon AND fire-immune
        $response = $this->getJson('/api/v1/monsters?filter=type = dragon AND tag_slugs IN [fire-immune]');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Red Dragon');
    }

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
