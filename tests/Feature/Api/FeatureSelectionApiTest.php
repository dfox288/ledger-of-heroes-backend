<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\FeatureSelection;
use App\Models\OptionalFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatureSelectionApiTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // INDEX ENDPOINT TESTS
    // ==========================================

    #[Test]
    public function it_returns_empty_array_when_character_has_no_feature_selections(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/feature-selections");

        $response->assertOk()
            ->assertJson(['data' => []]);
    }

    #[Test]
    public function it_lists_all_feature_selections_for_character(): void
    {
        $character = Character::factory()->create();
        $feature1 = OptionalFeature::factory()->maneuver()->create(['name' => 'Riposte']);
        $feature2 = OptionalFeature::factory()->maneuver()->create(['name' => 'Parry']);

        FeatureSelection::factory()
            ->for($character)
            ->withFeature($feature1)
            ->atLevel(3)
            ->create();

        FeatureSelection::factory()
            ->for($character)
            ->withFeature($feature2)
            ->atLevel(3)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/feature-selections");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.optional_feature.name', 'Riposte')
            ->assertJsonPath('data.1.optional_feature.name', 'Parry');
    }

    // ==========================================
    // AVAILABLE ENDPOINT TESTS
    // ==========================================

    #[Test]
    public function it_returns_available_feature_selections_for_character_class(): void
    {
        // Create a Fighter class with unique slug
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter-available-test',
        ]);

        // Create a character with Fighter class
        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_id' => $fighterClass->id,
            'level' => 3,
            'is_starting_class' => true,
        ]);

        // Create optional features - one for Fighter, one for another class
        $maneuver = OptionalFeature::factory()
            ->maneuver()
            ->create([
                'name' => 'Trip Attack',
                'level_requirement' => null,
            ]);
        $maneuver->classes()->attach($fighterClass->id);

        $wizardClass = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'wizard-available-test',
        ]);
        $wizardFeature = OptionalFeature::factory()
            ->create([
                'name' => 'Wizard Only Feature',
                'level_requirement' => null,
            ]);
        $wizardFeature->classes()->attach($wizardClass->id);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feature-selections");

        $response->assertOk();

        // Should include Fighter's maneuver
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Trip Attack', $names);
        $this->assertNotContains('Wizard Only Feature', $names);
    }

    #[Test]
    public function it_excludes_already_selected_features_from_available(): void
    {
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter-excludes-test',
        ]);

        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_id' => $fighterClass->id,
            'level' => 3,
            'is_starting_class' => true,
        ]);

        // Create features and explicitly attach to class
        $selectedFeature = OptionalFeature::factory()
            ->maneuver()
            ->create([
                'name' => 'Already Selected',
                'level_requirement' => null,
            ]);
        $selectedFeature->classes()->attach($fighterClass->id);

        $availableFeature = OptionalFeature::factory()
            ->maneuver()
            ->create([
                'name' => 'Still Available',
                'level_requirement' => null,
            ]);
        $availableFeature->classes()->attach($fighterClass->id);

        // Select one feature
        FeatureSelection::factory()
            ->for($character)
            ->withFeature($selectedFeature)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feature-selections");

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertNotContains('Already Selected', $names);
        $this->assertContains('Still Available', $names);
    }

    #[Test]
    public function it_respects_level_requirements_for_available_features(): void
    {
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter-level-req-test',
        ]);

        // Level 3 character
        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_id' => $fighterClass->id,
            'level' => 3,
            'is_starting_class' => true,
        ]);

        // Feature requiring level 5
        $highLevelFeature = OptionalFeature::factory()
            ->maneuver()
            ->create([
                'name' => 'High Level Only',
                'level_requirement' => 5,
            ]);
        $highLevelFeature->classes()->attach($fighterClass->id);

        // Feature with no level requirement
        $noRequirement = OptionalFeature::factory()
            ->maneuver()
            ->create([
                'name' => 'No Requirement',
                'level_requirement' => null,
            ]);
        $noRequirement->classes()->attach($fighterClass->id);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feature-selections");

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertNotContains('High Level Only', $names);
        $this->assertContains('No Requirement', $names);
    }

    #[Test]
    public function it_filters_available_features_by_feature_type(): void
    {
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter-filter-type-test',
        ]);

        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_id' => $fighterClass->id,
            'level' => 3,
            'is_starting_class' => true,
        ]);

        $maneuver = OptionalFeature::factory()
            ->maneuver()
            ->create([
                'name' => 'Maneuver Feature',
                'level_requirement' => null,
            ]);
        $maneuver->classes()->attach($fighterClass->id);

        $fightingStyle = OptionalFeature::factory()
            ->fightingStyle()
            ->create([
                'name' => 'Fighting Style Feature',
                'level_requirement' => null,
            ]);
        $fightingStyle->classes()->attach($fighterClass->id);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feature-selections?feature_type=maneuver");

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Maneuver Feature', $names);
        $this->assertNotContains('Fighting Style Feature', $names);
    }

    // ==========================================
    // CHOICES ENDPOINT TESTS
    // ==========================================

    #[Test]
    public function it_returns_pending_choices_based_on_class_counters(): void
    {
        // Create Fighter class with Battle Master subclass that has Maneuvers Known counter
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter-choices-test',
        ]);
        $battleMaster = CharacterClass::factory()->create([
            'name' => 'Battle Master',
            'slug' => 'fighter-choices-test-battle-master',
            'parent_class_id' => $fighterClass->id,
        ]);

        // Add "Maneuvers Known" counter to Battle Master at level 3 with value 3
        $battleMaster->counters()->create([
            'counter_name' => 'Maneuvers Known',
            'level' => 3,
            'counter_value' => 3,
        ]);

        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_id' => $fighterClass->id,
            'subclass_id' => $battleMaster->id,
            'level' => 3,
            'is_starting_class' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/feature-selection-choices");

        $response->assertOk();

        $choices = $response->json('data');
        $maneuverChoice = collect($choices)->firstWhere('feature_type', 'maneuver');

        $this->assertNotNull($maneuverChoice);
        $this->assertEquals(3, $maneuverChoice['allowed']);
        $this->assertEquals(0, $maneuverChoice['selected']);
        $this->assertEquals(3, $maneuverChoice['remaining']);
    }

    #[Test]
    public function it_calculates_remaining_choices_correctly(): void
    {
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter-remaining-test',
        ]);
        $battleMaster = CharacterClass::factory()->create([
            'name' => 'Battle Master',
            'slug' => 'fighter-remaining-test-battle-master',
            'parent_class_id' => $fighterClass->id,
        ]);

        $battleMaster->counters()->create([
            'counter_name' => 'Maneuvers Known',
            'level' => 3,
            'counter_value' => 3,
        ]);

        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_id' => $fighterClass->id,
            'subclass_id' => $battleMaster->id,
            'level' => 3,
            'is_starting_class' => true,
        ]);

        // Select 2 maneuvers
        $maneuver1 = OptionalFeature::factory()->maneuver()->create();
        $maneuver2 = OptionalFeature::factory()->maneuver()->create();

        FeatureSelection::factory()->for($character)->withFeature($maneuver1)->create();
        FeatureSelection::factory()->for($character)->withFeature($maneuver2)->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/feature-selection-choices");

        $response->assertOk();

        $choices = $response->json('data');
        $maneuverChoice = collect($choices)->firstWhere('feature_type', 'maneuver');

        $this->assertNotNull($maneuverChoice);
        $this->assertEquals(3, $maneuverChoice['allowed']);
        $this->assertEquals(2, $maneuverChoice['selected']);
        $this->assertEquals(1, $maneuverChoice['remaining']);
    }

    // ==========================================
    // STORE ENDPOINT TESTS
    // ==========================================

    #[Test]
    public function it_adds_feature_selection_to_character(): void
    {
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter-store-test',
        ]);

        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_id' => $fighterClass->id,
            'level' => 3,
            'is_starting_class' => true,
        ]);

        $maneuver = OptionalFeature::factory()
            ->maneuver()
            ->forClass($fighterClass)
            ->create([
                'name' => 'Riposte',
                'level_requirement' => null,
            ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/feature-selections", [
            'optional_feature_id' => $maneuver->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.optional_feature.name', 'Riposte')
            ->assertJsonPath('data.level_acquired', 3);

        $this->assertDatabaseHas('feature_selections', [
            'character_id' => $character->id,
            'optional_feature_id' => $maneuver->id,
        ]);
    }

    #[Test]
    public function it_prevents_duplicate_feature_selection(): void
    {
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter-duplicate-test',
        ]);

        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_id' => $fighterClass->id,
            'level' => 3,
            'is_starting_class' => true,
        ]);

        $maneuver = OptionalFeature::factory()
            ->maneuver()
            ->forClass($fighterClass)
            ->create(['level_requirement' => null]);

        // Select the feature once
        FeatureSelection::factory()
            ->for($character)
            ->withFeature($maneuver)
            ->create();

        // Try to select again
        $response = $this->postJson("/api/v1/characters/{$character->id}/feature-selections", [
            'optional_feature_id' => $maneuver->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('optional_feature_id');
    }

    #[Test]
    public function it_validates_level_requirement_on_store(): void
    {
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter-level-store-test',
        ]);

        // Level 3 character
        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_id' => $fighterClass->id,
            'level' => 3,
            'is_starting_class' => true,
        ]);

        // Feature requiring level 5
        $highLevelFeature = OptionalFeature::factory()
            ->maneuver()
            ->forClass($fighterClass)
            ->create(['level_requirement' => 5]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/feature-selections", [
            'optional_feature_id' => $highLevelFeature->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('optional_feature_id');
    }

    #[Test]
    public function it_validates_class_eligibility_on_store(): void
    {
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter-eligibility-test',
        ]);
        $wizardClass = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'wizard-eligibility-test',
        ]);

        // Fighter character
        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_id' => $fighterClass->id,
            'level' => 3,
            'is_starting_class' => true,
        ]);

        // Wizard-only feature
        $wizardFeature = OptionalFeature::factory()
            ->forClass($wizardClass)
            ->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/feature-selections", [
            'optional_feature_id' => $wizardFeature->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('optional_feature_id');
    }

    #[Test]
    public function it_allows_specifying_class_and_subclass_on_store(): void
    {
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter-subclass-store-test',
        ]);
        $battleMaster = CharacterClass::factory()->create([
            'name' => 'Battle Master',
            'slug' => 'fighter-subclass-store-test-battle-master',
            'parent_class_id' => $fighterClass->id,
        ]);

        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_id' => $fighterClass->id,
            'subclass_id' => $battleMaster->id,
            'level' => 3,
            'is_starting_class' => true,
        ]);

        $maneuver = OptionalFeature::factory()
            ->maneuver()
            ->forClass($fighterClass)
            ->create(['level_requirement' => null]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/feature-selections", [
            'optional_feature_id' => $maneuver->id,
            'class_id' => $fighterClass->id,
            'subclass_name' => 'Battle Master',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.class.name', 'Fighter')
            ->assertJsonPath('data.subclass_name', 'Battle Master');
    }

    #[Test]
    public function it_allows_specifying_level_acquired_on_store(): void
    {
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter-level-acquired-test',
        ]);

        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_id' => $fighterClass->id,
            'level' => 5,
            'is_starting_class' => true,
        ]);

        $maneuver = OptionalFeature::factory()
            ->maneuver()
            ->forClass($fighterClass)
            ->create(['level_requirement' => null]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/feature-selections", [
            'optional_feature_id' => $maneuver->id,
            'level_acquired' => 3,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.level_acquired', 3);
    }

    // ==========================================
    // DESTROY ENDPOINT TESTS
    // ==========================================

    #[Test]
    public function it_removes_feature_selection_from_character(): void
    {
        $character = Character::factory()->create();
        $feature = OptionalFeature::factory()->maneuver()->create();

        FeatureSelection::factory()
            ->for($character)
            ->withFeature($feature)
            ->create();

        $this->assertDatabaseHas('feature_selections', [
            'character_id' => $character->id,
            'optional_feature_id' => $feature->id,
        ]);

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/feature-selections/{$feature->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('feature_selections', [
            'character_id' => $character->id,
            'optional_feature_id' => $feature->id,
        ]);
    }

    #[Test]
    public function it_returns_404_when_removing_feature_character_does_not_have(): void
    {
        $character = Character::factory()->create();
        $feature = OptionalFeature::factory()->maneuver()->create();

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/feature-selections/{$feature->id}");

        $response->assertNotFound();
    }

    // ==========================================
    // GENERAL ERROR HANDLING TESTS
    // ==========================================

    #[Test]
    public function it_returns_404_for_nonexistent_character_on_index(): void
    {
        $response = $this->getJson('/api/v1/characters/99999/feature-selections');

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_nonexistent_character_on_available(): void
    {
        $response = $this->getJson('/api/v1/characters/99999/available-feature-selections');

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_nonexistent_character_on_choices(): void
    {
        $response = $this->getJson('/api/v1/characters/99999/feature-selection-choices');

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_nonexistent_character_on_store(): void
    {
        $feature = OptionalFeature::factory()->create();

        $response = $this->postJson('/api/v1/characters/99999/feature-selections', [
            'optional_feature_id' => $feature->id,
        ]);

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_422_for_invalid_optional_feature_id(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/feature-selections", [
            'optional_feature_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('optional_feature_id');
    }
}
