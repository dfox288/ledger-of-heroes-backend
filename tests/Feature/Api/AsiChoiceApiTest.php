<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterFeature;
use App\Models\EntityPrerequisite;
use App\Models\Feat;
use App\Models\Modifier;
use App\Models\Proficiency;
use App\Models\Race;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AsiChoiceApiTest extends TestCase
{
    use RefreshDatabase;

    private function createCompleteCharacter(array $options = []): Character
    {
        $class = CharacterClass::factory()->create(['hit_die' => 8]);
        $race = Race::factory()->create();

        // Extract ability scores from options (use uppercase codes)
        $abilityScores = [
            'STR' => $options['STR'] ?? 14,
            'DEX' => $options['DEX'] ?? 14,
            'CON' => $options['CON'] ?? 14,
            'INT' => $options['INT'] ?? 10,
            'WIS' => $options['WIS'] ?? 10,
            'CHA' => $options['CHA'] ?? 10,
        ];

        // Remove ability codes from options before passing to create()
        $createAttributes = array_diff_key($options, array_flip(['STR', 'DEX', 'CON', 'INT', 'WIS', 'CHA']));

        return Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->withAbilityScores($abilityScores)
            ->withHitPoints(10)
            ->create(array_merge(['asi_choices_remaining' => 1], $createAttributes));
    }

    // =========================================================================
    // Feat Choice Tests
    // =========================================================================

    #[Test]
    public function it_applies_feat_choice_successfully(): void
    {
        $character = $this->createCompleteCharacter();
        $feat = Feat::factory()->create(['name' => 'Alert', 'slug' => 'alert']);

        $response = $this->postJson("/api/v1/characters/{$character->id}/asi-choice", [
            'choice_type' => 'feat',
            'feat_id' => $feat->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.choice_type', 'feat')
            ->assertJsonPath('data.asi_choices_remaining', 0)
            ->assertJsonPath('data.changes.feat.name', 'Alert')
            ->assertJsonPath('data.changes.feat.slug', 'alert');

        $this->assertEquals(0, $character->fresh()->asi_choices_remaining);
        $this->assertDatabaseHas('character_features', [
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat->id,
        ]);
    }

    #[Test]
    public function it_applies_half_feat_with_ability_increase(): void
    {
        $character = $this->createCompleteCharacter(['CON' => 14]);
        $feat = Feat::factory()->create(['name' => 'Resilient (Constitution)', 'slug' => 'resilient-constitution']);

        $constitution = AbilityScore::firstOrCreate(['code' => 'CON'], ['name' => 'Constitution']);
        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $constitution->id,
            'value' => '1',
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/asi-choice", [
            'choice_type' => 'feat',
            'feat_id' => $feat->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.changes.ability_increases.CON', 1)
            ->assertJsonPath('data.new_ability_scores.CON', 15);

        $this->assertEquals(15, $character->fresh()->constitution);
    }

    #[Test]
    public function it_grants_proficiencies_from_feat(): void
    {
        $character = $this->createCompleteCharacter();
        $feat = Feat::factory()->create(['name' => 'Heavily Armored']);

        Proficiency::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'proficiency_type' => 'armor',
            'proficiency_name' => 'Heavy Armor',
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/asi-choice", [
            'choice_type' => 'feat',
            'feat_id' => $feat->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.changes.proficiencies_gained.0', 'Heavy Armor');
    }

    #[Test]
    public function it_grants_spells_from_feat(): void
    {
        $character = $this->createCompleteCharacter();
        $spell = Spell::factory()->create(['name' => 'Fire Bolt', 'slug' => 'fire-bolt']);
        $feat = Feat::factory()->create(['name' => 'Magic Initiate']);
        $feat->spells()->attach($spell->id);

        $response = $this->postJson("/api/v1/characters/{$character->id}/asi-choice", [
            'choice_type' => 'feat',
            'feat_id' => $feat->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.changes.spells_gained.0.name', 'Fire Bolt');

        $this->assertDatabaseHas('character_spells', [
            'character_id' => $character->id,
            'spell_id' => $spell->id,
        ]);
    }

    #[Test]
    public function it_returns_422_when_no_asi_choices_remaining(): void
    {
        $character = $this->createCompleteCharacter(['asi_choices_remaining' => 0]);
        $feat = Feat::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/asi-choice", [
            'choice_type' => 'feat',
            'feat_id' => $feat->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'No ASI choices remaining. Level up to gain more.');
    }

    #[Test]
    public function it_returns_422_when_feat_already_taken(): void
    {
        $character = $this->createCompleteCharacter();
        $feat = Feat::factory()->create(['name' => 'Alert']);

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat->id,
            'source' => 'feat',
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/asi-choice", [
            'choice_type' => 'feat',
            'feat_id' => $feat->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Character has already taken this feat.');
    }

    #[Test]
    public function it_returns_422_when_prerequisites_not_met(): void
    {
        $character = $this->createCompleteCharacter(['STR' => 10]);
        $feat = Feat::factory()->create(['name' => 'Heavy Armor Master']);

        $strength = AbilityScore::firstOrCreate(['code' => 'STR'], ['name' => 'Strength']);
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/asi-choice", [
            'choice_type' => 'feat',
            'feat_id' => $feat->fresh()->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Character does not meet feat prerequisites.')
            ->assertJsonStructure(['unmet_prerequisites']);
    }

    #[Test]
    public function it_returns_422_when_feat_not_found(): void
    {
        $character = $this->createCompleteCharacter();

        $response = $this->postJson("/api/v1/characters/{$character->id}/asi-choice", [
            'choice_type' => 'feat',
            'feat_id' => 99999,
        ]);

        $response->assertStatus(422); // Validation error
    }

    // =========================================================================
    // Ability Score Increase Tests
    // =========================================================================

    #[Test]
    public function it_applies_ability_increase_plus_two(): void
    {
        $character = $this->createCompleteCharacter(['STR' => 14]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/asi-choice", [
            'choice_type' => 'ability_increase',
            'ability_increases' => ['STR' => 2],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.choice_type', 'ability_increase')
            ->assertJsonPath('data.changes.ability_increases.STR', 2)
            ->assertJsonPath('data.new_ability_scores.STR', 16)
            ->assertJsonPath('data.changes.feat', null);

        $this->assertEquals(16, $character->fresh()->strength);
    }

    #[Test]
    public function it_applies_ability_increase_plus_one_to_two(): void
    {
        $character = $this->createCompleteCharacter(['STR' => 14, 'CON' => 14]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/asi-choice", [
            'choice_type' => 'ability_increase',
            'ability_increases' => ['STR' => 1, 'CON' => 1],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.changes.ability_increases.STR', 1)
            ->assertJsonPath('data.changes.ability_increases.CON', 1)
            ->assertJsonPath('data.new_ability_scores.STR', 15)
            ->assertJsonPath('data.new_ability_scores.CON', 15);

        $character->refresh();
        $this->assertEquals(15, $character->strength);
        $this->assertEquals(15, $character->constitution);
    }

    #[Test]
    public function it_returns_422_when_ability_exceeds_cap(): void
    {
        $character = $this->createCompleteCharacter(['STR' => 19]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/asi-choice", [
            'choice_type' => 'ability_increase',
            'ability_increases' => ['STR' => 2],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Ability score cannot exceed 20.');
    }

    #[Test]
    public function it_returns_422_when_increase_total_not_two(): void
    {
        $character = $this->createCompleteCharacter();

        $response = $this->postJson("/api/v1/characters/{$character->id}/asi-choice", [
            'choice_type' => 'ability_increase',
            'ability_increases' => ['STR' => 1], // Only 1 point
        ]);

        $response->assertStatus(422);
    }

    // =========================================================================
    // Validation Tests
    // =========================================================================

    #[Test]
    public function it_validates_request_structure(): void
    {
        $character = $this->createCompleteCharacter();

        // Missing choice_type
        $response = $this->postJson("/api/v1/characters/{$character->id}/asi-choice", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['choice_type']);
    }

    #[Test]
    public function it_returns_404_for_nonexistent_character(): void
    {
        $feat = Feat::factory()->create();

        $response = $this->postJson('/api/v1/characters/99999/asi-choice', [
            'choice_type' => 'feat',
            'feat_id' => $feat->id,
        ]);

        $response->assertStatus(404);
    }
}
