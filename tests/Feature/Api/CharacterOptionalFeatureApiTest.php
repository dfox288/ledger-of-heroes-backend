<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\FeatureSelection;
use App\Models\OptionalFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterOptionalFeatureApiTest extends TestCase
{
    use RefreshDatabase;

    // =============================
    // GET /characters/{id}/optional-features
    // =============================

    #[Test]
    public function it_returns_optional_features_for_character(): void
    {
        $character = Character::factory()->create();
        $class = CharacterClass::factory()->create(['slug' => 'test:warlock']);

        $invocation = OptionalFeature::factory()->invocation()->create([
            'name' => 'Agonizing Blast',
            'description' => 'When you cast eldritch blast, add your Charisma modifier to the damage.',
        ]);

        FeatureSelection::factory()
            ->for($character)
            ->withFeature($invocation)
            ->forClass($class)
            ->atLevel(2)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/optional-features");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Agonizing Blast')
            ->assertJsonPath('data.0.class_slug', 'test:warlock')
            ->assertJsonPath('data.0.level_acquired', 2);
    }

    #[Test]
    public function it_returns_empty_array_for_character_with_no_optional_features(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/optional-features");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_includes_full_optional_feature_details(): void
    {
        $character = Character::factory()->create();
        $class = CharacterClass::factory()->create();

        $metamagic = OptionalFeature::factory()->metamagic()->create([
            'name' => 'Quickened Spell',
            'description' => 'When you cast a spell that has a casting time of 1 action...',
            'level_requirement' => 3,
        ]);

        FeatureSelection::factory()
            ->for($character)
            ->withFeature($metamagic)
            ->forClass($class)
            ->atLevel(3)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/optional-features");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'slug',
                        'name',
                        'feature_type',
                        'feature_type_label',
                        'level_requirement',
                        'description',
                        'resource_type',
                        'resource_cost',
                        // Character-specific fields
                        'class_slug',
                        'level_acquired',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_returns_multiple_optional_features(): void
    {
        $character = Character::factory()->create();
        $class = CharacterClass::factory()->create();

        $feature1 = OptionalFeature::factory()->invocation()->create(['name' => 'Agonizing Blast']);
        $feature2 = OptionalFeature::factory()->invocation()->create(['name' => 'Repelling Blast']);
        $feature3 = OptionalFeature::factory()->invocation()->create(['name' => 'Eldritch Spear']);

        FeatureSelection::factory()->for($character)->withFeature($feature1)->forClass($class)->atLevel(1)->create();
        FeatureSelection::factory()->for($character)->withFeature($feature2)->forClass($class)->atLevel(1)->create();
        FeatureSelection::factory()->for($character)->withFeature($feature3)->forClass($class)->atLevel(5)->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/optional-features");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_accepts_public_id_parameter(): void
    {
        $character = Character::factory()->create(['public_id' => 'test-hero-ABC1']);
        $class = CharacterClass::factory()->create();

        $infusion = OptionalFeature::factory()->artificerInfusion()->create([
            'name' => 'Enhanced Defense',
        ]);

        FeatureSelection::factory()
            ->for($character)
            ->withFeature($infusion)
            ->forClass($class)
            ->create();

        $response = $this->getJson('/api/v1/characters/test-hero-ABC1/optional-features');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Enhanced Defense');
    }

    #[Test]
    public function it_returns_404_for_nonexistent_character(): void
    {
        $response = $this->getJson('/api/v1/characters/99999/optional-features');

        $response->assertNotFound();
    }

    #[Test]
    public function it_filters_out_dangling_feature_selections(): void
    {
        $character = Character::factory()->create();
        $class = CharacterClass::factory()->create();

        // Create valid feature selection
        $validFeature = OptionalFeature::factory()->invocation()->create(['name' => 'Valid Feature']);
        FeatureSelection::factory()
            ->for($character)
            ->withFeature($validFeature)
            ->forClass($class)
            ->create();

        // Create dangling feature selection (non-existent optional feature)
        FeatureSelection::create([
            'character_id' => $character->id,
            'optional_feature_slug' => 'non-existent-feature',
            'class_slug' => 'test:warlock',
            'level_acquired' => 1,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/optional-features");

        // Should only return the valid feature, filtering out dangling references
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Valid Feature');
    }
}
