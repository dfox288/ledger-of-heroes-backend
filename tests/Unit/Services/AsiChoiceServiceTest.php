<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AbilityScoreCapExceededException;
use App\Exceptions\FeatAlreadyTakenException;
use App\Exceptions\NoAsiChoicesRemainingException;
use App\Exceptions\PrerequisitesNotMetException;
use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterFeature;
use App\Models\EntityPrerequisite;
use App\Models\Feat;
use App\Models\Modifier;
use App\Models\Proficiency;
use App\Models\ProficiencyType;
use App\Models\Spell;
use App\Services\AsiChoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AsiChoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private AsiChoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AsiChoiceService;
    }

    // =========================================================================
    // Common Validation Tests
    // =========================================================================

    #[Test]
    public function it_throws_when_no_asi_choices_remaining(): void
    {
        $character = Character::factory()->create([
            'asi_choices_remaining' => 0,
        ]);
        $feat = Feat::factory()->create();

        $this->expectException(NoAsiChoicesRemainingException::class);

        $this->service->applyFeatChoice($character, $feat);
    }

    // =========================================================================
    // Feat Choice Tests
    // =========================================================================

    #[Test]
    public function it_throws_when_feat_already_taken(): void
    {
        $character = Character::factory()->create([
            'asi_choices_remaining' => 1,
        ]);
        $feat = Feat::factory()->create();

        // Character already has this feat (using slug-based lookup)
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_slug' => $feat->full_slug,
            'source' => 'feat',
            'level_acquired' => 1,
        ]);

        $this->expectException(FeatAlreadyTakenException::class);

        $this->service->applyFeatChoice($character, $feat);
    }

    #[Test]
    public function it_throws_when_prerequisites_not_met(): void
    {
        $character = Character::factory()->create([
            'asi_choices_remaining' => 1,
            'strength' => 10,
        ]);

        $feat = Feat::factory()->create();
        $strength = AbilityScore::firstOrCreate(['code' => 'STR'], ['name' => 'Strength']);

        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
        ]);

        $this->expectException(PrerequisitesNotMetException::class);

        $this->service->applyFeatChoice($character, $feat->fresh());
    }

    #[Test]
    public function it_throws_when_feat_ability_increase_would_exceed_cap(): void
    {
        $character = Character::factory()->create([
            'asi_choices_remaining' => 1,
            'strength' => 20, // Already maxed
        ]);

        $feat = Feat::factory()->create();
        $strength = AbilityScore::firstOrCreate(['code' => 'STR'], ['name' => 'Strength']);

        // Feat grants +1 STR
        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $strength->id,
            'value' => '1',
        ]);

        $this->expectException(AbilityScoreCapExceededException::class);

        $this->service->applyFeatChoice($character, $feat->fresh());
    }

    #[Test]
    public function it_decrements_asi_choices_remaining_for_feat(): void
    {
        $character = Character::factory()->create([
            'asi_choices_remaining' => 2,
        ]);
        $feat = Feat::factory()->create();

        $result = $this->service->applyFeatChoice($character, $feat);

        $this->assertEquals(1, $character->fresh()->asi_choices_remaining);
        $this->assertEquals(1, $result->asiChoicesRemaining);
    }

    #[Test]
    public function it_creates_character_feature_for_feat(): void
    {
        $character = Character::factory()->create([
            'asi_choices_remaining' => 1,
        ]);
        $feat = Feat::factory()->create();

        $this->service->applyFeatChoice($character, $feat);

        $this->assertDatabaseHas('character_features', [
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_slug' => $feat->full_slug,
            'source' => 'feat',
        ]);
    }

    #[Test]
    public function it_applies_half_feat_ability_increase(): void
    {
        $character = Character::factory()->create([
            'asi_choices_remaining' => 1,
            'constitution' => 14,
        ]);

        $feat = Feat::factory()->create();
        $constitution = AbilityScore::firstOrCreate(['code' => 'CON'], ['name' => 'Constitution']);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $constitution->id,
            'value' => '1',
        ]);

        $result = $this->service->applyFeatChoice($character, $feat->fresh());

        $this->assertEquals(15, $character->fresh()->constitution);
        $this->assertEquals(['CON' => 1], $result->abilityIncreases);
        $this->assertEquals(15, $result->newAbilityScores['CON']);
    }

    #[Test]
    public function it_grants_proficiencies_from_feat(): void
    {
        $character = Character::factory()->create([
            'asi_choices_remaining' => 1,
        ]);

        // Create a proficiency type with full_slug
        $proficiencyType = ProficiencyType::firstOrCreate(
            ['slug' => 'saving-throw'],
            ['name' => 'Saving Throw', 'full_slug' => 'phb:saving-throw', 'category' => 'saving_throw']
        );

        $feat = Feat::factory()->create();
        Proficiency::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'proficiency_type_id' => $proficiencyType->id,
            'proficiency_type' => 'saving_throw',
            'proficiency_name' => 'Constitution Saves',
        ]);

        $result = $this->service->applyFeatChoice($character, $feat->fresh());

        $this->assertDatabaseHas('character_proficiencies', [
            'character_id' => $character->id,
            'proficiency_type_slug' => 'phb:saving-throw',
            'source' => 'feat',
        ]);
        $this->assertContains('Constitution Saves', $result->proficienciesGained);
    }

    #[Test]
    public function it_grants_spells_from_feat(): void
    {
        $character = Character::factory()->create([
            'asi_choices_remaining' => 1,
        ]);

        $spell = Spell::factory()->create(['name' => 'Firebolt', 'slug' => 'firebolt', 'full_slug' => 'phb:firebolt']);
        $feat = Feat::factory()->create();
        $feat->spells()->attach($spell->id);

        $result = $this->service->applyFeatChoice($character, $feat->fresh());

        $this->assertDatabaseHas('character_spells', [
            'character_id' => $character->id,
            'spell_slug' => $spell->full_slug,
            'source' => 'feat',
        ]);
        $this->assertCount(1, $result->spellsGained);
        $this->assertEquals('Firebolt', $result->spellsGained[0]['name']);
        $this->assertEquals('phb:firebolt', $result->spellsGained[0]['slug']);
    }

    #[Test]
    public function it_returns_feat_info_in_result(): void
    {
        $character = Character::factory()->create([
            'asi_choices_remaining' => 1,
        ]);
        $feat = Feat::factory()->create([
            'name' => 'Alert',
            'slug' => 'alert',
            'full_slug' => 'phb:alert',
        ]);

        $result = $this->service->applyFeatChoice($character, $feat);

        $this->assertEquals('feat', $result->choiceType);
        $this->assertEquals('phb:alert', $result->feat['slug']);
        $this->assertEquals('Alert', $result->feat['name']);
    }

    #[Test]
    public function it_rolls_back_on_failure(): void
    {
        $character = Character::factory()->create([
            'asi_choices_remaining' => 1,
            'strength' => 20, // Will fail when trying to add +1
        ]);

        $feat = Feat::factory()->create();
        $strength = AbilityScore::firstOrCreate(['code' => 'STR'], ['name' => 'Strength']);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $strength->id,
            'value' => '1',
        ]);

        try {
            $this->service->applyFeatChoice($character, $feat->fresh());
        } catch (AbilityScoreCapExceededException) {
            // Expected
        }

        // Nothing should have changed
        $this->assertEquals(1, $character->fresh()->asi_choices_remaining);
        $this->assertDatabaseMissing('character_features', [
            'character_id' => $character->id,
            'feature_id' => $feat->id,
        ]);
    }

    // =========================================================================
    // Ability Score Increase Tests
    // =========================================================================

    #[Test]
    public function it_applies_plus_two_to_single_ability(): void
    {
        $character = Character::factory()->create([
            'asi_choices_remaining' => 1,
            'strength' => 14,
        ]);

        $result = $this->service->applyAbilityIncrease($character, ['STR' => 2]);

        $this->assertEquals(16, $character->fresh()->strength);
        $this->assertEquals(['STR' => 2], $result->abilityIncreases);
        $this->assertEquals(16, $result->newAbilityScores['STR']);
        $this->assertEquals('ability_increase', $result->choiceType);
        $this->assertNull($result->feat);
    }

    #[Test]
    public function it_applies_plus_one_to_two_abilities(): void
    {
        $character = Character::factory()->create([
            'asi_choices_remaining' => 1,
            'strength' => 14,
            'constitution' => 14,
        ]);

        $result = $this->service->applyAbilityIncrease($character, ['STR' => 1, 'CON' => 1]);

        $character->refresh();
        $this->assertEquals(15, $character->strength);
        $this->assertEquals(15, $character->constitution);
        $this->assertEquals(['STR' => 1, 'CON' => 1], $result->abilityIncreases);
    }

    #[Test]
    public function it_throws_when_increase_total_not_two(): void
    {
        $character = Character::factory()->create([
            'asi_choices_remaining' => 1,
            'strength' => 14,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must total exactly 2');

        $this->service->applyAbilityIncrease($character, ['STR' => 1]); // Only 1 point
    }

    #[Test]
    public function it_throws_when_ability_increase_exceeds_cap(): void
    {
        $character = Character::factory()->create([
            'asi_choices_remaining' => 1,
            'strength' => 19,
        ]);

        $this->expectException(AbilityScoreCapExceededException::class);

        $this->service->applyAbilityIncrease($character, ['STR' => 2]); // Would be 21
    }

    #[Test]
    public function it_decrements_asi_choices_for_ability_increase(): void
    {
        $character = Character::factory()->create([
            'asi_choices_remaining' => 2,
            'strength' => 14,
        ]);

        $result = $this->service->applyAbilityIncrease($character, ['STR' => 2]);

        $this->assertEquals(1, $character->fresh()->asi_choices_remaining);
        $this->assertEquals(1, $result->asiChoicesRemaining);
    }

    #[Test]
    public function it_returns_correct_new_ability_scores(): void
    {
        $character = Character::factory()->create([
            'asi_choices_remaining' => 1,
            'strength' => 10,
            'dexterity' => 12,
            'constitution' => 14,
            'intelligence' => 8,
            'wisdom' => 16,
            'charisma' => 11,
        ]);

        $result = $this->service->applyAbilityIncrease($character, ['STR' => 1, 'DEX' => 1]);

        $this->assertEquals([
            'STR' => 11,
            'DEX' => 13,
            'CON' => 14,
            'INT' => 8,
            'WIS' => 16,
            'CHA' => 11,
        ], $result->newAbilityScores);
    }

    // =========================================================================
    // Retroactive HP Bonus Tests (Tough Feat)
    // =========================================================================

    #[Test]
    public function it_applies_retroactive_hp_when_granting_tough_feat(): void
    {
        // Create level 5 character
        $class = CharacterClass::factory()->create([
            'slug' => 'fighter',
            'hit_die' => 10,
        ]);

        $character = Character::factory()->create([
            'asi_choices_remaining' => 1,
            'max_hit_points' => 50,
            'current_hit_points' => 50,
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Create Tough feat with hit_points_per_level modifier
        $toughFeat = Feat::factory()->create([
            'slug' => 'tough',
            'name' => 'Tough',
        ]);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $toughFeat->id,
            'modifier_category' => 'hit_points_per_level',
            'value' => 2,
        ]);

        $result = $this->service->applyFeatChoice($character, $toughFeat->fresh());

        $character->refresh();
        // HP should increase by 2 × 5 levels = 10 HP
        $this->assertEquals(60, $character->max_hit_points);
        $this->assertEquals(60, $character->current_hit_points);
        $this->assertEquals(10, $result->hpBonus);
    }

    #[Test]
    public function it_does_not_change_hp_for_feats_without_hp_modifier(): void
    {
        // Create level 4 character
        $class = CharacterClass::factory()->create([
            'slug' => 'rogue',
            'hit_die' => 8,
        ]);

        $character = Character::factory()->create([
            'asi_choices_remaining' => 1,
            'max_hit_points' => 32,
            'current_hit_points' => 32,
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->full_slug,
            'level' => 4,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Create Alert feat (no HP modifier)
        $alertFeat = Feat::factory()->create([
            'slug' => 'alert',
            'name' => 'Alert',
        ]);

        $result = $this->service->applyFeatChoice($character, $alertFeat);

        $character->refresh();
        // HP should remain unchanged
        $this->assertEquals(32, $character->max_hit_points);
        $this->assertEquals(32, $character->current_hit_points);
        $this->assertEquals(0, $result->hpBonus);
    }

    #[Test]
    public function it_sets_feature_id_for_polymorphic_relationship(): void
    {
        $character = Character::factory()->create([
            'asi_choices_remaining' => 1,
        ]);
        $feat = Feat::factory()->create([
            'slug' => 'actor',
            'full_slug' => 'phb:actor',
            'name' => 'Actor',
        ]);

        $this->service->applyFeatChoice($character, $feat);

        $characterFeature = CharacterFeature::where('character_id', $character->id)
            ->where('feature_slug', $feat->full_slug)
            ->first();

        // Verify feature_id is set
        $this->assertNotNull($characterFeature->feature_id);
        $this->assertEquals($feat->id, $characterFeature->feature_id);

        // Verify polymorphic relationship resolves correctly
        $this->assertNotNull($characterFeature->feature);
        $this->assertInstanceOf(Feat::class, $characterFeature->feature);
        $this->assertEquals($feat->id, $characterFeature->feature->id);
    }
}
