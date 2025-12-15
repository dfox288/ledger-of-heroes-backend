<?php

namespace Tests\Unit\Services;

use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterFeature;
use App\Models\CharacterTrait;
use App\Models\ClassFeature;
use App\Models\Race;
use App\Services\CharacterFeatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterFeatureServiceTest extends TestCase
{
    use RefreshDatabase;

    private CharacterFeatureService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CharacterFeatureService::class);
    }

    // =====================
    // Class Feature Population Tests
    // =====================

    #[Test]
    public function it_populates_class_features_for_character_level(): void
    {
        // Create a class with features at various levels
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);

        $secondWind = ClassFeature::create([
            'class_id' => $fighterClass->id,
            'level' => 1,
            'feature_name' => 'Second Wind',
            'description' => 'You can heal yourself.',
            'is_optional' => false,
        ]);

        $actionSurge = ClassFeature::create([
            'class_id' => $fighterClass->id,
            'level' => 2,
            'feature_name' => 'Action Surge',
            'description' => 'You can take an additional action.',
            'is_optional' => false,
        ]);

        // Level 3 feature - should NOT be included for level 2 character
        ClassFeature::create([
            'class_id' => $fighterClass->id,
            'level' => 3,
            'feature_name' => 'Martial Archetype',
            'description' => 'Choose a subclass.',
            'is_optional' => false,
        ]);

        // Create level 2 character
        $character = Character::factory()->withClass($fighterClass)->create(['level' => 2]);

        // Populate features
        $this->service->populateFromClass($character);

        // Should have features up to level 2 only
        $this->assertCount(2, $character->features);
        $this->assertTrue($character->features->contains('feature_id', $secondWind->id));
        $this->assertTrue($character->features->contains('feature_id', $actionSurge->id));
        $this->assertTrue($character->features->every(fn ($f) => $f->source === 'class'));
    }

    #[Test]
    public function it_skips_optional_class_features_for_auto_population(): void
    {
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);

        // Regular feature
        $secondWind = ClassFeature::create([
            'class_id' => $fighterClass->id,
            'level' => 1,
            'feature_name' => 'Second Wind',
            'description' => 'You can heal yourself.',
            'is_optional' => false,
        ]);

        // Optional feature (like Fighting Style - requires choice)
        $fightingStyleParent = ClassFeature::create([
            'class_id' => $fighterClass->id,
            'level' => 1,
            'feature_name' => 'Fighting Style',
            'description' => 'Choose a fighting style.',
            'is_optional' => true,
        ]);

        $character = Character::factory()->withClass($fighterClass)->create(['level' => 1]);

        $this->service->populateFromClass($character);

        // Only non-optional feature should be auto-populated
        $this->assertCount(1, $character->features);
        $this->assertEquals($secondWind->id, $character->features->first()->feature_id);
    }

    #[Test]
    public function it_does_not_duplicate_features_on_repeated_calls(): void
    {
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);

        ClassFeature::create([
            'class_id' => $fighterClass->id,
            'level' => 1,
            'feature_name' => 'Second Wind',
            'description' => 'You can heal yourself.',
            'is_optional' => false,
        ]);

        $character = Character::factory()->withClass($fighterClass)->create(['level' => 1]);

        // Call populate twice
        $this->service->populateFromClass($character);
        $this->service->populateFromClass($character);

        // Should still only have 1 feature
        $this->assertCount(1, $character->fresh()->features);
    }

    #[Test]
    public function it_sets_level_acquired_from_feature_level(): void
    {
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);

        ClassFeature::create([
            'class_id' => $fighterClass->id,
            'level' => 2,
            'feature_name' => 'Action Surge',
            'description' => 'Take an extra action.',
            'is_optional' => false,
        ]);

        $character = Character::factory()->withClass($fighterClass)->create(['level' => 5]);

        $this->service->populateFromClass($character);

        $feature = $character->features->first();
        $this->assertEquals(2, $feature->level_acquired);
    }

    // =====================
    // Racial Trait Population Tests
    // =====================

    #[Test]
    public function it_populates_racial_traits(): void
    {
        $race = Race::factory()->create(['name' => 'Elf', 'slug' => 'elf-'.uniqid()]);

        // Create racial traits using the entity_traits table
        // Only 'species', 'subspecies', 'feature' categories are considered mechanical
        $darkvision = CharacterTrait::create([
            'reference_type' => 'App\\Models\\Race',
            'reference_id' => $race->id,
            'name' => 'Darkvision',
            'category' => 'species', // Mechanical trait
            'description' => 'You can see in dim light.',
        ]);

        $feySenses = CharacterTrait::create([
            'reference_type' => 'App\\Models\\Race',
            'reference_id' => $race->id,
            'name' => 'Fey Ancestry',
            'category' => 'species', // Mechanical trait
            'description' => 'Advantage against charm.',
        ]);

        $character = Character::factory()->withRace($race)->create();

        $this->service->populateFromRace($character);

        $this->assertCount(2, $character->features);
        $this->assertTrue($character->features->every(fn ($f) => $f->source === 'race'));
        $this->assertTrue($character->features->every(fn ($f) => $f->feature_type === 'App\\Models\\CharacterTrait'));
    }

    #[Test]
    public function it_filters_out_non_mechanical_racial_traits(): void
    {
        $race = Race::factory()->create(['name' => 'Elf', 'slug' => 'elf-'.uniqid()]);

        // Mechanical trait (should be included)
        $darkvision = CharacterTrait::create([
            'reference_type' => 'App\\Models\\Race',
            'reference_id' => $race->id,
            'name' => 'Darkvision',
            'category' => 'species',
            'description' => 'You can see in dim light.',
        ]);

        // Non-mechanical traits (should be filtered out)
        CharacterTrait::create([
            'reference_type' => 'App\\Models\\Race',
            'reference_id' => $race->id,
            'name' => 'Description',
            'category' => 'description',
            'description' => 'Elves are tall and slender.',
        ]);

        CharacterTrait::create([
            'reference_type' => 'App\\Models\\Race',
            'reference_id' => $race->id,
            'name' => 'Age',
            'category' => null, // null category should be filtered
            'description' => 'Elves mature slowly.',
        ]);

        $character = Character::factory()->withRace($race)->create();

        $this->service->populateFromRace($character);

        // Only the mechanical trait should be included
        $this->assertCount(1, $character->features);
        $this->assertEquals($darkvision->id, $character->features->first()->feature_id);
    }

    // =====================
    // Background Feature Population Tests
    // =====================

    #[Test]
    public function it_populates_background_traits(): void
    {
        $background = Background::factory()->create(['name' => 'Soldier', 'slug' => 'soldier-'.uniqid()]);

        CharacterTrait::create([
            'reference_type' => 'App\\Models\\Background',
            'reference_id' => $background->id,
            'name' => 'Military Rank',
            'category' => 'feature',
            'description' => 'You have a military rank.',
        ]);

        $character = Character::factory()->withBackground($background)->create();

        $this->service->populateFromBackground($character);

        $this->assertCount(1, $character->features);
        $this->assertEquals('background', $character->features->first()->source);
    }

    // =====================
    // Populate All Tests
    // =====================

    #[Test]
    public function it_populates_all_features_from_class_race_and_background(): void
    {
        // Set up class with feature
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        ClassFeature::create([
            'class_id' => $fighterClass->id,
            'level' => 1,
            'feature_name' => 'Second Wind',
            'description' => 'Heal yourself.',
            'is_optional' => false,
        ]);

        // Set up race with mechanical trait (species category)
        $race = Race::factory()->create(['name' => 'Elf', 'slug' => 'elf-'.uniqid()]);
        CharacterTrait::create([
            'reference_type' => 'App\\Models\\Race',
            'reference_id' => $race->id,
            'name' => 'Darkvision',
            'category' => 'species', // Mechanical category
            'description' => 'See in the dark.',
        ]);

        // Set up background with feature trait
        $background = Background::factory()->create(['name' => 'Soldier', 'slug' => 'soldier-'.uniqid()]);
        CharacterTrait::create([
            'reference_type' => 'App\\Models\\Background',
            'reference_id' => $background->id,
            'name' => 'Military Rank',
            'category' => 'feature',
            'description' => 'You have rank.',
        ]);

        $character = Character::factory()
            ->withClass($fighterClass)
            ->withRace($race)
            ->withBackground($background)
            ->create(['level' => 1]);

        $this->service->populateAll($character);

        $this->assertCount(3, $character->features);
        $this->assertEquals(1, $character->features->where('source', 'class')->count());
        $this->assertEquals(1, $character->features->where('source', 'race')->count());
        $this->assertEquals(1, $character->features->where('source', 'background')->count());
    }

    // =====================
    // Clear Features Tests
    // =====================

    #[Test]
    public function it_clears_features_from_specific_source(): void
    {
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        $classFeature = ClassFeature::create([
            'class_id' => $fighterClass->id,
            'level' => 1,
            'feature_name' => 'Second Wind',
            'description' => 'Heal.',
            'is_optional' => false,
        ]);

        $race = Race::factory()->create(['name' => 'Elf', 'slug' => 'elf-'.uniqid()]);
        $raceTrait = CharacterTrait::create([
            'reference_type' => 'App\\Models\\Race',
            'reference_id' => $race->id,
            'name' => 'Darkvision',
            'category' => 'species', // Mechanical category
            'description' => 'See.',
        ]);

        $character = Character::factory()
            ->withClass($fighterClass)
            ->withRace($race)
            ->create();

        // Manually create features
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => 'App\\Models\\ClassFeature',
            'feature_id' => $classFeature->id,
            'source' => 'class',
            'level_acquired' => 1,
        ]);

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => 'App\\Models\\CharacterTrait',
            'feature_id' => $raceTrait->id,
            'source' => 'race',
            'level_acquired' => 1,
        ]);

        $character->load('features');
        $this->assertCount(2, $character->features);

        // Clear only class features
        $this->service->clearFeatures($character, 'class');

        $character->refresh();
        $this->assertCount(1, $character->features);
        $this->assertEquals('race', $character->features->first()->source);
    }

    // =====================
    // Get Character Features Tests
    // =====================

    #[Test]
    public function it_retrieves_all_character_features_with_details(): void
    {
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        $classFeature = ClassFeature::create([
            'class_id' => $fighterClass->id,
            'level' => 1,
            'feature_name' => 'Second Wind',
            'description' => 'Heal yourself.',
            'is_optional' => false,
        ]);

        $character = Character::factory()->withClass($fighterClass)->create();

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => 'App\\Models\\ClassFeature',
            'feature_id' => $classFeature->id,
            'source' => 'class',
            'level_acquired' => 1,
        ]);

        $features = $this->service->getCharacterFeatures($character);

        $this->assertCount(1, $features);
        $this->assertNotNull($features->first()->feature);
        $this->assertEquals('Second Wind', $features->first()->feature->feature_name);
    }

    // =====================
    // Subclass Feature Population Tests
    // =====================

    #[Test]
    public function it_assigns_bonus_cantrip_with_null_level_requirement_from_subclass(): void
    {
        // Create Cleric base class
        $clericClass = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
        ]);

        // Create Light Domain subclass
        $lightDomain = CharacterClass::factory()->create([
            'name' => 'Light Domain',
            'slug' => 'cleric-light-domain',
            'parent_class_id' => $clericClass->id,
        ]);

        // Create a Bonus Cantrip feature for Light Domain (level 1, always prepared)
        $bonusCantrip = ClassFeature::create([
            'class_id' => $lightDomain->id,
            'level' => 1,
            'feature_name' => 'Bonus Cantrip (Light Domain)',
            'description' => 'You gain the light cantrip.',
            'is_optional' => false,
            'is_always_prepared' => true,
        ]);

        // Create the Light spell
        $lightSpell = \App\Models\Spell::factory()->create([
            'name' => 'Light',
            'slug' => 'light',
            'level' => 0,
        ]);

        // Attach the Light spell to the Bonus Cantrip feature with NULL level_requirement
        // This simulates the bug: bonus cantrips have level_requirement=NULL
        $bonusCantrip->spells()->attach($lightSpell->id, [
            'level_requirement' => null, // NULL means available immediately
            'is_cantrip' => true,
        ]);

        // Create a level 1 Cleric character
        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_slug' => $clericClass->slug,
            'level' => 1,
        ]);

        // Populate subclass features (should include the Light cantrip)
        $this->service->populateFromSubclass($character, $clericClass->slug, $lightDomain->slug);

        // Verify the Light cantrip was assigned
        $character->refresh();
        $lightSpellAssigned = $character->spells()->where('spell_slug', 'light')->first();

        $this->assertNotNull($lightSpellAssigned, 'Light cantrip should be assigned to character');
        $this->assertEquals('subclass', $lightSpellAssigned->source);
        $this->assertEquals('always_prepared', $lightSpellAssigned->preparation_status);
    }

    #[Test]
    public function it_does_not_assign_spells_with_level_requirement_above_character_level(): void
    {
        // Create Cleric base class
        $clericClass = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
        ]);

        // Create Light Domain subclass
        $lightDomain = CharacterClass::factory()->create([
            'name' => 'Light Domain',
            'slug' => 'cleric-light-domain',
            'parent_class_id' => $clericClass->id,
        ]);

        // Create a Domain Spells feature
        $domainSpells = ClassFeature::create([
            'class_id' => $lightDomain->id,
            'level' => 1,
            'feature_name' => 'Domain Spells (Light Domain)',
            'description' => 'Domain spells at various levels.',
            'is_optional' => false,
            'is_always_prepared' => true,
        ]);

        // Create spells at various level requirements
        $burningHands = \App\Models\Spell::factory()->create([
            'name' => 'Burning Hands',
            'slug' => 'burning-hands',
            'level' => 1,
        ]);

        $scorchingRay = \App\Models\Spell::factory()->create([
            'name' => 'Scorching Ray',
            'slug' => 'scorching-ray',
            'level' => 2,
        ]);

        $fireball = \App\Models\Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'level' => 3,
        ]);

        // Attach spells with different level requirements
        $domainSpells->spells()->attach($burningHands->id, ['level_requirement' => 1, 'is_cantrip' => false]);
        $domainSpells->spells()->attach($scorchingRay->id, ['level_requirement' => 3, 'is_cantrip' => false]);
        $domainSpells->spells()->attach($fireball->id, ['level_requirement' => 5, 'is_cantrip' => false]);

        // Create a level 3 Cleric character
        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_slug' => $clericClass->slug,
            'level' => 3,
        ]);

        // Populate subclass features
        $this->service->populateFromSubclass($character, $clericClass->slug, $lightDomain->slug);

        // Verify spells at or below character level are assigned
        $character->refresh();

        $burningHandsAssigned = $character->spells()->where('spell_slug', 'burning-hands')->first();
        $this->assertNotNull($burningHandsAssigned, 'Burning Hands (level_req=1) should be assigned to level 3 character');

        $scorchingRayAssigned = $character->spells()->where('spell_slug', 'scorching-ray')->first();
        $this->assertNotNull($scorchingRayAssigned, 'Scorching Ray (level_req=3) should be assigned to level 3 character');

        // Verify spell above character level is NOT assigned
        $fireballAssigned = $character->spells()->where('spell_slug', 'fireball')->first();
        $this->assertNull($fireballAssigned, 'Fireball (level_req=5) should NOT be assigned to level 3 character');
    }

    #[Test]
    public function it_does_not_duplicate_spell_when_subclass_grants_already_known_spell(): void
    {
        // This tests issue #627: Duplicate spell constraint violation when subclass grants already-known spell
        // Scenario: Character has heroism from Divine Soul origin, then multiclasses into Peace Domain Cleric
        // which also grants heroism. Should not throw: "Duplicate entry for character_spells_character_id_spell_slug_unique"

        // Create Cleric base class
        $clericClass = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'test-cleric-'.uniqid(),
        ]);

        // Create Peace Domain subclass
        $peaceDomain = CharacterClass::factory()->create([
            'name' => 'Peace Domain',
            'slug' => 'test-cleric-peace-domain-'.uniqid(),
            'parent_class_id' => $clericClass->id,
        ]);

        // Create Domain Spells feature
        $domainSpells = ClassFeature::create([
            'class_id' => $peaceDomain->id,
            'level' => 1,
            'feature_name' => 'Domain Spells (Peace Domain)',
            'description' => 'Peace domain spells.',
            'is_optional' => false,
            'is_always_prepared' => true,
        ]);

        // Create the Heroism spell
        $heroism = \App\Models\Spell::factory()->create([
            'name' => 'Heroism',
            'slug' => 'test-heroism-'.uniqid(),
            'level' => 1,
        ]);

        // Attach heroism to Peace Domain
        $domainSpells->spells()->attach($heroism->id, [
            'level_requirement' => 1,
            'is_cantrip' => false,
        ]);

        // Create a character who already has heroism from a different source (e.g., Divine Soul origin)
        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_slug' => $clericClass->slug,
            'level' => 1,
        ]);

        // Pre-existing heroism spell from another source (Divine Soul origin grants this)
        \App\Models\CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $heroism->slug,
            'source' => 'class', // From Divine Soul Sorcerer origin
            'level_acquired' => 1,
            'preparation_status' => 'known',
        ]);

        // This should NOT throw a duplicate constraint violation
        // It should upgrade the spell to always_prepared (domain spells are always prepared)
        $this->service->populateFromSubclass($character, $clericClass->slug, $peaceDomain->slug);

        // Verify the spell exists only once
        $character->refresh();
        $heroismSpells = $character->spells()->where('spell_slug', $heroism->slug)->get();

        $this->assertCount(1, $heroismSpells, 'Character should have exactly one copy of the spell');

        // Verify the preparation status was upgraded to always_prepared
        // (Peace Domain grants heroism as always_prepared, which is stronger than known)
        $this->assertEquals(
            'always_prepared',
            $heroismSpells->first()->preparation_status,
            'Spell should be upgraded to always_prepared when domain grants it'
        );
    }
}
