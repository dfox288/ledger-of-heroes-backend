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

    #[Test]
    public function it_includes_all_mechanical_trait_categories(): void
    {
        $race = Race::factory()->create(['name' => 'High Elf', 'slug' => 'high-elf-'.uniqid()]);

        // Species category trait (base race mechanical trait)
        $darkvision = CharacterTrait::create([
            'reference_type' => 'App\\Models\\Race',
            'reference_id' => $race->id,
            'name' => 'Darkvision',
            'category' => 'species',
            'description' => 'You can see in dim light.',
        ]);

        // Subspecies category trait (subrace mechanical trait)
        $elfWeaponTraining = CharacterTrait::create([
            'reference_type' => 'App\\Models\\Race',
            'reference_id' => $race->id,
            'name' => 'Elf Weapon Training',
            'category' => 'subspecies',
            'description' => 'Proficiency with longsword, shortsword, longbow, and shortbow.',
        ]);

        // Feature category trait (general mechanical feature)
        $cantrip = CharacterTrait::create([
            'reference_type' => 'App\\Models\\Race',
            'reference_id' => $race->id,
            'name' => 'Cantrip',
            'category' => 'feature',
            'description' => 'You know one cantrip of your choice.',
        ]);

        $character = Character::factory()->withRace($race)->create();

        $this->service->populateFromRace($character);

        // All three mechanical categories should be included
        $this->assertCount(3, $character->features);

        $featureIds = $character->features->pluck('feature_id')->toArray();
        $this->assertContains($darkvision->id, $featureIds);
        $this->assertContains($elfWeaponTraining->id, $featureIds);
        $this->assertContains($cantrip->id, $featureIds);
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

    #[Test]
    public function it_sets_class_slug_on_subclass_granted_spells(): void
    {
        // Issue #715: class_slug should be populated for multiclass spellcasting support
        // When a subclass grants spells (e.g., Cleric Domain Spells), the class_slug
        // should identify which class granted the spell

        // Create Cleric base class
        $clericClass = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'test-cleric-'.uniqid(),
        ]);

        // Create Grave Domain subclass
        $graveDomain = CharacterClass::factory()->create([
            'name' => 'Grave Domain',
            'slug' => 'test-cleric-grave-domain-'.uniqid(),
            'parent_class_id' => $clericClass->id,
        ]);

        // Create Domain Spells feature
        $domainSpells = ClassFeature::create([
            'class_id' => $graveDomain->id,
            'level' => 1,
            'feature_name' => 'Domain Spells (Grave Domain)',
            'description' => 'Grave domain spells.',
            'is_optional' => false,
            'is_always_prepared' => true,
        ]);

        // Create a domain spell
        $bane = \App\Models\Spell::factory()->create([
            'name' => 'Bane',
            'slug' => 'test-bane-'.uniqid(),
            'level' => 1,
        ]);

        // Attach spell to domain
        $domainSpells->spells()->attach($bane->id, [
            'level_requirement' => 1,
            'is_cantrip' => false,
        ]);

        // Create a character with Cleric class
        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_slug' => $clericClass->slug,
            'level' => 1,
        ]);

        // Populate subclass features (this grants the domain spells)
        $this->service->populateFromSubclass($character, $clericClass->slug, $graveDomain->slug);

        // Verify the spell has the correct class_slug
        $character->refresh();
        $baneSpell = $character->spells()->where('spell_slug', $bane->slug)->first();

        $this->assertNotNull($baneSpell, 'Bane should be assigned');
        $this->assertEquals(
            $clericClass->slug,
            $baneSpell->class_slug,
            'Domain spell should have class_slug set to the granting class'
        );
    }

    // =====================
    // Subclass Variant Choice Tests (Circle of the Land terrain, etc.)
    // =====================

    #[Test]
    public function it_skips_variant_features_when_no_choice_is_made(): void
    {
        // Issue #752: Circle of the Land assigns all terrain spells instead of chosen terrain
        // This test verifies that variant features (with choice_group set) are skipped
        // when no subclass_choices have been made

        // Create Druid base class
        $druidClass = CharacterClass::factory()->create([
            'name' => 'Druid',
            'slug' => 'test-druid-'.uniqid(),
        ]);

        // Create Circle of the Land subclass
        $circleOfLand = CharacterClass::factory()->create([
            'name' => 'Circle of the Land',
            'slug' => 'test-druid-circle-of-the-land-'.uniqid(),
            'parent_class_id' => $druidClass->id,
        ]);

        // Create a non-variant feature (should be assigned)
        $bonusCantrip = ClassFeature::create([
            'class_id' => $circleOfLand->id,
            'level' => 2,
            'feature_name' => 'Bonus Cantrip (Circle of the Land)',
            'description' => 'You gain one druid cantrip.',
            'is_optional' => false,
        ]);

        // Create terrain variant features (should be SKIPPED when no choice made)
        $arcticFeature = ClassFeature::create([
            'class_id' => $circleOfLand->id,
            'level' => 3,
            'feature_name' => 'Arctic (Circle of the Land)',
            'description' => 'Arctic circle spells.',
            'is_optional' => false,
            'choice_group' => 'terrain', // This marks it as a variant
        ]);

        $coastFeature = ClassFeature::create([
            'class_id' => $circleOfLand->id,
            'level' => 3,
            'feature_name' => 'Coast (Circle of the Land)',
            'description' => 'Coast circle spells.',
            'is_optional' => false,
            'choice_group' => 'terrain',
        ]);

        // Create spells for each terrain
        $holdPerson = \App\Models\Spell::factory()->create(['name' => 'Hold Person', 'slug' => 'test-hold-person-'.uniqid()]);
        $mirrorImage = \App\Models\Spell::factory()->create(['name' => 'Mirror Image', 'slug' => 'test-mirror-image-'.uniqid()]);

        $arcticFeature->spells()->attach($holdPerson->id, ['level_requirement' => 3, 'is_cantrip' => false]);
        $coastFeature->spells()->attach($mirrorImage->id, ['level_requirement' => 3, 'is_cantrip' => false]);

        // Create character WITHOUT terrain choice
        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_slug' => $druidClass->slug,
            'subclass_slug' => $circleOfLand->slug,
            'level' => 3,
            // subclass_choices is NULL - no terrain selected
        ]);

        // Populate subclass features
        $this->service->populateFromSubclass($character, $druidClass->slug, $circleOfLand->slug);

        // Verify: Non-variant feature should be assigned
        $character->refresh();
        $features = $character->features;
        $this->assertTrue(
            $features->contains('feature_id', $bonusCantrip->id),
            'Non-variant features should be assigned'
        );

        // Verify: No terrain variant features should be assigned
        $this->assertFalse(
            $features->contains('feature_id', $arcticFeature->id),
            'Arctic variant should NOT be assigned when no terrain choice made'
        );
        $this->assertFalse(
            $features->contains('feature_id', $coastFeature->id),
            'Coast variant should NOT be assigned when no terrain choice made'
        );

        // Verify: No terrain spells should be assigned
        $this->assertCount(0, $character->spells, 'No terrain spells should be assigned without a choice');
    }

    #[Test]
    public function it_assigns_only_chosen_terrain_variant_feature_and_spells(): void
    {
        // Issue #752: When terrain choice is made, only that terrain's feature and spells
        // should be assigned

        // Create Druid base class
        $druidClass = CharacterClass::factory()->create([
            'name' => 'Druid',
            'slug' => 'test-druid-'.uniqid(),
        ]);

        // Create Circle of the Land subclass
        $circleOfLand = CharacterClass::factory()->create([
            'name' => 'Circle of the Land',
            'slug' => 'test-druid-circle-of-the-land-'.uniqid(),
            'parent_class_id' => $druidClass->id,
        ]);

        // Create terrain variant features
        $arcticFeature = ClassFeature::create([
            'class_id' => $circleOfLand->id,
            'level' => 3,
            'feature_name' => 'Arctic (Circle of the Land)',
            'description' => 'Arctic circle spells.',
            'is_optional' => false,
            'choice_group' => 'terrain',
        ]);

        $coastFeature = ClassFeature::create([
            'class_id' => $circleOfLand->id,
            'level' => 3,
            'feature_name' => 'Coast (Circle of the Land)',
            'description' => 'Coast circle spells.',
            'is_optional' => false,
            'choice_group' => 'terrain',
        ]);

        // Create spells for each terrain
        $holdPerson = \App\Models\Spell::factory()->create(['name' => 'Hold Person', 'slug' => 'test-hold-person-'.uniqid(), 'level' => 2]);
        $spikeGrowth = \App\Models\Spell::factory()->create(['name' => 'Spike Growth', 'slug' => 'test-spike-growth-'.uniqid(), 'level' => 2]);
        $mirrorImage = \App\Models\Spell::factory()->create(['name' => 'Mirror Image', 'slug' => 'test-mirror-image-'.uniqid(), 'level' => 2]);
        $mistyStep = \App\Models\Spell::factory()->create(['name' => 'Misty Step', 'slug' => 'test-misty-step-'.uniqid(), 'level' => 2]);

        // Arctic gets Hold Person + Spike Growth
        $arcticFeature->spells()->attach($holdPerson->id, ['level_requirement' => 3, 'is_cantrip' => false]);
        $arcticFeature->spells()->attach($spikeGrowth->id, ['level_requirement' => 3, 'is_cantrip' => false]);

        // Coast gets Mirror Image + Misty Step
        $coastFeature->spells()->attach($mirrorImage->id, ['level_requirement' => 3, 'is_cantrip' => false]);
        $coastFeature->spells()->attach($mistyStep->id, ['level_requirement' => 3, 'is_cantrip' => false]);

        // Create character WITH Arctic terrain choice
        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_slug' => $druidClass->slug,
            'subclass_slug' => $circleOfLand->slug,
            'level' => 3,
            'subclass_choices' => ['terrain' => 'arctic'], // Arctic selected!
        ]);

        // Populate subclass features
        $this->service->populateFromSubclass($character, $druidClass->slug, $circleOfLand->slug);

        // Verify: Arctic variant feature should be assigned
        $character->refresh();
        $features = $character->features;
        $this->assertTrue(
            $features->contains('feature_id', $arcticFeature->id),
            'Arctic variant should be assigned when arctic terrain chosen'
        );

        // Verify: Coast variant should NOT be assigned
        $this->assertFalse(
            $features->contains('feature_id', $coastFeature->id),
            'Coast variant should NOT be assigned when arctic terrain chosen'
        );

        // Verify: Only Arctic spells should be assigned (2 spells)
        $this->assertCount(2, $character->spells, 'Only Arctic spells should be assigned');

        $spellSlugs = $character->spells->pluck('spell_slug')->toArray();
        $this->assertContains($holdPerson->slug, $spellSlugs, 'Hold Person (Arctic) should be assigned');
        $this->assertContains($spikeGrowth->slug, $spellSlugs, 'Spike Growth (Arctic) should be assigned');
        $this->assertNotContains($mirrorImage->slug, $spellSlugs, 'Mirror Image (Coast) should NOT be assigned');
        $this->assertNotContains($mistyStep->slug, $spellSlugs, 'Misty Step (Coast) should NOT be assigned');
    }

    #[Test]
    public function it_handles_terrain_spells_at_multiple_levels(): void
    {
        // Issue #752: Terrain spells are granted at levels 3, 5, 7, 9
        // Verify correct spells are granted based on character level

        // Create Druid base class
        $druidClass = CharacterClass::factory()->create([
            'name' => 'Druid',
            'slug' => 'test-druid-'.uniqid(),
        ]);

        // Create Circle of the Land subclass
        $circleOfLand = CharacterClass::factory()->create([
            'name' => 'Circle of the Land',
            'slug' => 'test-druid-circle-of-the-land-'.uniqid(),
            'parent_class_id' => $druidClass->id,
        ]);

        // Create Arctic terrain feature
        $arcticFeature = ClassFeature::create([
            'class_id' => $circleOfLand->id,
            'level' => 3,
            'feature_name' => 'Arctic (Circle of the Land)',
            'description' => 'Arctic circle spells.',
            'is_optional' => false,
            'choice_group' => 'terrain',
        ]);

        // Create spells at various level requirements
        $holdPerson = \App\Models\Spell::factory()->create(['name' => 'Hold Person', 'slug' => 'test-hold-person-'.uniqid(), 'level' => 2]);
        $sleetStorm = \App\Models\Spell::factory()->create(['name' => 'Sleet Storm', 'slug' => 'test-sleet-storm-'.uniqid(), 'level' => 3]);
        $iceStorm = \App\Models\Spell::factory()->create(['name' => 'Ice Storm', 'slug' => 'test-ice-storm-'.uniqid(), 'level' => 4]);

        // Attach spells with different level requirements
        $arcticFeature->spells()->attach($holdPerson->id, ['level_requirement' => 3, 'is_cantrip' => false]);
        $arcticFeature->spells()->attach($sleetStorm->id, ['level_requirement' => 5, 'is_cantrip' => false]);
        $arcticFeature->spells()->attach($iceStorm->id, ['level_requirement' => 7, 'is_cantrip' => false]);

        // Create level 5 character with Arctic choice
        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_slug' => $druidClass->slug,
            'subclass_slug' => $circleOfLand->slug,
            'level' => 5,
            'subclass_choices' => ['terrain' => 'arctic'],
        ]);

        // Populate subclass features
        $this->service->populateFromSubclass($character, $druidClass->slug, $circleOfLand->slug);

        // Verify: Spells at L3 and L5 should be assigned, L7 should not
        $character->refresh();
        $spellSlugs = $character->spells->pluck('spell_slug')->toArray();

        $this->assertContains($holdPerson->slug, $spellSlugs, 'Hold Person (L3 req) should be assigned at L5');
        $this->assertContains($sleetStorm->slug, $spellSlugs, 'Sleet Storm (L5 req) should be assigned at L5');
        $this->assertNotContains($iceStorm->slug, $spellSlugs, 'Ice Storm (L7 req) should NOT be assigned at L5');

        $this->assertCount(2, $character->spells, 'Should have 2 spells at level 5');
    }
}
