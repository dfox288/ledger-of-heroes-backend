<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterLanguage;
use App\Models\EntityLanguage;
use App\Models\Feat;
use App\Models\Language;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterLanguageApiTest extends TestCase
{
    use RefreshDatabase;

    private Language $common;

    private Language $elvish;

    private Language $dwarvish;

    private Language $draconic;

    private Race $humanRace;

    private Race $elfRace;

    private Background $sageBackground;

    private Background $acolyteBackground;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();
    }

    private function createFixtures(): void
    {
        $uniqueId = uniqid();

        // Create languages with unique names to avoid constraint violations
        $this->common = Language::create([
            'name' => 'Common '.$uniqueId,
            'slug' => 'common-'.$uniqueId,
            'script' => 'Common',
        ]);

        $this->elvish = Language::create([
            'name' => 'Elvish '.$uniqueId,
            'slug' => 'elvish-'.$uniqueId,
            'script' => 'Elvish',
        ]);

        $this->dwarvish = Language::create([
            'name' => 'Dwarvish '.$uniqueId,
            'slug' => 'dwarvish-'.$uniqueId,
            'script' => 'Dwarvish',
        ]);

        $this->draconic = Language::create([
            'name' => 'Draconic '.$uniqueId,
            'slug' => 'draconic-'.$uniqueId,
            'script' => 'Draconic',
        ]);

        // Create Human race: Common (fixed) + 1 language choice
        $this->humanRace = Race::factory()->create([
            'name' => 'Human',
            'slug' => 'human-'.$uniqueId,
        ]);

        // Fixed language: Common
        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $this->humanRace->id,
            'language_id' => $this->common->id,
            'is_choice' => false,
        ]);

        // Choice: 1 language
        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $this->humanRace->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 1,
        ]);

        // Create Elf race: Common + Elvish (both fixed)
        $this->elfRace = Race::factory()->create([
            'name' => 'Elf',
            'slug' => 'elf-'.$uniqueId,
        ]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $this->elfRace->id,
            'language_id' => $this->common->id,
            'is_choice' => false,
        ]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $this->elfRace->id,
            'language_id' => $this->elvish->id,
            'is_choice' => false,
        ]);

        // Create Sage background: 2 language choices
        $this->sageBackground = Background::factory()->create([
            'name' => 'Sage',
            'slug' => 'sage-'.$uniqueId,
        ]);

        EntityLanguage::create([
            'reference_type' => Background::class,
            'reference_id' => $this->sageBackground->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 2,
        ]);

        // Create Acolyte background: 2 language choices
        $this->acolyteBackground = Background::factory()->create([
            'name' => 'Acolyte',
            'slug' => 'acolyte-'.$uniqueId,
        ]);

        EntityLanguage::create([
            'reference_type' => Background::class,
            'reference_id' => $this->acolyteBackground->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 2,
        ]);
    }

    // =============================
    // GET /characters/{id}/languages
    // =============================

    #[Test]
    public function it_lists_character_languages(): void
    {
        $character = Character::factory()
            ->withRace($this->elfRace)
            ->create();

        // Add languages to character
        CharacterLanguage::create([
            'character_id' => $character->id,
            'language_id' => $this->common->id,
            'source' => 'race',
        ]);

        CharacterLanguage::create([
            'character_id' => $character->id,
            'language_id' => $this->elvish->id,
            'source' => 'race',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/languages");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'source',
                        'language' => [
                            'id',
                            'name',
                            'slug',
                            'script',
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_returns_empty_array_for_character_with_no_languages(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/languages");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // =============================
    // POST /characters/{id}/languages/populate
    // =============================

    #[Test]
    public function it_populates_fixed_languages_from_race(): void
    {
        $character = Character::factory()
            ->withRace($this->elfRace)
            ->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/languages/populate");

        $response->assertOk()
            ->assertJsonPath('message', 'Languages populated successfully');

        // Elf has Common and Elvish as fixed languages
        $this->assertCount(2, $response->json('data'));

        // Verify database
        $character->refresh();
        $this->assertEquals(2, $character->languages->count());
    }

    #[Test]
    public function it_does_not_populate_choice_languages(): void
    {
        $character = Character::factory()
            ->withRace($this->humanRace)
            ->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/languages/populate");

        $response->assertOk();

        // Human has only Common as fixed, choice is not populated
        $this->assertCount(1, $response->json('data'));
        $this->assertStringStartsWith('Common ', $response->json('data.0.language.name'));
    }

    #[Test]
    public function it_populates_languages_from_background(): void
    {
        // Create a background with a fixed language
        $uniqueId = uniqid();
        $hermitBackground = Background::factory()->create([
            'name' => 'Hermit',
            'slug' => 'hermit-'.$uniqueId,
        ]);

        // Hermit gets one fixed language
        EntityLanguage::create([
            'reference_type' => Background::class,
            'reference_id' => $hermitBackground->id,
            'language_id' => $this->draconic->id,
            'is_choice' => false,
        ]);

        $character = Character::factory()
            ->withRace($this->elfRace)
            ->withBackground($hermitBackground)
            ->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/languages/populate");

        $response->assertOk();

        // Should have Elf languages (Common, Elvish) + Hermit (Draconic)
        $this->assertCount(3, $response->json('data'));
    }

    #[Test]
    public function it_does_not_duplicate_languages_on_repeated_calls(): void
    {
        $character = Character::factory()
            ->withRace($this->elfRace)
            ->create();

        $this->postJson("/api/v1/characters/{$character->id}/languages/populate");
        $response = $this->postJson("/api/v1/characters/{$character->id}/languages/populate");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function it_does_not_duplicate_same_language_from_different_sources(): void
    {
        // Background that grants Common (same as race)
        $uniqueId = uniqid();
        $urbanBackground = Background::factory()->create([
            'name' => 'Urban Bounty Hunter',
            'slug' => 'urban-bounty-hunter-'.$uniqueId,
        ]);

        EntityLanguage::create([
            'reference_type' => Background::class,
            'reference_id' => $urbanBackground->id,
            'language_id' => $this->common->id,
            'is_choice' => false,
        ]);

        $character = Character::factory()
            ->withRace($this->elfRace) // Grants Common and Elvish
            ->withBackground($urbanBackground) // Also grants Common
            ->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/languages/populate");

        $response->assertOk();

        // Should have Common + Elvish, NOT Common twice
        // Common from race (first source) should be kept
        $this->assertCount(2, $response->json('data'));
    }

    // =============================
    // GET /characters/{id}/language-choices
    // =============================

    #[Test]
    public function it_returns_pending_language_choices_from_race(): void
    {
        $character = Character::factory()
            ->withRace($this->humanRace)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/language-choices");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'race' => [
                        'known' => [],
                        'choices' => [
                            'quantity',
                            'remaining',
                            'selected',
                            'options',
                        ],
                    ],
                ],
            ]);

        $raceData = $response->json('data.race');
        $this->assertEquals(1, $raceData['choices']['quantity']);
        $this->assertEquals(1, $raceData['choices']['remaining']);
        $this->assertEmpty($raceData['choices']['selected']);
    }

    #[Test]
    public function it_returns_pending_language_choices_from_background(): void
    {
        $character = Character::factory()
            ->withRace($this->elfRace)
            ->withBackground($this->sageBackground)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/language-choices");

        $response->assertOk();

        $backgroundData = $response->json('data.background');
        $this->assertEquals(2, $backgroundData['choices']['quantity']);
        $this->assertEquals(2, $backgroundData['choices']['remaining']);
    }

    #[Test]
    public function it_excludes_already_known_languages_from_options(): void
    {
        $character = Character::factory()
            ->withRace($this->humanRace)
            ->create();

        // Populate fixed languages (Common)
        $this->postJson("/api/v1/characters/{$character->id}/languages/populate");

        $response = $this->getJson("/api/v1/characters/{$character->id}/language-choices");

        $response->assertOk();

        $options = $response->json('data.race.choices.options');
        $optionSlugs = collect($options)->pluck('slug')->toArray();

        // Common should NOT be in options since character already knows it
        $this->assertNotContains($this->common->slug, $optionSlugs);

        // Other languages should be available
        $this->assertContains($this->elvish->slug, $optionSlugs);
        $this->assertContains($this->dwarvish->slug, $optionSlugs);
    }

    #[Test]
    public function it_shows_known_languages_in_response(): void
    {
        $character = Character::factory()
            ->withRace($this->elfRace)
            ->create();

        // Populate fixed languages
        $this->postJson("/api/v1/characters/{$character->id}/languages/populate");

        $response = $this->getJson("/api/v1/characters/{$character->id}/language-choices");

        $response->assertOk();

        $knownLanguages = $response->json('data.race.known');
        // Check that known languages contain Common and Elvish (with unique suffixes)
        $this->assertCount(2, $knownLanguages);
        $this->assertTrue(
            collect($knownLanguages)->contains(fn ($lang) => str_starts_with($lang['name'], 'Common '))
        );
        $this->assertTrue(
            collect($knownLanguages)->contains(fn ($lang) => str_starts_with($lang['name'], 'Elvish '))
        );
    }

    #[Test]
    public function it_tracks_remaining_choices_correctly(): void
    {
        $character = Character::factory()
            ->withRace($this->humanRace)
            ->withBackground($this->sageBackground)
            ->create();

        // Populate fixed languages
        $this->postJson("/api/v1/characters/{$character->id}/languages/populate");

        // Make one background choice
        CharacterLanguage::create([
            'character_id' => $character->id,
            'language_id' => $this->dwarvish->id,
            'source' => 'background',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/language-choices");

        $response->assertOk();

        // Background had 2 choices, 1 made, 1 remaining
        $backgroundData = $response->json('data.background');
        $this->assertEquals(2, $backgroundData['choices']['quantity']);
        $this->assertEquals(1, $backgroundData['choices']['remaining']);
        $this->assertCount(1, $backgroundData['choices']['selected']);
    }

    // =============================
    // POST /characters/{id}/language-choices
    // =============================

    #[Test]
    public function it_accepts_valid_language_choice(): void
    {
        $character = Character::factory()
            ->withRace($this->humanRace)
            ->create();

        // Populate fixed languages first
        $this->postJson("/api/v1/characters/{$character->id}/languages/populate");

        $response = $this->postJson("/api/v1/characters/{$character->id}/language-choices", [
            'source' => 'race',
            'language_ids' => [$this->elvish->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Languages saved successfully');

        // Verify language was added
        $character->refresh();
        $languageIds = $character->languages->pluck('language_id')->toArray();
        $this->assertContains($this->elvish->id, $languageIds);
    }

    #[Test]
    public function it_accepts_multiple_language_choices(): void
    {
        $character = Character::factory()
            ->withRace($this->elfRace)
            ->withBackground($this->sageBackground)
            ->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/language-choices", [
            'source' => 'background',
            'language_ids' => [$this->dwarvish->id, $this->draconic->id],
        ]);

        $response->assertOk();

        // Verify both languages were added
        $character->refresh();
        $backgroundLanguages = $character->languages->where('source', 'background');
        $this->assertCount(2, $backgroundLanguages);
    }

    #[Test]
    public function it_rejects_wrong_quantity_of_choices(): void
    {
        $character = Character::factory()
            ->withRace($this->humanRace)
            ->create();

        // Human has 1 choice, trying to submit 2
        $response = $this->postJson("/api/v1/characters/{$character->id}/language-choices", [
            'source' => 'race',
            'language_ids' => [$this->elvish->id, $this->dwarvish->id],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_rejects_already_known_language(): void
    {
        $character = Character::factory()
            ->withRace($this->humanRace)
            ->create();

        // Populate fixed languages (Common)
        $this->postJson("/api/v1/characters/{$character->id}/languages/populate");

        // Try to choose Common which is already known
        $response = $this->postJson("/api/v1/characters/{$character->id}/language-choices", [
            'source' => 'race',
            'language_ids' => [$this->common->id],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_replaces_existing_choices_when_resubmitting(): void
    {
        $character = Character::factory()
            ->withRace($this->humanRace)
            ->create();

        // First choice: Elvish
        $this->postJson("/api/v1/characters/{$character->id}/language-choices", [
            'source' => 'race',
            'language_ids' => [$this->elvish->id],
        ])->assertOk();

        // Change mind: Dwarvish
        $response = $this->postJson("/api/v1/characters/{$character->id}/language-choices", [
            'source' => 'race',
            'language_ids' => [$this->dwarvish->id],
        ]);

        $response->assertOk();

        // Should have Dwarvish, not Elvish
        $character->refresh();
        $raceLanguages = $character->languages->where('source', 'race');
        $languageIds = $raceLanguages->pluck('language_id')->toArray();

        $this->assertContains($this->dwarvish->id, $languageIds);
        $this->assertNotContains($this->elvish->id, $languageIds);
    }

    #[Test]
    public function it_validates_required_fields(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/language-choices", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['source', 'language_ids']);
    }

    #[Test]
    public function it_validates_source_is_valid(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/language-choices", [
            'source' => 'invalid',
            'language_ids' => [1],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['source']);
    }

    #[Test]
    public function it_validates_language_ids_exist(): void
    {
        $character = Character::factory()
            ->withRace($this->humanRace)
            ->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/language-choices", [
            'source' => 'race',
            'language_ids' => [99999],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language_ids.0']);
    }

    // =============================
    // Feat Language Support
    // =============================

    #[Test]
    public function it_populates_languages_from_feat(): void
    {
        // Create a feat that grants Draconic
        $uniqueId = uniqid();
        $dragonFearFeat = Feat::factory()->create([
            'name' => 'Dragon Fear',
            'slug' => 'dragon-fear-'.$uniqueId,
        ]);

        EntityLanguage::create([
            'reference_type' => Feat::class,
            'reference_id' => $dragonFearFeat->id,
            'language_id' => $this->draconic->id,
            'is_choice' => false,
        ]);

        $character = Character::factory()
            ->withRace($this->elfRace)
            ->create();

        // Give character the feat
        $character->features()->create([
            'feature_type' => Feat::class,
            'feature_id' => $dragonFearFeat->id,
            'source' => 'feat',
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/languages/populate");

        $response->assertOk();

        // Should have Elf languages (Common, Elvish) + Feat (Draconic)
        $languageNames = collect($response->json('data'))->pluck('language.name')->toArray();
        $this->assertTrue(collect($languageNames)->contains(fn ($n) => str_starts_with($n, 'Common ')));
        $this->assertTrue(collect($languageNames)->contains(fn ($n) => str_starts_with($n, 'Elvish ')));
        $this->assertTrue(collect($languageNames)->contains(fn ($n) => str_starts_with($n, 'Draconic ')));
    }

    #[Test]
    public function it_includes_feat_choices_in_language_choices(): void
    {
        // Create a feat with language choice
        $uniqueId = uniqid();
        $linguistFeat = Feat::factory()->create([
            'name' => 'Linguist',
            'slug' => 'linguist-'.$uniqueId,
        ]);

        EntityLanguage::create([
            'reference_type' => Feat::class,
            'reference_id' => $linguistFeat->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 3,
        ]);

        $character = Character::factory()
            ->withRace($this->elfRace)
            ->create();

        // Give character the feat
        $character->features()->create([
            'feature_type' => Feat::class,
            'feature_id' => $linguistFeat->id,
            'source' => 'feat',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/language-choices");

        $response->assertOk();

        $featData = $response->json('data.feat');
        $this->assertEquals(3, $featData['choices']['quantity']);
        $this->assertEquals(3, $featData['choices']['remaining']);
    }

    // =============================
    // Error Handling
    // =============================

    #[Test]
    public function it_returns_404_for_nonexistent_character(): void
    {
        $response = $this->getJson('/api/v1/characters/99999/languages');

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_nonexistent_character_on_choices(): void
    {
        $response = $this->getJson('/api/v1/characters/99999/language-choices');

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_nonexistent_character_on_populate(): void
    {
        $response = $this->postJson('/api/v1/characters/99999/languages/populate');

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_422_when_source_has_no_choices(): void
    {
        $character = Character::factory()
            ->withRace($this->elfRace) // Elf has no language choices
            ->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/language-choices", [
            'source' => 'race',
            'language_ids' => [$this->dwarvish->id],
        ]);

        $response->assertStatus(422);
    }

    // =============================
    // Subrace Language Inheritance
    // =============================

    #[Test]
    public function it_includes_inherited_parent_race_languages_in_known_array(): void
    {
        $uniqueId = uniqid();

        // Create parent race with languages (like Aasimar base)
        $parentRace = Race::factory()->create([
            'name' => 'Aasimar',
            'slug' => 'aasimar-'.$uniqueId,
        ]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'language_id' => $this->common->id,
            'is_choice' => false,
        ]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'language_id' => $this->draconic->id, // Using draconic as "Celestial"
            'is_choice' => false,
        ]);

        // Create subrace with no direct languages (like Aasimar DMG)
        $subrace = Race::factory()->create([
            'name' => 'Aasimar (DMG)',
            'slug' => 'aasimar-dmg-'.$uniqueId,
            'parent_race_id' => $parentRace->id,
        ]);

        $character = Character::factory()
            ->withRace($subrace)
            ->create();

        // Populate fixed languages (should include inherited)
        $this->postJson("/api/v1/characters/{$character->id}/languages/populate");

        $response = $this->getJson("/api/v1/characters/{$character->id}/language-choices");

        $response->assertOk();

        $knownLanguages = $response->json('data.race.known');

        // Should include both inherited languages (Common and Draconic/"Celestial")
        $this->assertCount(2, $knownLanguages);
        $this->assertTrue(
            collect($knownLanguages)->contains(fn ($lang) => str_starts_with($lang['name'], 'Common '))
        );
        $this->assertTrue(
            collect($knownLanguages)->contains(fn ($lang) => str_starts_with($lang['name'], 'Draconic '))
        );
    }

    #[Test]
    public function it_populates_inherited_parent_race_languages(): void
    {
        $uniqueId = uniqid();

        // Create parent race with languages
        $parentRace = Race::factory()->create([
            'name' => 'Aasimar',
            'slug' => 'aasimar-parent-'.$uniqueId,
        ]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'language_id' => $this->common->id,
            'is_choice' => false,
        ]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'language_id' => $this->draconic->id,
            'is_choice' => false,
        ]);

        // Create subrace with no direct languages
        $subrace = Race::factory()->create([
            'name' => 'Aasimar (DMG)',
            'slug' => 'aasimar-dmg-parent-'.$uniqueId,
            'parent_race_id' => $parentRace->id,
        ]);

        $character = Character::factory()
            ->withRace($subrace)
            ->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/languages/populate");

        $response->assertOk();

        // Should have both inherited languages
        $this->assertCount(2, $response->json('data'));

        $languageNames = collect($response->json('data'))->pluck('language.name')->toArray();
        $this->assertTrue(collect($languageNames)->contains(fn ($n) => str_starts_with($n, 'Common ')));
        $this->assertTrue(collect($languageNames)->contains(fn ($n) => str_starts_with($n, 'Draconic ')));
    }

    #[Test]
    public function it_includes_inherited_language_choices_from_parent_race(): void
    {
        $uniqueId = uniqid();

        // Create parent race with 1 language choice
        $parentRace = Race::factory()->create([
            'name' => 'Parent Race',
            'slug' => 'parent-race-'.$uniqueId,
        ]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'language_id' => $this->common->id,
            'is_choice' => false,
        ]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 1,
        ]);

        // Create subrace with no direct languages or choices
        $subrace = Race::factory()->create([
            'name' => 'Subrace',
            'slug' => 'subrace-'.$uniqueId,
            'parent_race_id' => $parentRace->id,
        ]);

        $character = Character::factory()
            ->withRace($subrace)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/language-choices");

        $response->assertOk();

        $raceData = $response->json('data.race');
        // Should inherit the 1 language choice from parent
        $this->assertEquals(1, $raceData['choices']['quantity']);
        $this->assertEquals(1, $raceData['choices']['remaining']);
    }

    #[Test]
    public function it_combines_inherited_and_direct_language_choices(): void
    {
        $uniqueId = uniqid();

        // Create parent race with 1 language choice
        $parentRace = Race::factory()->create([
            'name' => 'Parent Race With Choice',
            'slug' => 'parent-choice-'.$uniqueId,
        ]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'language_id' => $this->common->id,
            'is_choice' => false,
        ]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 1,
        ]);

        // Create subrace with its OWN language choice (in addition to inherited)
        $subrace = Race::factory()->create([
            'name' => 'Subrace With Choice',
            'slug' => 'subrace-choice-'.$uniqueId,
            'parent_race_id' => $parentRace->id,
        ]);

        // Subrace adds an additional language choice
        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $subrace->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 2,
        ]);

        $character = Character::factory()
            ->withRace($subrace)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/language-choices");

        $response->assertOk();

        $raceData = $response->json('data.race');
        // Should combine: 1 from parent + 2 from subrace = 3 total choices
        $this->assertEquals(3, $raceData['choices']['quantity']);
        $this->assertEquals(3, $raceData['choices']['remaining']);
    }

    #[Test]
    public function it_does_not_duplicate_same_language_from_parent_and_subrace(): void
    {
        $uniqueId = uniqid();

        // Create parent race with Common
        $parentRace = Race::factory()->create([
            'name' => 'Parent With Common',
            'slug' => 'parent-common-'.$uniqueId,
        ]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'language_id' => $this->common->id,
            'is_choice' => false,
        ]);

        // Create subrace that ALSO has Common defined (shouldn't create duplicate)
        $subrace = Race::factory()->create([
            'name' => 'Subrace With Common',
            'slug' => 'subrace-common-'.$uniqueId,
            'parent_race_id' => $parentRace->id,
        ]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $subrace->id,
            'language_id' => $this->common->id,
            'is_choice' => false,
        ]);

        $character = Character::factory()
            ->withRace($subrace)
            ->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/languages/populate");

        $response->assertOk();

        // Should have only ONE Common, not two
        $this->assertCount(1, $response->json('data'));

        $languageNames = collect($response->json('data'))->pluck('language.name')->toArray();
        $commonCount = collect($languageNames)->filter(fn ($n) => str_starts_with($n, 'Common '))->count();
        $this->assertEquals(1, $commonCount);
    }
}
