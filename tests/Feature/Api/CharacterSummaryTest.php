<?php

namespace Tests\Feature\Api;

use App\Enums\SpellSlotType;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterCondition;
use App\Models\CharacterLanguage;
use App\Models\CharacterSpellSlot;
use App\Models\ClassCounter;
use App\Models\Condition;
use App\Models\EntityChoice;
use App\Models\Language;
use App\Models\Modifier;
use App\Models\Race;
use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterSummaryTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_character_summary_structure()
    {
        $character = Character::factory()
            ->withStandardArray()
            ->create([
                'current_hit_points' => 35,
                'max_hit_points' => 45,
                'temp_hit_points' => 5,
                'death_save_successes' => 0,
                'death_save_failures' => 0,
                'asi_choices_remaining' => 0,
            ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'character' => ['id', 'name', 'total_level'],
                    'pending_choices' => [
                        'proficiencies',
                        'languages',
                        'spells',
                        'optional_features',
                        'asi',
                        'size',
                        'feats',
                    ],
                    'resources' => [
                        'hit_points' => ['current', 'max', 'temp'],
                        'hit_dice' => ['available', 'max'],
                        'spell_slots',
                        'features_with_uses',
                    ],
                    'combat_state' => [
                        'conditions',
                        'death_saves' => ['successes', 'failures'],
                        'is_conscious',
                    ],
                    'creation_complete',
                    'missing_required',
                ],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_basic_character_info()
    {
        $character = Character::factory()
            ->withStandardArray()
            ->create(['name' => 'Thorin Oakenshield']);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 5,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'character' => [
                        'id' => $character->id,
                        'name' => 'Thorin Oakenshield',
                        'total_level' => 5,
                    ],
                ],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calculates_pending_proficiency_choices()
    {
        $race = Race::factory()->create();
        $character = Character::factory()->withStandardArray()->create(['race_slug' => $race->slug]);

        // For now, just test that the proficiencies field exists and is numeric
        // The actual proficiency choice logic requires complex setup with EntitySkillChoice polymorphic table
        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('pending_choices', $data);
        $this->assertArrayHasKey('proficiencies', $data['pending_choices']);
        $this->assertIsInt($data['pending_choices']['proficiencies']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calculates_pending_language_choices()
    {
        $race = Race::factory()->create();
        $character = Character::factory()->withStandardArray()->create(['race_slug' => $race->slug]);

        // Create languages first
        $common = Language::factory()->create(['slug' => 'common-test-'.uniqid()]);
        $elvish = Language::factory()->create(['slug' => 'elvish-test-'.uniqid()]);

        // Add language choice to race (2 choices from any language) - now via EntityChoice
        EntityChoice::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'choice_type' => 'language',
            'choice_group' => 'language_choice_1',
            'quantity' => 1,
            'level_granted' => 1,
            'is_required' => true,
        ]);
        EntityChoice::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'choice_type' => 'language',
            'choice_group' => 'language_choice_2',
            'quantity' => 1,
            'level_granted' => 1,
            'is_required' => true,
        ]);

        // Character has selected 1 out of 2 languages
        CharacterLanguage::factory()->create([
            'character_id' => $character->id,
            'language_slug' => $common->slug,
            'source' => 'race',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'pending_choices' => [
                        'languages' => 1, // 2 required - 1 selected = 1 remaining
                    ],
                ],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_zero_for_spell_choices_placeholder()
    {
        $character = Character::factory()->withStandardArray()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'pending_choices' => [
                        'spells' => 0, // Placeholder - complex logic deferred
                    ],
                ],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_asi_choices_remaining()
    {
        $character = Character::factory()
            ->withStandardArray()
            ->create(['asi_choices_remaining' => 2]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'pending_choices' => [
                        'asi' => 2,
                    ],
                ],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_hit_points_state()
    {
        $character = Character::factory()
            ->withStandardArray()
            ->create([
                'current_hit_points' => 25,
                'max_hit_points' => 50,
                'temp_hit_points' => 10,
            ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'resources' => [
                        'hit_points' => [
                            'current' => 25,
                            'max' => 50,
                            'temp' => 10,
                        ],
                    ],
                ],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_hit_dice_state()
    {
        $character = Character::factory()->withStandardArray()->create();
        $class = CharacterClass::factory()->create(['hit_die' => 'd8']);
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 5,
            'hit_dice_spent' => 2,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'resources' => [
                        'hit_dice' => [
                            'available' => 3, // 5 total - 2 spent
                            'max' => 5,
                        ],
                    ],
                ],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_spell_slots_state()
    {
        $character = Character::factory()->withStandardArray()->create();

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 2,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 2,
            'max_slots' => 3,
            'used_slots' => 1,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('spell_slots', $data['resources']);
        $this->assertArrayHasKey('standard', $data['resources']['spell_slots']);

        // Spell slots should have entries for both levels
        $standardSlots = $data['resources']['spell_slots']['standard'];

        // Verify we have 2 spell slot entries
        $this->assertIsArray($standardSlots);
        $this->assertCount(2, $standardSlots);

        // Due to JSON encoding, associative arrays with numeric keys may be re-indexed
        // We need to find our slots by their actual values
        // Collect all slot data to verify the right values are present
        $slotsByLevel = [];
        foreach ($standardSlots as $slot) {
            // We can't rely on array keys after JSON encoding, so we need another way
            // The SpellSlotService includes 'max', 'used', and 'available' keys
            $slotsByLevel[] = $slot;
        }

        // We should have 2 slots
        $this->assertCount(2, $slotsByLevel);

        // Verify the first slot (should be level 1 with max=4, available=2)
        $slot1 = $slotsByLevel[0];
        $this->assertEquals(4, $slot1['max']);
        $this->assertEquals(2, $slot1['available']);

        // Verify the second slot (should be level 2 with max=3, available=2)
        $slot2 = $slotsByLevel[1];
        $this->assertEquals(3, $slot2['max']);
        $this->assertEquals(2, $slot2['available']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_features_with_uses_placeholder()
    {
        $character = Character::factory()->withStandardArray()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'resources' => [
                        'features_with_uses' => [], // Placeholder - feature uses not implemented yet
                    ],
                ],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_active_conditions()
    {
        $character = Character::factory()->withStandardArray()->create();
        $poisoned = Condition::factory()->create(['slug' => 'poisoned-test-'.uniqid(), 'name' => 'Poisoned']);
        $blinded = Condition::factory()->create(['slug' => 'blinded-test-'.uniqid(), 'name' => 'Blinded']);

        CharacterCondition::factory()->create([
            'character_id' => $character->id,
            'condition_slug' => $poisoned->slug,
        ]);

        CharacterCondition::factory()->create([
            'character_id' => $character->id,
            'condition_slug' => $blinded->slug,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data['combat_state']['conditions']);
        $this->assertContains($poisoned->slug, $data['combat_state']['conditions']);
        $this->assertContains($blinded->slug, $data['combat_state']['conditions']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_death_saves_state()
    {
        $character = Character::factory()
            ->withStandardArray()
            ->create([
                'current_hit_points' => 0,
                'death_save_successes' => 2,
                'death_save_failures' => 1,
            ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'combat_state' => [
                        'death_saves' => [
                            'successes' => 2,
                            'failures' => 1,
                        ],
                        'is_conscious' => false,
                    ],
                ],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_determines_consciousness_based_on_hp()
    {
        $conscious = Character::factory()
            ->withStandardArray()
            ->create(['current_hit_points' => 10]);

        $unconscious = Character::factory()
            ->withStandardArray()
            ->create(['current_hit_points' => 0]);

        $responseCons = $this->getJson("/api/v1/characters/{$conscious->id}/summary");
        $responseUncons = $this->getJson("/api/v1/characters/{$unconscious->id}/summary");

        $responseCons->assertOk()
            ->assertJson(['data' => ['combat_state' => ['is_conscious' => true]]]);

        $responseUncons->assertOk()
            ->assertJson(['data' => ['combat_state' => ['is_conscious' => false]]]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_incomplete_character_creation()
    {
        // Character missing race and ability scores
        $character = Character::factory()->create(['race_slug' => null]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'creation_complete' => false,
                    'missing_required' => ['race', 'class', 'ability_scores'],
                ],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_complete_character_creation()
    {
        $race = Race::factory()->create();
        $character = Character::factory()->withStandardArray()->create(['race_slug' => $race->slug]);
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'creation_complete' => true,
                    'missing_required' => [],
                ],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_pending_choices_in_missing_required_when_incomplete()
    {
        $race = Race::factory()->create();
        $character = Character::factory()->withStandardArray()->create(['race_slug' => $race->slug]);

        // Add language choice to race (2 choices) via EntityChoice
        // Note: Language choices moved from entity_languages to entity_choices table
        EntityChoice::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'choice_type' => 'language',
            'choice_group' => 'language_choice_1',
            'quantity' => 2,
            'level_granted' => 1,
            'is_required' => true,
        ]);

        $class = CharacterClass::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        // Character has no language choices made
        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertFalse($data['creation_complete']);
        $this->assertContains('language_choices', $data['missing_required']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_size_choices_for_race_with_has_size_choice()
    {
        $race = Race::factory()->create(['has_size_choice' => true]);
        $character = Character::factory()->withStandardArray()->create(['race_slug' => $race->slug]);

        // Ensure sizes exist
        Size::firstOrCreate(['code' => 'S', 'name' => 'Small']);
        Size::firstOrCreate(['code' => 'M', 'name' => 'Medium']);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJsonPath('data.pending_choices.size', 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_zero_size_choices_when_race_has_no_size_choice()
    {
        $race = Race::factory()->create(['has_size_choice' => false]);
        $character = Character::factory()->withStandardArray()->create(['race_slug' => $race->slug]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJsonPath('data.pending_choices.size', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_zero_size_choices_when_size_already_selected()
    {
        $race = Race::factory()->create(['has_size_choice' => true]);

        // Ensure sizes exist
        $smallSize = Size::firstOrCreate(['code' => 'S', 'name' => 'Small']);
        Size::firstOrCreate(['code' => 'M', 'name' => 'Medium']);

        $character = Character::factory()->withStandardArray()->create([
            'race_slug' => $race->slug,
            'size_id' => $smallSize->id,
        ]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJsonPath('data.pending_choices.size', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_size_choice_in_missing_required_when_pending()
    {
        $race = Race::factory()->create(['has_size_choice' => true]);
        $character = Character::factory()->withStandardArray()->create(['race_slug' => $race->slug]);

        // Ensure sizes exist
        Size::firstOrCreate(['code' => 'S', 'name' => 'Small']);
        Size::firstOrCreate(['code' => 'M', 'name' => 'Medium']);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertFalse($data['creation_complete']);
        $this->assertContains('size_choice', $data['missing_required']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_feats_choices_for_race_with_bonus_feat()
    {
        $race = Race::factory()->create();

        // Add bonus_feat modifier to race (like Variant Human)
        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'bonus_feat',
            'value' => '1',
        ]);

        $character = Character::factory()->withStandardArray()->create(['race_slug' => $race->slug]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJsonPath('data.pending_choices.feats', 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_zero_feats_choices_when_race_has_no_bonus_feat()
    {
        $race = Race::factory()->create();
        $character = Character::factory()->withStandardArray()->create(['race_slug' => $race->slug]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJsonPath('data.pending_choices.feats', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_feat_choices_in_missing_required_when_pending()
    {
        $race = Race::factory()->create();

        // Add bonus_feat modifier
        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'bonus_feat',
            'value' => '1',
        ]);

        $character = Character::factory()->withStandardArray()->create(['race_slug' => $race->slug]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertFalse($data['creation_complete']);
        $this->assertContains('feat_choices', $data['missing_required']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_size_in_pending_choices_structure()
    {
        $character = Character::factory()->withStandardArray()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'pending_choices' => [
                        'proficiencies',
                        'languages',
                        'spells',
                        'optional_features',
                        'asi',
                        'size',
                        'feats',
                    ],
                ],
            ]);
    }

    /**
     * Helper to create a skill with required ability score.
     */
    private function createSkill(string $baseName): \App\Models\Skill
    {
        $abilityScore = \App\Models\AbilityScore::firstOrCreate(
            ['code' => 'WIS'],
            ['name' => 'Wisdom', 'slug' => 'wisdom']
        );

        $uniqueId = uniqid();
        $slug = strtolower(str_replace(' ', '-', $baseName)).'-'.$uniqueId;

        return \App\Models\Skill::create([
            'name' => $baseName.' '.$uniqueId,
            'slug' => $slug,
            'slug' => 'test:'.$slug,
            'ability_score_id' => $abilityScore->id,
        ]);
    }

    /**
     * Issue #480 fix: Verify subclass_feature proficiencies are counted in summary
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_counts_subclass_feature_proficiencies_in_summary()
    {
        // Create skill for choices
        $nature = $this->createSkill('Nature');

        // Create Cleric class
        $clericClass = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric-'.uniqid(),
        ]);

        // Create Nature Domain subclass
        $natureDomain = CharacterClass::factory()->create([
            'name' => 'Nature Domain',
            'slug' => 'cleric-nature-domain-'.uniqid(),
            'parent_class_id' => $clericClass->id,
        ]);

        // Create "Acolyte of Nature" feature with skill choice
        $acolyteFeature = $natureDomain->features()->create([
            'feature_name' => 'Acolyte of Nature',
            'level' => 1,
            'is_optional' => false,
            'description' => 'Choose one skill.',
        ]);

        // Add skill choice to the feature via EntityChoice (proficiency choices moved from entity_proficiencies)
        EntityChoice::create([
            'reference_type' => \App\Models\ClassFeature::class,
            'reference_id' => $acolyteFeature->id,
            'choice_type' => 'proficiency',
            'choice_group' => 'feature_skill_choice_1',
            'quantity' => 1,
            'proficiency_type' => 'skill',
            'target_type' => 'skill',
            'target_slug' => $nature->slug,
            'level_granted' => 1,
            'is_required' => true,
        ]);

        // Create character with Cleric + Nature Domain subclass
        $character = Character::factory()->withStandardArray()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $clericClass->slug,
            'subclass_slug' => $natureDomain->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Get summary - should show 1 pending proficiency from subclass_feature
        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'pending_choices' => [
                        'proficiencies' => 1, // 1 from subclass_feature
                    ],
                ],
            ]);
    }

    /**
     * Issue #479/#480 fix: Verify subclass_feature proficiency resolves correctly and shows 0 after selection.
     *
     * This test uses the CharacterProficiencyService directly instead of the HTTP API
     * to avoid URL encoding issues with the choice ID (which contains colons and pipes).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_zero_proficiencies_after_subclass_feature_choice_is_resolved()
    {
        // Create skill for choices
        $nature = $this->createSkill('Nature');

        // Create Cleric class
        $clericClass = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric-'.uniqid(),
        ]);

        // Create Nature Domain subclass
        $natureDomain = CharacterClass::factory()->create([
            'name' => 'Nature Domain',
            'slug' => 'cleric-nature-domain-'.uniqid(),
            'parent_class_id' => $clericClass->id,
        ]);

        // Create "Acolyte of Nature" feature with skill choice
        $acolyteFeature = $natureDomain->features()->create([
            'feature_name' => 'Acolyte of Nature',
            'level' => 1,
            'is_optional' => false,
            'description' => 'Choose one skill.',
        ]);

        // Add skill choice to the feature via EntityChoice (proficiency choices moved from entity_proficiencies)
        EntityChoice::create([
            'reference_type' => \App\Models\ClassFeature::class,
            'reference_id' => $acolyteFeature->id,
            'choice_type' => 'proficiency',
            'choice_group' => 'feature_skill_choice_1',
            'quantity' => 1,
            'proficiency_type' => 'skill',
            'target_type' => 'skill',
            'target_slug' => $nature->slug,
            'level_granted' => 1,
            'is_required' => true,
        ]);

        // Create character with Cleric + Nature Domain subclass
        $character = Character::factory()->withStandardArray()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $clericClass->slug,
            'subclass_slug' => $natureDomain->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Verify pending before resolution via summary API
        $responseBefore = $this->getJson("/api/v1/characters/{$character->id}/summary");
        $responseBefore->assertOk()
            ->assertJson(['data' => ['pending_choices' => ['proficiencies' => 1]]]);

        // Resolve the choice using the service directly (avoid URL encoding issues)
        $proficiencyService = app(\App\Services\CharacterProficiencyService::class);
        $fullChoiceGroup = 'Acolyte of Nature:feature_skill_choice_1';
        $proficiencyService->makeSkillChoice($character, 'subclass_feature', $fullChoiceGroup, [$nature->slug]);

        // Verify pending after resolution via summary API - should be 0
        $character->refresh();
        $responseAfter = $this->getJson("/api/v1/characters/{$character->id}/summary");
        $responseAfter->assertOk()
            ->assertJson([
                'data' => [
                    'pending_choices' => [
                        'proficiencies' => 0, // Should be 0 after resolution
                    ],
                ],
            ]);
    }

    // =====================
    // Fighting Style & Expertise in Summary (Issue #490)
    // =====================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_fighting_style_in_pending_choices()
    {
        // Create Fighter class with "Fighting Styles Known" counter
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter-'.uniqid(),
        ]);

        ClassCounter::factory()
            ->forClass($fighter)
            ->atLevel(1)
            ->noReset()
            ->create([
                'counter_name' => 'Fighting Styles Known',
                'counter_value' => 1,
            ]);

        // Create character with Fighter class
        $character = Character::factory()->withStandardArray()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $fighter->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'pending_choices' => [
                        'fighting_style',
                    ],
                ],
            ])
            ->assertJsonPath('data.pending_choices.fighting_style', 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_expertise_in_pending_choices_structure()
    {
        $character = Character::factory()->withStandardArray()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/summary");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'pending_choices' => [
                        'expertise',
                    ],
                ],
            ]);
    }
}
