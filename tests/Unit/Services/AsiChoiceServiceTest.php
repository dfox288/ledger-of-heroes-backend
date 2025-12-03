<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AbilityScoreCapExceededException;
use App\Exceptions\FeatAlreadyTakenException;
use App\Exceptions\NoAsiChoicesRemainingException;
use App\Exceptions\PrerequisitesNotMetException;
use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterFeature;
use App\Models\EntityPrerequisite;
use App\Models\Feat;
use App\Models\Modifier;
use App\Models\Proficiency;
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

        // Character already has this feat
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat->id,
            'source' => 'feat',
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
            'feature_id' => $feat->id,
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

        $feat = Feat::factory()->create();
        Proficiency::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'proficiency_type' => 'saving_throw',
            'proficiency_name' => 'Constitution Saves',
        ]);

        $result = $this->service->applyFeatChoice($character, $feat->fresh());

        $this->assertDatabaseHas('character_proficiencies', [
            'character_id' => $character->id,
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

        $spell = Spell::factory()->create(['name' => 'Firebolt', 'slug' => 'firebolt']);
        $feat = Feat::factory()->create();
        $feat->spells()->attach($spell->id);

        $result = $this->service->applyFeatChoice($character, $feat->fresh());

        $this->assertDatabaseHas('character_spells', [
            'character_id' => $character->id,
            'spell_id' => $spell->id,
            'source' => 'feat',
        ]);
        $this->assertCount(1, $result->spellsGained);
        $this->assertEquals('Firebolt', $result->spellsGained[0]['name']);
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
        ]);

        $result = $this->service->applyFeatChoice($character, $feat);

        $this->assertEquals('feat', $result->choiceType);
        $this->assertEquals($feat->id, $result->feat['id']);
        $this->assertEquals('Alert', $result->feat['name']);
        $this->assertEquals('alert', $result->feat['slug']);
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
}
