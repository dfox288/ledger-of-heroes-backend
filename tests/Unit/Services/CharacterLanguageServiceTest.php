<?php

namespace Tests\Unit\Services;

use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterFeature;
use App\Models\CharacterLanguage;
use App\Models\EntityLanguage;
use App\Models\Feat;
use App\Models\Language;
use App\Models\Race;
use App\Services\CharacterLanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterLanguageServiceTest extends TestCase
{
    use RefreshDatabase;

    private CharacterLanguageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CharacterLanguageService::class);
    }

    // =====================
    // getCharacterLanguages() Tests
    // =====================

    #[Test]
    public function it_returns_all_character_languages_with_details(): void
    {
        // Arrange
        $character = Character::factory()->create();
        $common = Language::factory()->create(['name' => 'Common-'.uniqid(), 'slug' => 'common-'.uniqid()]);
        $elvish = Language::factory()->create(['name' => 'Elvish-'.uniqid(), 'slug' => 'elvish-'.uniqid()]);

        CharacterLanguage::create([
            'character_id' => $character->id,
            'language_id' => $common->id,
            'source' => 'race',
        ]);

        CharacterLanguage::create([
            'character_id' => $character->id,
            'language_id' => $elvish->id,
            'source' => 'background',
        ]);

        // Act
        $languages = $this->service->getCharacterLanguages($character);

        // Assert
        $this->assertCount(2, $languages);
        $this->assertNotNull($languages->first()->language);
    }

    #[Test]
    public function it_returns_empty_collection_for_character_without_languages(): void
    {
        // Arrange
        $character = Character::factory()->create();

        // Act
        $languages = $this->service->getCharacterLanguages($character);

        // Assert
        $this->assertCount(0, $languages);
    }

    // =====================
    // populateFixed() Tests
    // =====================

    #[Test]
    public function it_populates_fixed_languages_from_race(): void
    {
        // Arrange
        $race = Race::factory()->create(['name' => 'Elf-'.uniqid(), 'slug' => 'elf-'.uniqid()]);
        $common = Language::factory()->create(['name' => 'Common-'.uniqid(), 'slug' => 'common-'.uniqid()]);
        $elvish = Language::factory()->create(['name' => 'Elvish-'.uniqid(), 'slug' => 'elvish-'.uniqid()]);

        // Create fixed language entries for the race
        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => $common->id,
            'is_choice' => false,
        ]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => $elvish->id,
            'is_choice' => false,
        ]);

        $character = Character::factory()->withRace($race)->create();

        // Act
        $this->service->populateFixed($character);

        // Assert
        $character->refresh();
        $this->assertCount(2, $character->languages);
        $this->assertTrue($character->languages->every(fn ($cl) => $cl->source === 'race'));
    }

    #[Test]
    public function it_populates_fixed_languages_from_background(): void
    {
        // Arrange
        $background = Background::factory()->create(['name' => 'Sage-'.uniqid(), 'slug' => 'sage-'.uniqid()]);
        $draconic = Language::factory()->create(['name' => 'Draconic-'.uniqid(), 'slug' => 'draconic-'.uniqid()]);

        EntityLanguage::create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'language_id' => $draconic->id,
            'is_choice' => false,
        ]);

        $character = Character::factory()->withBackground($background)->create();

        // Act
        $this->service->populateFixed($character);

        // Assert
        $character->refresh();
        $this->assertCount(1, $character->languages);
        $this->assertEquals('background', $character->languages->first()->source);
        $this->assertEquals($draconic->id, $character->languages->first()->language_id);
    }

    #[Test]
    public function it_populates_fixed_languages_from_feats(): void
    {
        // Arrange
        $character = Character::factory()->create();
        $feat = Feat::factory()->create(['name' => 'Linguist-'.uniqid(), 'slug' => 'linguist-'.uniqid()]);
        $dwarvish = Language::factory()->create(['name' => 'Dwarvish-'.uniqid(), 'slug' => 'dwarvish-'.uniqid()]);

        // Add feat to character via character_features
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat->id,
            'source' => 'feat',
            'level_acquired' => 1,
        ]);

        // Create fixed language from feat
        EntityLanguage::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'language_id' => $dwarvish->id,
            'is_choice' => false,
        ]);

        // Act
        $this->service->populateFixed($character);

        // Assert
        $character->refresh();
        $this->assertCount(1, $character->languages);
        $this->assertEquals('feat', $character->languages->first()->source);
        $this->assertEquals($dwarvish->id, $character->languages->first()->language_id);
    }

    #[Test]
    public function it_populates_fixed_languages_from_all_sources(): void
    {
        // Arrange
        $race = Race::factory()->create(['name' => 'Elf-'.uniqid(), 'slug' => 'elf-'.uniqid()]);
        $background = Background::factory()->create(['name' => 'Sage-'.uniqid(), 'slug' => 'sage-'.uniqid()]);
        $feat = Feat::factory()->create(['name' => 'Linguist-'.uniqid(), 'slug' => 'linguist-'.uniqid()]);

        $common = Language::factory()->create(['name' => 'Common-'.uniqid(), 'slug' => 'common-'.uniqid()]);
        $elvish = Language::factory()->create(['name' => 'Elvish-'.uniqid(), 'slug' => 'elvish-'.uniqid()]);
        $draconic = Language::factory()->create(['name' => 'Draconic-'.uniqid(), 'slug' => 'draconic-'.uniqid()]);

        // Race languages
        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => $common->id,
            'is_choice' => false,
        ]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => $elvish->id,
            'is_choice' => false,
        ]);

        // Background language
        EntityLanguage::create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'language_id' => $draconic->id,
            'is_choice' => false,
        ]);

        $character = Character::factory()
            ->withRace($race)
            ->withBackground($background)
            ->create();

        // Add feat
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat->id,
            'source' => 'feat',
            'level_acquired' => 1,
        ]);

        // Act
        $this->service->populateFixed($character);

        // Assert
        $character->refresh();
        $this->assertCount(3, $character->languages);
        $this->assertEquals(2, $character->languages->where('source', 'race')->count());
        $this->assertEquals(1, $character->languages->where('source', 'background')->count());
    }

    #[Test]
    public function it_does_not_duplicate_fixed_languages(): void
    {
        // Arrange
        $race = Race::factory()->create(['name' => 'Elf-'.uniqid(), 'slug' => 'elf-'.uniqid()]);
        $common = Language::factory()->create(['name' => 'Common-'.uniqid(), 'slug' => 'common-'.uniqid()]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => $common->id,
            'is_choice' => false,
        ]);

        $character = Character::factory()->withRace($race)->create();

        // Act - call twice
        $this->service->populateFixed($character);
        $this->service->populateFixed($character);

        // Assert - should only have 1 language
        $character->refresh();
        $this->assertCount(1, $character->languages);
    }

    #[Test]
    public function it_skips_choice_languages_in_populate_fixed(): void
    {
        // Arrange
        $race = Race::factory()->create(['name' => 'Human-'.uniqid(), 'slug' => 'human-'.uniqid()]);
        $common = Language::factory()->create(['name' => 'Common-'.uniqid(), 'slug' => 'common-'.uniqid()]);

        // Fixed language
        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => $common->id,
            'is_choice' => false,
        ]);

        // Choice language (quantity 1)
        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 1,
        ]);

        $character = Character::factory()->withRace($race)->create();

        // Act
        $this->service->populateFixed($character);

        // Assert - only fixed language should be added
        $character->refresh();
        $this->assertCount(1, $character->languages);
        $this->assertEquals($common->id, $character->languages->first()->language_id);
    }

    // =====================
    // getPendingChoices() Tests
    // =====================

    #[Test]
    public function it_returns_pending_choices_for_race(): void
    {
        // Arrange
        $race = Race::factory()->create(['name' => 'Human-'.uniqid(), 'slug' => 'human-'.uniqid()]);
        $common = Language::factory()->create(['name' => 'Common-'.uniqid(), 'slug' => 'common-'.uniqid()]);
        $elvish = Language::factory()->create(['name' => 'Elvish-'.uniqid(), 'slug' => 'elvish-'.uniqid()]);

        // Fixed language
        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => $common->id,
            'is_choice' => false,
        ]);

        // Choice (1 language)
        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 1,
        ]);

        $character = Character::factory()->withRace($race)->create();
        $this->service->populateFixed($character);

        // Act
        $choices = $this->service->getPendingChoices($character);

        // Assert
        $this->assertEquals(1, $choices['race']['choices']['quantity']);
        $this->assertEquals(1, $choices['race']['choices']['remaining']);
        $this->assertCount(1, $choices['race']['known']); // Common is known
        $this->assertGreaterThan(0, count($choices['race']['choices']['options'])); // Has options
    }

    #[Test]
    public function it_returns_no_pending_choices_when_all_selected(): void
    {
        // Arrange
        $race = Race::factory()->create(['name' => 'Human-'.uniqid(), 'slug' => 'human-'.uniqid()]);
        $common = Language::factory()->create(['name' => 'Common-'.uniqid(), 'slug' => 'common-'.uniqid()]);
        $elvish = Language::factory()->create(['name' => 'Elvish-'.uniqid(), 'slug' => 'elvish-'.uniqid()]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => $common->id,
            'is_choice' => false,
        ]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 1,
        ]);

        $character = Character::factory()->withRace($race)->create();
        $this->service->populateFixed($character);

        // Make the choice
        CharacterLanguage::create([
            'character_id' => $character->id,
            'language_id' => $elvish->id,
            'source' => 'race',
        ]);

        // Act
        $choices = $this->service->getPendingChoices($character);

        // Assert
        $this->assertEquals(1, $choices['race']['choices']['quantity']);
        $this->assertEquals(0, $choices['race']['choices']['remaining']); // All selected
    }

    #[Test]
    public function it_returns_pending_choices_for_background(): void
    {
        // Arrange
        $background = Background::factory()->create(['name' => 'Sage-'.uniqid(), 'slug' => 'sage-'.uniqid()]);

        // Choice (2 languages)
        EntityLanguage::create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 2,
        ]);

        $character = Character::factory()->withBackground($background)->create();

        // Act
        $choices = $this->service->getPendingChoices($character);

        // Assert
        $this->assertEquals(2, $choices['background']['choices']['quantity']);
        $this->assertEquals(2, $choices['background']['choices']['remaining']);
    }

    #[Test]
    public function it_returns_pending_choices_for_feats(): void
    {
        // Arrange
        $character = Character::factory()->create();
        $feat = Feat::factory()->create(['name' => 'Linguist-'.uniqid(), 'slug' => 'linguist-'.uniqid()]);

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat->id,
            'source' => 'feat',
            'level_acquired' => 1,
        ]);

        // Choice (3 languages from Linguist feat)
        EntityLanguage::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 3,
        ]);

        // Act
        $choices = $this->service->getPendingChoices($character);

        // Assert
        $this->assertEquals(3, $choices['feat']['choices']['quantity']);
        $this->assertEquals(3, $choices['feat']['choices']['remaining']);
    }

    #[Test]
    public function it_excludes_known_languages_from_options(): void
    {
        // Arrange
        $race = Race::factory()->create(['name' => 'Human-'.uniqid(), 'slug' => 'human-'.uniqid()]);
        $common = Language::factory()->create(['name' => 'Common-'.uniqid(), 'slug' => 'common-'.uniqid()]);
        $elvish = Language::factory()->create(['name' => 'Elvish-'.uniqid(), 'slug' => 'elvish-'.uniqid()]);
        $dwarvish = Language::factory()->create(['name' => 'Dwarvish-'.uniqid(), 'slug' => 'dwarvish-'.uniqid()]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => $common->id,
            'is_choice' => false,
        ]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 1,
        ]);

        $character = Character::factory()->withRace($race)->create();
        $this->service->populateFixed($character);

        // Act
        $choices = $this->service->getPendingChoices($character);

        // Assert - Common should NOT be in options (already known)
        $optionIds = collect($choices['race']['choices']['options'])->pluck('id')->toArray();
        $this->assertNotContains($common->id, $optionIds);
        $this->assertContains($elvish->id, $optionIds);
        $this->assertContains($dwarvish->id, $optionIds);
    }

    #[Test]
    public function it_handles_subrace_language_choices(): void
    {
        // Arrange
        $parentRace = Race::factory()->create([
            'name' => 'Elf',
            'slug' => 'elf-'.uniqid(),
            'parent_race_id' => null,
        ]);

        $subrace = Race::factory()->create([
            'name' => 'High Elf',
            'slug' => 'high-elf-'.uniqid(),
            'parent_race_id' => $parentRace->id,
        ]);

        $common = Language::factory()->create(['name' => 'Common-'.uniqid(), 'slug' => 'common-'.uniqid()]);
        $elvish = Language::factory()->create(['name' => 'Elvish-'.uniqid(), 'slug' => 'elvish-'.uniqid()]);

        // Parent race fixed language
        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'language_id' => $elvish->id,
            'is_choice' => false,
        ]);

        // Parent race choice
        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 1,
        ]);

        // Subrace fixed language
        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $subrace->id,
            'language_id' => $common->id,
            'is_choice' => false,
        ]);

        $character = Character::factory()->withRace($subrace)->create();
        $this->service->populateFixed($character);

        // Act
        $choices = $this->service->getPendingChoices($character);

        // Assert - should inherit parent's choice quantity
        $this->assertEquals(1, $choices['race']['choices']['quantity']);
        $this->assertCount(2, $choices['race']['known']); // Both Common and Elvish
    }

    // =====================
    // makeChoice() Tests
    // =====================

    #[Test]
    public function it_makes_language_choice_for_race(): void
    {
        // Arrange
        $race = Race::factory()->create(['name' => 'Human-'.uniqid(), 'slug' => 'human-'.uniqid()]);
        $common = Language::factory()->create(['name' => 'Common-'.uniqid(), 'slug' => 'common-'.uniqid()]);
        $elvish = Language::factory()->create(['name' => 'Elvish-'.uniqid(), 'slug' => 'elvish-'.uniqid()]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => $common->id,
            'is_choice' => false,
        ]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 1,
        ]);

        $character = Character::factory()->withRace($race)->create();
        $this->service->populateFixed($character);

        // Act
        $this->service->makeChoice($character, 'race', [$elvish->id]);

        // Assert
        $character->refresh();
        $this->assertCount(2, $character->languages); // Common + Elvish
        $this->assertTrue($character->languages->pluck('language_id')->contains($elvish->id));
    }

    #[Test]
    public function it_makes_language_choice_for_background(): void
    {
        // Arrange
        $background = Background::factory()->create(['name' => 'Sage-'.uniqid(), 'slug' => 'sage-'.uniqid()]);
        $draconic = Language::factory()->create(['name' => 'Draconic-'.uniqid(), 'slug' => 'draconic-'.uniqid()]);
        $infernal = Language::factory()->create(['name' => 'Infernal-'.uniqid(), 'slug' => 'infernal-'.uniqid()]);

        EntityLanguage::create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 2,
        ]);

        $character = Character::factory()->withBackground($background)->create();

        // Act
        $this->service->makeChoice($character, 'background', [$draconic->id, $infernal->id]);

        // Assert
        $character->refresh();
        $this->assertCount(2, $character->languages);
        $this->assertTrue($character->languages->every(fn ($cl) => $cl->source === 'background'));
    }

    #[Test]
    public function it_makes_language_choice_for_feat(): void
    {
        // Arrange
        $character = Character::factory()->create();
        $feat = Feat::factory()->create(['name' => 'Linguist-'.uniqid(), 'slug' => 'linguist-'.uniqid()]);
        $dwarvish = Language::factory()->create(['name' => 'Dwarvish-'.uniqid(), 'slug' => 'dwarvish-'.uniqid()]);

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat->id,
            'source' => 'feat',
            'level_acquired' => 1,
        ]);

        EntityLanguage::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 1,
        ]);

        // Act
        $this->service->makeChoice($character, 'feat', [$dwarvish->id]);

        // Assert
        $character->refresh();
        $this->assertCount(1, $character->languages);
        $this->assertEquals('feat', $character->languages->first()->source);
    }

    #[Test]
    public function it_throws_exception_for_invalid_source(): void
    {
        // Arrange
        $character = Character::factory()->create();
        $language = Language::factory()->create();

        // Assert & Act
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid source: invalid_source');

        $this->service->makeChoice($character, 'invalid_source', [$language->id]);
    }

    #[Test]
    public function it_throws_exception_for_wrong_quantity(): void
    {
        // Arrange
        $race = Race::factory()->create(['name' => 'Human-'.uniqid(), 'slug' => 'human-'.uniqid()]);
        $elvish = Language::factory()->create(['name' => 'Elvish-'.uniqid(), 'slug' => 'elvish-'.uniqid()]);
        $dwarvish = Language::factory()->create(['name' => 'Dwarvish-'.uniqid(), 'slug' => 'dwarvish-'.uniqid()]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 1, // Only 1 allowed
        ]);

        $character = Character::factory()->withRace($race)->create();

        // Assert & Act - trying to choose 2 when only 1 allowed
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Must choose exactly 1 languages, got 2');

        $this->service->makeChoice($character, 'race', [$elvish->id, $dwarvish->id]);
    }

    #[Test]
    public function it_throws_exception_for_invalid_language_id(): void
    {
        // Arrange
        $race = Race::factory()->create(['name' => 'Human-'.uniqid(), 'slug' => 'human-'.uniqid()]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 1,
        ]);

        $character = Character::factory()->withRace($race)->create();

        // Assert & Act
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('One or more language IDs are invalid');

        $this->service->makeChoice($character, 'race', [99999]); // Non-existent ID
    }

    #[Test]
    public function it_throws_exception_for_already_known_language(): void
    {
        // Arrange
        $race = Race::factory()->create(['name' => 'Human-'.uniqid(), 'slug' => 'human-'.uniqid()]);
        $common = Language::factory()->create(['name' => 'Common-'.uniqid(), 'slug' => 'common-'.uniqid()]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => $common->id,
            'is_choice' => false,
        ]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 1,
        ]);

        $character = Character::factory()->withRace($race)->create();
        $this->service->populateFixed($character);

        // Assert & Act - trying to choose Common which is already known
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Language ID {$common->id} is already known");

        $this->service->makeChoice($character, 'race', [$common->id]);
    }

    #[Test]
    public function it_throws_exception_when_no_choices_available(): void
    {
        // Arrange
        $race = Race::factory()->create(['name' => 'Dwarf-'.uniqid(), 'slug' => 'dwarf-'.uniqid()]);
        $language = Language::factory()->create();

        // No choice record - only fixed languages
        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => $language->id,
            'is_choice' => false,
        ]);

        $character = Character::factory()->withRace($race)->create();

        // Assert & Act
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No language choices available for race');

        $this->service->makeChoice($character, 'race', [$language->id]);
    }

    #[Test]
    public function it_replaces_previous_choices_for_same_source(): void
    {
        // Arrange
        $race = Race::factory()->create(['name' => 'Human-'.uniqid(), 'slug' => 'human-'.uniqid()]);
        $elvish = Language::factory()->create(['name' => 'Elvish-'.uniqid(), 'slug' => 'elvish-'.uniqid()]);
        $dwarvish = Language::factory()->create(['name' => 'Dwarvish-'.uniqid(), 'slug' => 'dwarvish-'.uniqid()]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 1,
        ]);

        $character = Character::factory()->withRace($race)->create();

        // Act - make first choice
        $this->service->makeChoice($character, 'race', [$elvish->id]);
        $character->refresh();
        $this->assertCount(1, $character->languages);
        $this->assertEquals($elvish->id, $character->languages->first()->language_id);

        // Act - change choice to different language
        $this->service->makeChoice($character, 'race', [$dwarvish->id]);
        $character->refresh();

        // Assert - should have replaced, not added
        $this->assertCount(1, $character->languages);
        $this->assertEquals($dwarvish->id, $character->languages->first()->language_id);
    }

    #[Test]
    public function it_handles_character_without_race(): void
    {
        // Arrange
        $character = Character::factory()->create(['race_id' => null]);

        // Act
        $this->service->populateFixed($character);

        // Assert - no languages populated
        $character->refresh();
        $this->assertCount(0, $character->languages);
    }

    #[Test]
    public function it_handles_character_without_background(): void
    {
        // Arrange
        $character = Character::factory()->create(['background_id' => null]);

        // Act
        $this->service->populateFixed($character);

        // Assert - no languages populated
        $character->refresh();
        $this->assertCount(0, $character->languages);
    }

    #[Test]
    public function it_handles_character_without_feats(): void
    {
        // Arrange
        $character = Character::factory()->create();
        // No feats added

        // Act
        $this->service->populateFixed($character);

        // Assert - no languages populated
        $character->refresh();
        $this->assertCount(0, $character->languages);
    }

    #[Test]
    public function it_handles_multiple_feats_with_language_choices(): void
    {
        // Arrange
        $character = Character::factory()->create();
        $feat1 = Feat::factory()->create(['name' => 'Linguist-'.uniqid(), 'slug' => 'linguist-'.uniqid()]);
        $feat2 = Feat::factory()->create(['name' => 'Prodigy-'.uniqid(), 'slug' => 'prodigy-'.uniqid()]);

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat1->id,
            'source' => 'feat',
            'level_acquired' => 1,
        ]);

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat2->id,
            'source' => 'feat',
            'level_acquired' => 4,
        ]);

        // Linguist gives 3 languages
        EntityLanguage::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat1->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 3,
        ]);

        // Prodigy gives 1 language
        EntityLanguage::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat2->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 1,
        ]);

        // Act
        $choices = $this->service->getPendingChoices($character);

        // Assert - should sum up to 4 total choices from feats
        $this->assertEquals(4, $choices['feat']['choices']['quantity']);
        $this->assertEquals(4, $choices['feat']['choices']['remaining']);
    }
}
