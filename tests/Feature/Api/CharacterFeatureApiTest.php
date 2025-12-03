<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterFeature;
use App\Models\CharacterTrait;
use App\Models\ClassFeature;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterFeatureApiTest extends TestCase
{
    use RefreshDatabase;

    private CharacterClass $fighterClass;

    private Race $elfRace;

    private Background $soldierBackground;

    private ClassFeature $secondWind;

    private CharacterTrait $darkvision;

    private CharacterTrait $militaryRank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();
    }

    private function createFixtures(): void
    {
        // Create class with features
        $this->fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter-'.uniqid(),
        ]);

        $this->secondWind = ClassFeature::create([
            'class_id' => $this->fighterClass->id,
            'level' => 1,
            'feature_name' => 'Second Wind',
            'description' => 'You have a limited well of stamina.',
            'is_optional' => false,
        ]);

        ClassFeature::create([
            'class_id' => $this->fighterClass->id,
            'level' => 2,
            'feature_name' => 'Action Surge',
            'description' => 'You can push yourself beyond normal limits.',
            'is_optional' => false,
        ]);

        // Create race with traits
        $this->elfRace = Race::factory()->create([
            'name' => 'Elf',
            'slug' => 'elf-'.uniqid(),
        ]);

        $this->darkvision = CharacterTrait::create([
            'reference_type' => 'App\\Models\\Race',
            'reference_id' => $this->elfRace->id,
            'name' => 'Darkvision',
            'category' => 'sense',
            'description' => 'You can see in dim light within 60 feet.',
        ]);

        // Create background with features
        $this->soldierBackground = Background::factory()->create([
            'name' => 'Soldier',
            'slug' => 'soldier-'.uniqid(),
        ]);

        $this->militaryRank = CharacterTrait::create([
            'reference_type' => 'App\\Models\\Background',
            'reference_id' => $this->soldierBackground->id,
            'name' => 'Military Rank',
            'category' => 'feature',
            'description' => 'You have a military rank from your career.',
        ]);
    }

    // =============================
    // GET /characters/{id}/features
    // =============================

    #[Test]
    public function it_lists_character_features(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $this->secondWind->id,
            'source' => 'class',
            'level_acquired' => 1,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/features");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.source', 'class')
            ->assertJsonPath('data.0.feature_type', 'class_feature')
            ->assertJsonPath('data.0.feature.name', 'Second Wind');
    }

    #[Test]
    public function it_returns_empty_array_for_character_with_no_features(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/features");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_includes_feature_details_in_response(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $this->secondWind->id,
            'source' => 'class',
            'level_acquired' => 1,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/features");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'source',
                        'level_acquired',
                        'feature_type',
                        'uses_remaining',
                        'max_uses',
                        'has_limited_uses',
                        'feature' => [
                            'id',
                            'name',
                            'description',
                        ],
                    ],
                ],
            ]);
    }

    // =============================
    // POST /characters/{id}/features/populate
    // =============================

    #[Test]
    public function it_populates_features_from_class_race_and_background(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->withRace($this->elfRace)
            ->withBackground($this->soldierBackground)
            ->create(['level' => 1]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/features/populate");

        $response->assertOk()
            ->assertJsonPath('message', 'Features populated successfully');

        // Should have 3 features: Second Wind (class), Darkvision (race), Military Rank (background)
        $this->assertCount(3, $response->json('data'));

        // Verify all sources are represented
        $sources = collect($response->json('data'))->pluck('source')->unique()->values()->all();
        $this->assertContains('class', $sources);
        $this->assertContains('race', $sources);
        $this->assertContains('background', $sources);
    }

    #[Test]
    public function it_only_populates_features_up_to_character_level(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create(['level' => 1]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/features/populate");

        $response->assertOk();

        // Level 1 should only get Second Wind, not Action Surge (level 2)
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Second Wind', $response->json('data.0.feature.name'));
    }

    #[Test]
    public function it_includes_higher_level_features_for_higher_level_characters(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create(['level' => 2]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/features/populate");

        $response->assertOk();

        // Level 2 should get both Second Wind and Action Surge
        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function it_does_not_duplicate_features_on_repeated_calls(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create(['level' => 1]);

        // Call populate twice
        $this->postJson("/api/v1/characters/{$character->id}/features/populate");
        $response = $this->postJson("/api/v1/characters/{$character->id}/features/populate");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    // =============================
    // DELETE /characters/{id}/features/{source}
    // =============================

    #[Test]
    public function it_clears_features_from_specific_source(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->withRace($this->elfRace)
            ->create();

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $this->secondWind->id,
            'source' => 'class',
            'level_acquired' => 1,
        ]);

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => CharacterTrait::class,
            'feature_id' => $this->darkvision->id,
            'source' => 'race',
            'level_acquired' => 1,
        ]);

        $this->assertCount(2, $character->features);

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/features/class");

        $response->assertOk()
            ->assertJsonPath('message', 'Features from class cleared successfully');

        // Should only have race feature remaining
        $character->refresh();
        $this->assertCount(1, $character->features);
        $this->assertEquals('race', $character->features->first()->source);
    }

    #[Test]
    public function it_rejects_invalid_source_for_clear(): void
    {
        $character = Character::factory()->create();

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/features/invalid-source");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Invalid source. Valid sources: class, race, background, feat, item');
    }

    // =============================
    // Error Handling
    // =============================

    #[Test]
    public function it_returns_404_for_nonexistent_character(): void
    {
        $response = $this->getJson('/api/v1/characters/99999/features');

        $response->assertNotFound();
    }
}
