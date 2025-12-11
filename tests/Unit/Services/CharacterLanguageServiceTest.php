<?php

namespace Tests\Unit\Services;

use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterClass;
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
            'language_slug' => $common->slug,
            'source' => 'race',
        ]);

        CharacterLanguage::create([
            'character_id' => $character->id,
            'language_slug' => $elvish->slug,
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
        $this->assertEquals($draconic->slug, $character->languages->first()->language_slug);
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
            'feature_slug' => $feat->slug,
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
        $this->assertEquals($dwarvish->slug, $character->languages->first()->language_slug);
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
            'feature_slug' => $feat->slug,
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
        $this->assertEquals($common->slug, $character->languages->first()->language_slug);
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
            'language_slug' => $elvish->slug,
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
            'feature_slug' => $feat->slug,
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
        $optionSlugs = collect($choices['race']['choices']['options'])->pluck('slug')->toArray();
        $this->assertNotContains($common->slug, $optionSlugs);
        $this->assertContains($elvish->slug, $optionSlugs);
        $this->assertContains($dwarvish->slug, $optionSlugs);
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
        $this->service->makeChoice($character, 'race', [$elvish->slug]);

        // Assert
        $character->refresh();
        $this->assertCount(2, $character->languages); // Common + Elvish
        $this->assertTrue($character->languages->pluck('language_slug')->contains($elvish->slug));
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
        $this->service->makeChoice($character, 'background', [$draconic->slug, $infernal->slug]);

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
            'feature_slug' => $feat->slug,
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
        $this->service->makeChoice($character, 'feat', [$dwarvish->slug]);

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

        $this->service->makeChoice($character, 'invalid_source', [$language->slug]);
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

        $this->service->makeChoice($character, 'race', [$elvish->slug, $dwarvish->slug]);
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
        $this->expectExceptionMessage('One or more language slugs are invalid');

        $this->service->makeChoice($character, 'race', ['nonexistent:language']); // Non-existent slug
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
        $this->expectExceptionMessage("Language {$common->slug} is already known");

        $this->service->makeChoice($character, 'race', [$common->slug]);
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

        $this->service->makeChoice($character, 'race', [$language->slug]);
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
        $this->service->makeChoice($character, 'race', [$elvish->slug]);
        $character->refresh();
        $this->assertCount(1, $character->languages);
        $this->assertEquals($elvish->slug, $character->languages->first()->language_slug);

        // Act - change choice to different language
        $this->service->makeChoice($character, 'race', [$dwarvish->slug]);
        $character->refresh();

        // Assert - should have replaced, not added
        $this->assertCount(1, $character->languages);
        $this->assertEquals($dwarvish->slug, $character->languages->first()->language_slug);
    }

    #[Test]
    public function it_handles_character_without_race(): void
    {
        // Arrange
        $character = Character::factory()->create(['race_slug' => null]);

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
        $character = Character::factory()->create(['background_slug' => null]);

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
            'feature_slug' => $feat1->slug,
            'source' => 'feat',
            'level_acquired' => 1,
        ]);

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat2->id,
            'feature_slug' => $feat2->slug,
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

    #[Test]
    public function it_excludes_non_learnable_languages_from_options(): void
    {
        // Arrange - Create race with language choice
        $race = Race::factory()->create();
        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 1,
        ]);

        $character = Character::factory()->withRace($race)->create();

        // Get all languages count and non-learnable count
        $totalLanguages = Language::count();
        $nonLearnableCount = Language::where('is_learnable', false)->count();

        // Act
        $choices = $this->service->getPendingChoices($character);

        // Assert - Options should not include non-learnable languages
        $options = $choices['race']['choices']['options'];
        $optionSlugs = array_column($options, 'slug');

        // Verify Thieves' Cant and Druidic are NOT in options
        $this->assertNotContains('core:thieves-cant', $optionSlugs);
        $this->assertNotContains('core:druidic', $optionSlugs);

        // Verify known learnable languages ARE included
        $this->assertContains('core:common', $optionSlugs, 'Common should be in learnable options');
        $this->assertContains('core:elvish', $optionSlugs, 'Elvish should be in learnable options');

        // Verify all options have is_learnable = true
        foreach ($options as $option) {
            $this->assertArrayHasKey('is_learnable', $option);
            $this->assertTrue($option['is_learnable']);
        }

        // Verify we got the expected number of options (total - non-learnable)
        $expectedCount = $totalLanguages - $nonLearnableCount;
        $this->assertCount($expectedCount, $options);
    }

    #[Test]
    public function it_includes_is_learnable_field_in_language_options(): void
    {
        // Arrange - Create race with language choice
        $race = Race::factory()->create();
        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => null,
            'is_choice' => true,
            'quantity' => 1,
        ]);

        $character = Character::factory()->withRace($race)->create();

        // Act
        $choices = $this->service->getPendingChoices($character);

        // Assert - Each option should have is_learnable field
        $options = $choices['race']['choices']['options'];
        $this->assertNotEmpty($options);

        foreach ($options as $option) {
            $this->assertArrayHasKey('is_learnable', $option);
            $this->assertIsBool($option['is_learnable']);
        }
    }

    // =====================
    // populateFromClass() Tests
    // =====================

    #[Test]
    public function it_populates_fixed_languages_from_class(): void
    {
        // Arrange - Create a class with a fixed language (like Druid with Druidic)
        $class = CharacterClass::factory()->create(['name' => 'Druid-'.uniqid(), 'slug' => 'druid-'.uniqid()]);
        $druidic = Language::factory()->create(['name' => 'Druidic-'.uniqid(), 'slug' => 'druidic-'.uniqid()]);

        EntityLanguage::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'language_id' => $druidic->id,
            'is_choice' => false,
        ]);

        $character = Character::factory()->withClass($class)->create();

        // Act
        $this->service->populateFromClass($character);

        // Assert
        $character->refresh();
        $this->assertCount(1, $character->languages);
        $this->assertEquals('class', $character->languages->first()->source);
        $this->assertEquals($druidic->slug, $character->languages->first()->language_slug);
    }

    #[Test]
    public function it_does_not_populate_class_languages_if_no_class(): void
    {
        // Arrange - Character without a class
        $character = Character::factory()->create();

        // Act
        $this->service->populateFromClass($character);

        // Assert
        $character->refresh();
        $this->assertCount(0, $character->languages);
    }

    #[Test]
    public function it_does_not_duplicate_class_languages(): void
    {
        // Arrange
        $class = CharacterClass::factory()->create(['name' => 'Rogue-'.uniqid(), 'slug' => 'rogue-'.uniqid()]);
        $thievesCant = Language::factory()->create(['name' => 'Thieves Cant-'.uniqid(), 'slug' => 'thieves-cant-'.uniqid()]);

        EntityLanguage::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'language_id' => $thievesCant->id,
            'is_choice' => false,
        ]);

        $character = Character::factory()->withClass($class)->create();

        // Act - call twice
        $this->service->populateFromClass($character);
        $this->service->populateFromClass($character);

        // Assert - should still only have one language
        $character->refresh();
        $this->assertCount(1, $character->languages);
    }

    #[Test]
    public function it_does_not_populate_class_language_choices(): void
    {
        // Arrange - Class with a language CHOICE (not fixed)
        $class = CharacterClass::factory()->create(['name' => 'Test-'.uniqid(), 'slug' => 'test-'.uniqid()]);

        EntityLanguage::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'language_id' => null, // Choice - no specific language
            'is_choice' => true,
            'quantity' => 1,
        ]);

        $character = Character::factory()->withClass($class)->create();

        // Act
        $this->service->populateFromClass($character);

        // Assert - No languages should be auto-populated (choices require user input)
        $character->refresh();
        $this->assertCount(0, $character->languages);
    }
}
