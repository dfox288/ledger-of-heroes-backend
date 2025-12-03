<?php

namespace Tests\Unit\Listeners;

use App\Events\CharacterUpdated;
use App\Listeners\PopulateCharacterAbilities;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterFeature;
use App\Models\CharacterTrait;
use App\Models\ClassFeature;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Services\CharacterFeatureService;
use App\Services\CharacterProficiencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PopulateCharacterAbilitiesTest extends TestCase
{
    use RefreshDatabase;

    private PopulateCharacterAbilities $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->listener = new PopulateCharacterAbilities(
            app(CharacterFeatureService::class),
            app(CharacterProficiencyService::class)
        );
    }

    #[Test]
    public function it_populates_features_and_proficiencies_when_class_assigned(): void
    {
        // Create class with feature and proficiency
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);

        ClassFeature::create([
            'class_id' => $fighterClass->id,
            'level' => 1,
            'feature_name' => 'Second Wind',
            'description' => 'You can heal yourself.',
            'is_optional' => false,
        ]);

        $armor = ProficiencyType::create([
            'name' => 'Light Armor',
            'slug' => 'light-armor-'.uniqid(),
            'category' => 'armor',
        ]);

        $fighterClass->proficiencies()->create([
            'proficiency_type' => 'armor',
            'proficiency_type_id' => $armor->id,
            'is_choice' => false,
        ]);

        // Create character without class
        $character = Character::factory()->create(['class_id' => null, 'level' => 1]);

        // Now assign a class
        $character->class_id = $fighterClass->id;
        $character->save();

        // Manually trigger the event (in real app this happens automatically)
        $this->listener->handle(new CharacterUpdated($character));

        // Verify features and proficiencies were populated
        $character->refresh();
        $this->assertCount(1, $character->features);
        $this->assertCount(1, $character->proficiencies);
    }

    #[Test]
    public function it_clears_old_features_when_class_changes(): void
    {
        // Create two classes
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        $wizardClass = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard-'.uniqid()]);

        $fighterFeature = ClassFeature::create([
            'class_id' => $fighterClass->id,
            'level' => 1,
            'feature_name' => 'Second Wind',
            'description' => 'Fighter healing.',
            'is_optional' => false,
        ]);

        $wizardFeature = ClassFeature::create([
            'class_id' => $wizardClass->id,
            'level' => 1,
            'feature_name' => 'Arcane Recovery',
            'description' => 'Recover spell slots.',
            'is_optional' => false,
        ]);

        // Create character with fighter class
        $character = Character::factory()->withClass($fighterClass)->create(['level' => 1]);

        // Add fighter feature
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $fighterFeature->id,
            'source' => 'class',
            'level_acquired' => 1,
        ]);

        $this->assertCount(1, $character->features);
        $this->assertEquals('Second Wind', $character->features->first()->feature->feature_name);

        // Change to wizard
        $character->class_id = $wizardClass->id;
        $character->save();

        $this->listener->handle(new CharacterUpdated($character));

        // Verify old features cleared and new ones added
        $character->refresh();
        $this->assertCount(1, $character->features);
        $this->assertEquals('Arcane Recovery', $character->features->first()->feature->feature_name);
    }

    #[Test]
    public function it_adds_new_features_when_level_increases(): void
    {
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);

        ClassFeature::create([
            'class_id' => $fighterClass->id,
            'level' => 1,
            'feature_name' => 'Second Wind',
            'description' => 'Heal.',
            'is_optional' => false,
        ]);

        ClassFeature::create([
            'class_id' => $fighterClass->id,
            'level' => 2,
            'feature_name' => 'Action Surge',
            'description' => 'Extra action.',
            'is_optional' => false,
        ]);

        // Create level 1 character with feature
        $character = Character::factory()->withClass($fighterClass)->create(['level' => 1]);

        // Populate initial features
        app(CharacterFeatureService::class)->populateFromClass($character);
        $this->assertCount(1, $character->features);

        // Level up to 2
        $character->level = 2;
        $character->save();

        $this->listener->handle(new CharacterUpdated($character));

        // Should now have 2 features
        $character->refresh();
        $this->assertCount(2, $character->features);
    }

    #[Test]
    public function it_populates_racial_traits_when_race_assigned(): void
    {
        $race = Race::factory()->create(['name' => 'Elf', 'slug' => 'elf-'.uniqid()]);

        CharacterTrait::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'name' => 'Darkvision',
            'category' => 'sense',
            'description' => 'See in the dark.',
        ]);

        $character = Character::factory()->create(['race_id' => null]);

        // Assign race
        $character->race_id = $race->id;
        $character->save();

        $this->listener->handle(new CharacterUpdated($character));

        $character->refresh();
        $this->assertCount(1, $character->features);
        $this->assertEquals('race', $character->features->first()->source);
    }

    #[Test]
    public function it_clears_old_traits_when_race_changes(): void
    {
        $elfRace = Race::factory()->create(['name' => 'Elf', 'slug' => 'elf-'.uniqid()]);
        $dwarfRace = Race::factory()->create(['name' => 'Dwarf', 'slug' => 'dwarf-'.uniqid()]);

        $elfTrait = CharacterTrait::create([
            'reference_type' => Race::class,
            'reference_id' => $elfRace->id,
            'name' => 'Darkvision',
            'category' => 'sense',
            'description' => 'See in the dark.',
        ]);

        CharacterTrait::create([
            'reference_type' => Race::class,
            'reference_id' => $dwarfRace->id,
            'name' => 'Stonecunning',
            'category' => 'skill',
            'description' => 'Know about stone.',
        ]);

        // Create character with elf race
        $character = Character::factory()->withRace($elfRace)->create();

        // Add elf trait
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => CharacterTrait::class,
            'feature_id' => $elfTrait->id,
            'source' => 'race',
            'level_acquired' => 1,
        ]);

        // Change to dwarf
        $character->race_id = $dwarfRace->id;
        $character->save();

        $this->listener->handle(new CharacterUpdated($character));

        $character->refresh();
        $this->assertCount(1, $character->features);
        $this->assertEquals('Stonecunning', $character->features->first()->feature->name);
    }

    #[Test]
    public function it_does_nothing_when_unrelated_fields_change(): void
    {
        $character = Character::factory()->create(['name' => 'Test Character']);

        // Just change the name
        $character->name = 'Updated Name';
        $character->save();

        $this->listener->handle(new CharacterUpdated($character));

        // No features or proficiencies should be created
        $character->refresh();
        $this->assertCount(0, $character->features);
        $this->assertCount(0, $character->proficiencies);
    }
}
