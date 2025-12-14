<?php

namespace Tests\Unit\Services;

use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterProficiency;
use App\Models\ClassFeature;
use App\Models\EntityChoice;
use App\Models\ProficiencyType;
use App\Models\Skill;
use App\Services\CharacterProficiencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterProficiencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private CharacterProficiencyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CharacterProficiencyService::class);
    }

    private function createSkill(string $baseName): Skill
    {
        $abilityScore = AbilityScore::firstOrCreate(
            ['code' => 'STR'],
            ['name' => 'Strength', 'slug' => 'strength']
        );

        $uniqueId = uniqid();
        $slug = strtolower($baseName).'-'.$uniqueId;

        return Skill::create([
            'name' => $baseName.' '.$uniqueId,
            'slug' => 'test:'.$slug,
            'ability_score_id' => $abilityScore->id,
        ]);
    }

    // =====================
    // Fixed Proficiency Population Tests
    // =====================

    #[Test]
    public function it_populates_fixed_armor_proficiencies_from_class(): void
    {
        // Create proficiency types with unique slugs
        $lightArmorSlug = 'light-armor-'.uniqid();
        $heavyArmorSlug = 'heavy-armor-'.uniqid();
        $lightArmor = ProficiencyType::create([
            'name' => 'Light Armor',
            'slug' => 'test:'.$lightArmorSlug,
            'category' => 'armor',
        ]);
        $heavyArmor = ProficiencyType::create([
            'name' => 'Heavy Armor',
            'slug' => 'test:'.$heavyArmorSlug,
            'category' => 'armor',
        ]);

        // Create class with fixed armor proficiencies
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        $fighterClass->proficiencies()->createMany([
            ['proficiency_type' => 'armor', 'proficiency_type_id' => $lightArmor->id],
            ['proficiency_type' => 'armor', 'proficiency_type_id' => $heavyArmor->id],
        ]);

        // Create character with this class
        $character = Character::factory()->withClass($fighterClass)->create();

        // Populate proficiencies
        $this->service->populateFromClass($character);

        // Assert proficiencies were created
        $this->assertCount(2, $character->proficiencies);
        $this->assertTrue($character->proficiencies->contains('proficiency_type_slug', $lightArmor->slug));
        $this->assertTrue($character->proficiencies->contains('proficiency_type_slug', $heavyArmor->slug));
        $this->assertTrue($character->proficiencies->every(fn ($p) => $p->source === 'class'));
    }

    #[Test]
    public function it_does_not_populate_choice_proficiencies_automatically(): void
    {
        // Create skills
        $athletics = $this->createSkill('Athletics');
        $acrobatics = $this->createSkill('Acrobatics');

        // Create class with skill choices (via EntityChoice)
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_1',
            'quantity' => 2,
            'target_type' => 'skill',
            'target_slug' => $athletics->slug,
        ]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_1',
            'target_type' => 'skill',
            'target_slug' => $acrobatics->slug,
        ]);

        $character = Character::factory()->withClass($fighterClass)->create();

        $this->service->populateFromClass($character);

        // No proficiencies should be auto-created for choices
        $this->assertCount(0, $character->proficiencies);
    }

    #[Test]
    public function it_does_not_duplicate_proficiencies_on_repeated_calls(): void
    {
        $lightArmorSlug = 'light-armor-'.uniqid();
        $lightArmor = ProficiencyType::create([
            'name' => 'Light Armor',
            'slug' => 'test:'.$lightArmorSlug,
            'category' => 'armor',
        ]);
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        $fighterClass->proficiencies()->create([
            'proficiency_type' => 'armor',
            'proficiency_type_id' => $lightArmor->id,
        ]);

        $character = Character::factory()->withClass($fighterClass)->create();

        // Call populate twice
        $this->service->populateFromClass($character);
        $this->service->populateFromClass($character);

        // Should still only have 1 proficiency
        $this->assertCount(1, $character->fresh()->proficiencies);
    }

    // =====================
    // Pending Choices Tests
    // =====================

    #[Test]
    public function it_returns_pending_skill_choices(): void
    {
        $athletics = $this->createSkill('Athletics');
        $acrobatics = $this->createSkill('Acrobatics');
        $perception = $this->createSkill('Perception');

        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_1',
            'quantity' => 2,
            'target_type' => 'skill',
            'target_slug' => $athletics->slug,
        ]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_1',
            'target_type' => 'skill',
            'target_slug' => $acrobatics->slug,
        ]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_1',
            'target_type' => 'skill',
            'target_slug' => $perception->slug,
        ]);

        $character = Character::factory()->withClass($fighterClass)->create();

        $choices = $this->service->getPendingChoices($character);

        $this->assertArrayHasKey('class', $choices);
        $this->assertArrayHasKey('skill_choice_1', $choices['class']);
        $this->assertEquals(2, $choices['class']['skill_choice_1']['quantity']);
        $this->assertCount(3, $choices['class']['skill_choice_1']['options']);
    }

    #[Test]
    public function it_tracks_already_chosen_skills_in_selected_array(): void
    {
        $athletics = $this->createSkill('Athletics');
        $acrobatics = $this->createSkill('Acrobatics');

        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_1',
            'quantity' => 1,
            'target_type' => 'skill',
            'target_slug' => $athletics->slug,
        ]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_1',
            'target_type' => 'skill',
            'target_slug' => $acrobatics->slug,
        ]);

        $character = Character::factory()->withClass($fighterClass)->create();

        // Character already chose athletics
        CharacterProficiency::create([
            'character_id' => $character->id,
            'skill_slug' => $athletics->slug,
            'source' => 'class',
            'choice_group' => 'skill_choice_1',
        ]);

        $choices = $this->service->getPendingChoices($character);

        $choiceData = $choices['class']['skill_choice_1'];

        // Should have 0 remaining since quantity (1) is fulfilled
        $this->assertEquals(0, $choiceData['remaining']);

        // Should still return ALL options (not filtered)
        $this->assertCount(2, $choiceData['options']);

        // Should track selected skill slug in separate array
        $this->assertArrayHasKey('selected_skills', $choiceData);
        $this->assertContains($athletics->slug, $choiceData['selected_skills']);
        $this->assertNotContains($acrobatics->slug, $choiceData['selected_skills']);

        // selected_proficiency_types should be empty
        $this->assertArrayHasKey('selected_proficiency_types', $choiceData);
        $this->assertEmpty($choiceData['selected_proficiency_types']);
    }

    // =====================
    // Make Choice Tests
    // =====================

    #[Test]
    public function it_creates_proficiencies_for_skill_choices(): void
    {
        $athletics = $this->createSkill('Athletics');
        $acrobatics = $this->createSkill('Acrobatics');
        $perception = $this->createSkill('Perception');

        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_1',
            'quantity' => 2,
            'target_type' => 'skill',
            'target_slug' => $athletics->slug,
        ]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_1',
            'target_type' => 'skill',
            'target_slug' => $acrobatics->slug,
        ]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_1',
            'target_type' => 'skill',
            'target_slug' => $perception->slug,
        ]);

        $character = Character::factory()->withClass($fighterClass)->create();

        $this->service->makeSkillChoice($character, 'class', 'skill_choice_1', [$athletics->slug, $acrobatics->slug]);

        $this->assertCount(2, $character->proficiencies);
        $this->assertTrue($character->proficiencies->contains('skill_slug', $athletics->slug));
        $this->assertTrue($character->proficiencies->contains('skill_slug', $acrobatics->slug));
    }

    #[Test]
    public function it_rejects_invalid_skill_choices(): void
    {
        $athletics = $this->createSkill('Athletics');
        $stealth = $this->createSkill('Stealth'); // Not in fighter options

        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_1',
            'quantity' => 1,
            'target_type' => 'skill',
            'target_slug' => $athletics->slug,
        ]);

        $character = Character::factory()->withClass($fighterClass)->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->service->makeSkillChoice($character, 'class', 'skill_choice_1', [$stealth->slug]);
    }

    #[Test]
    public function it_rejects_wrong_quantity_of_choices(): void
    {
        $athletics = $this->createSkill('Athletics');
        $acrobatics = $this->createSkill('Acrobatics');

        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_1',
            'quantity' => 2,
            'target_type' => 'skill',
            'target_slug' => $athletics->slug,
        ]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_1',
            'target_type' => 'skill',
            'target_slug' => $acrobatics->slug,
        ]);

        $character = Character::factory()->withClass($fighterClass)->create();

        $this->expectException(\InvalidArgumentException::class);

        // Trying to choose only 1 when 2 are required
        $this->service->makeSkillChoice($character, 'class', 'skill_choice_1', [$athletics->slug]);
    }

    #[Test]
    public function it_replaces_existing_choices_when_resubmitting(): void
    {
        $athletics = $this->createSkill('Athletics');
        $acrobatics = $this->createSkill('Acrobatics');
        $perception = $this->createSkill('Perception');
        $stealth = $this->createSkill('Stealth');

        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_1',
            'quantity' => 2,
            'target_type' => 'skill',
            'target_slug' => $athletics->slug,
        ]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_1',
            'target_type' => 'skill',
            'target_slug' => $acrobatics->slug,
        ]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_1',
            'target_type' => 'skill',
            'target_slug' => $perception->slug,
        ]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_1',
            'target_type' => 'skill',
            'target_slug' => $stealth->slug,
        ]);

        $character = Character::factory()->withClass($fighterClass)->create();

        // First choice: athletics and acrobatics
        $this->service->makeSkillChoice($character, 'class', 'skill_choice_1', [$athletics->slug, $acrobatics->slug]);
        $this->assertCount(2, $character->proficiencies);
        $this->assertTrue($character->proficiencies->contains('skill_slug', $athletics->slug));
        $this->assertTrue($character->proficiencies->contains('skill_slug', $acrobatics->slug));

        // Second choice (change of mind): perception and stealth
        $this->service->makeSkillChoice($character, 'class', 'skill_choice_1', [$perception->slug, $stealth->slug]);

        // Should still have exactly 2 proficiencies, not 4
        $character->refresh();
        $this->assertCount(2, $character->proficiencies);
        $this->assertTrue($character->proficiencies->contains('skill_slug', $perception->slug));
        $this->assertTrue($character->proficiencies->contains('skill_slug', $stealth->slug));
        // Old choices should be gone
        $this->assertFalse($character->proficiencies->contains('skill_slug', $athletics->slug));
        $this->assertFalse($character->proficiencies->contains('skill_slug', $acrobatics->slug));
    }

    #[Test]
    public function it_does_not_delete_proficiencies_from_other_choice_groups(): void
    {
        $athletics = $this->createSkill('Athletics');
        $acrobatics = $this->createSkill('Acrobatics');
        $intimidation = $this->createSkill('Intimidation');
        $survival = $this->createSkill('Survival');

        // Class with two choice groups, with athletics appearing in both
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        // Choice group 1: pick 1 from athletics, acrobatics
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_1',
            'quantity' => 1,
            'target_type' => 'skill',
            'target_slug' => $athletics->slug,
        ]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_1',
            'target_type' => 'skill',
            'target_slug' => $acrobatics->slug,
        ]);
        // Choice group 2: pick 1 from athletics, intimidation, survival
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_2',
            'quantity' => 1,
            'target_type' => 'skill',
            'target_slug' => $athletics->slug,
        ]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_2',
            'target_type' => 'skill',
            'target_slug' => $intimidation->slug,
        ]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighterClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'skill_choice_2',
            'target_type' => 'skill',
            'target_slug' => $survival->slug,
        ]);

        $character = Character::factory()->withClass($fighterClass)->create();

        // Choose athletics from group 1
        $this->service->makeSkillChoice($character, 'class', 'skill_choice_1', [$athletics->slug]);
        $this->assertCount(1, $character->proficiencies);

        // Choose intimidation from group 2
        $this->service->makeSkillChoice($character, 'class', 'skill_choice_2', [$intimidation->slug]);
        $character->refresh();
        $this->assertCount(2, $character->proficiencies);

        // Now change group 1 to acrobatics - should NOT affect group 2's intimidation
        $this->service->makeSkillChoice($character, 'class', 'skill_choice_1', [$acrobatics->slug]);

        $character->refresh();
        $this->assertCount(2, $character->proficiencies);
        $this->assertTrue($character->proficiencies->contains('skill_slug', $acrobatics->slug));
        $this->assertTrue($character->proficiencies->contains('skill_slug', $intimidation->slug));
        // Athletics from group 1 should be gone
        $this->assertFalse($character->proficiencies->contains('skill_slug', $athletics->slug));
    }

    // =====================
    // Subclass Proficiency Tests
    // =====================

    #[Test]
    public function it_includes_subclass_proficiencies_in_character_proficiencies(): void
    {
        // Create proficiency types
        $mediumArmorSlug = 'medium-armor-'.uniqid();
        $heavyArmorSlug = 'heavy-armor-'.uniqid();
        $mediumArmor = ProficiencyType::create([
            'name' => 'Medium Armor',
            'slug' => 'test:'.$mediumArmorSlug,
            'category' => 'armor',
        ]);
        $heavyArmor = ProficiencyType::create([
            'name' => 'Heavy Armor',
            'slug' => 'test:'.$heavyArmorSlug,
            'category' => 'armor',
        ]);

        // Create base class (Cleric) with medium armor
        $clericClass = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric-'.uniqid(),
        ]);
        $clericClass->proficiencies()->create([
            'proficiency_type' => 'armor',
            'proficiency_type_id' => $mediumArmor->id,
        ]);

        // Create subclass (Life Domain) with heavy armor bonus proficiency
        $lifeDomainSlug = 'cleric-life-domain-'.uniqid();
        $lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'slug' => $lifeDomainSlug,
            'parent_class_id' => $clericClass->id,
        ]);
        $lifeDomain->proficiencies()->create([
            'proficiency_type' => 'armor',
            'proficiency_type_id' => $heavyArmor->id,
            'proficiency_name' => 'heavy armor',
            'level' => 1,
        ]);

        // Create character with Cleric + Life Domain subclass
        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_slug' => $clericClass->slug,
            'subclass_slug' => $lifeDomain->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Get character proficiencies
        $proficiencies = $this->service->getCharacterProficiencies($character);

        // Should include BOTH base class AND subclass proficiencies
        $this->assertCount(2, $proficiencies);
        $this->assertTrue($proficiencies->contains('proficiency_type_slug', $mediumArmor->slug));
        $this->assertTrue($proficiencies->contains('proficiency_type_slug', $heavyArmor->slug));
    }

    #[Test]
    public function it_deduplicates_proficiencies_from_class_and_subclass(): void
    {
        // Create proficiency type
        $heavyArmorSlug = 'heavy-armor-'.uniqid();
        $heavyArmor = ProficiencyType::create([
            'name' => 'Heavy Armor',
            'slug' => 'test:'.$heavyArmorSlug,
            'category' => 'armor',
        ]);

        // Create base class with heavy armor
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter-'.uniqid(),
        ]);
        $fighterClass->proficiencies()->create([
            'proficiency_type' => 'armor',
            'proficiency_type_id' => $heavyArmor->id,
        ]);

        // Create subclass that ALSO grants heavy armor (hypothetical)
        $championSlug = 'fighter-champion-'.uniqid();
        $champion = CharacterClass::factory()->create([
            'name' => 'Champion',
            'slug' => $championSlug,
            'parent_class_id' => $fighterClass->id,
        ]);
        $champion->proficiencies()->create([
            'proficiency_type' => 'armor',
            'proficiency_type_id' => $heavyArmor->id,
        ]);

        // Create character with Fighter + Champion subclass
        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_slug' => $fighterClass->slug,
            'subclass_slug' => $champion->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Get character proficiencies
        $proficiencies = $this->service->getCharacterProficiencies($character);

        // Should only have ONE heavy armor proficiency, not duplicated
        $this->assertCount(1, $proficiencies);
        $this->assertTrue($proficiencies->contains('proficiency_type_slug', $heavyArmor->slug));
    }

    #[Test]
    public function it_returns_pending_choices_from_subclass_features(): void
    {
        // Create skills
        $animalHandling = $this->createSkill('Animal Handling');
        $nature = $this->createSkill('Nature');
        $survival = $this->createSkill('Survival');

        // Create Cleric class
        $clericClass = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric-'.uniqid(),
        ]);

        // Create Nature Domain subclass
        $natureDomainSlug = 'cleric-nature-domain-'.uniqid();
        $natureDomain = CharacterClass::factory()->create([
            'name' => 'Nature Domain',
            'slug' => $natureDomainSlug,
            'parent_class_id' => $clericClass->id,
        ]);

        // Create "Acolyte of Nature" feature with skill choice
        $acolyteFeature = $natureDomain->features()->create([
            'feature_name' => 'Acolyte of Nature',
            'level' => 1,
            'is_optional' => false,
            'description' => 'Choose one skill from Animal Handling, Nature, or Survival.',
        ]);

        // Add skill choices to the feature via EntityChoice
        EntityChoice::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $acolyteFeature->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'feature_skill_choice_1',
            'quantity' => 1,
            'target_type' => 'skill',
            'target_slug' => $animalHandling->slug,
        ]);
        EntityChoice::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $acolyteFeature->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'feature_skill_choice_1',
            'target_type' => 'skill',
            'target_slug' => $nature->slug,
        ]);
        EntityChoice::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $acolyteFeature->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'feature_skill_choice_1',
            'target_type' => 'skill',
            'target_slug' => $survival->slug,
        ]);

        // Create character with Cleric + Nature Domain subclass
        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_slug' => $clericClass->slug,
            'subclass_slug' => $natureDomain->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Call getPendingChoices
        $choices = $this->service->getPendingChoices($character);

        // Assert that subclass_feature choices are returned
        $this->assertArrayHasKey('subclass_feature', $choices);
        $this->assertArrayHasKey('Acolyte of Nature:feature_skill_choice_1', $choices['subclass_feature']);

        $choiceData = $choices['subclass_feature']['Acolyte of Nature:feature_skill_choice_1'];
        $this->assertEquals(1, $choiceData['quantity']);
        $this->assertEquals(1, $choiceData['remaining']);
        $this->assertCount(3, $choiceData['options']);

        // Verify options include the three skills
        $skillSlugs = collect($choiceData['options'])->pluck('skill_slug')->filter();
        $this->assertContains($animalHandling->slug, $skillSlugs);
        $this->assertContains($nature->slug, $skillSlugs);
        $this->assertContains($survival->slug, $skillSlugs);
    }

    #[Test]
    public function it_can_make_skill_choice_from_subclass_feature(): void
    {
        // Create skills
        $animalHandling = $this->createSkill('Animal Handling');
        $nature = $this->createSkill('Nature');
        $survival = $this->createSkill('Survival');

        // Create Cleric class
        $clericClass = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric-'.uniqid(),
        ]);

        // Create Nature Domain subclass
        $natureDomainSlug = 'cleric-nature-domain-'.uniqid();
        $natureDomain = CharacterClass::factory()->create([
            'name' => 'Nature Domain',
            'slug' => $natureDomainSlug,
            'parent_class_id' => $clericClass->id,
        ]);

        // Create "Acolyte of Nature" feature with skill choice
        $acolyteFeature = $natureDomain->features()->create([
            'feature_name' => 'Acolyte of Nature',
            'level' => 1,
            'is_optional' => false,
            'description' => 'Choose one skill from Animal Handling, Nature, or Survival.',
        ]);

        // Add skill choices to the feature via EntityChoice
        EntityChoice::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $acolyteFeature->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'feature_skill_choice_1',
            'quantity' => 1,
            'target_type' => 'skill',
            'target_slug' => $animalHandling->slug,
        ]);
        EntityChoice::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $acolyteFeature->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'feature_skill_choice_1',
            'target_type' => 'skill',
            'target_slug' => $nature->slug,
        ]);
        EntityChoice::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $acolyteFeature->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'feature_skill_choice_1',
            'target_type' => 'skill',
            'target_slug' => $survival->slug,
        ]);

        // Create character with Cleric + Nature Domain subclass
        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_slug' => $clericClass->slug,
            'subclass_slug' => $natureDomain->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Make the skill choice
        $this->service->makeSkillChoice($character, 'subclass_feature', 'feature_skill_choice_1', [$nature->slug]);

        // Assert proficiency was created
        $this->assertCount(1, $character->proficiencies);
        $this->assertTrue($character->proficiencies->contains('skill_slug', $nature->slug));
        $this->assertEquals('subclass_feature', $character->proficiencies->first()->source);
        $this->assertEquals('feature_skill_choice_1', $character->proficiencies->first()->choice_group);
    }

    /**
     * Issue #476 fix: Test that makeSkillChoice works with full choice_group format
     * (e.g., "Acolyte of Nature (Nature Domain):feature_skill_choice_1")
     */
    #[Test]
    public function it_can_make_skill_choice_from_subclass_feature_with_full_choice_group_format(): void
    {
        // Create skills
        $animalHandling = $this->createSkill('Animal Handling');
        $nature = $this->createSkill('Nature');
        $survival = $this->createSkill('Survival');

        // Create Cleric class
        $clericClass = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric-'.uniqid(),
        ]);

        // Create Nature Domain subclass
        $natureDomainSlug = 'cleric-nature-domain-'.uniqid();
        $natureDomain = CharacterClass::factory()->create([
            'name' => 'Nature Domain',
            'slug' => $natureDomainSlug,
            'parent_class_id' => $clericClass->id,
        ]);

        // Create "Acolyte of Nature" feature with skill choice
        $acolyteFeature = $natureDomain->features()->create([
            'feature_name' => 'Acolyte of Nature (Nature Domain)',
            'level' => 1,
            'is_optional' => false,
            'description' => 'Choose one skill from Animal Handling, Nature, or Survival.',
        ]);

        // Add skill choices to the feature via EntityChoice
        EntityChoice::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $acolyteFeature->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'feature_skill_choice_1',
            'quantity' => 1,
            'target_type' => 'skill',
            'target_slug' => $animalHandling->slug,
        ]);
        EntityChoice::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $acolyteFeature->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'feature_skill_choice_1',
            'target_type' => 'skill',
            'target_slug' => $nature->slug,
        ]);
        EntityChoice::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $acolyteFeature->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'feature_skill_choice_1',
            'target_type' => 'skill',
            'target_slug' => $survival->slug,
        ]);

        // Create character with Cleric + Nature Domain subclass
        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_slug' => $clericClass->slug,
            'subclass_slug' => $natureDomain->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Make the skill choice using the FULL choice_group format (as received from frontend)
        // This is the format returned by getPendingChoices: "FeatureName:base_choice_group"
        $fullChoiceGroup = 'Acolyte of Nature (Nature Domain):feature_skill_choice_1';
        $baseChoiceGroup = 'feature_skill_choice_1';
        $this->service->makeSkillChoice($character, 'subclass_feature', $fullChoiceGroup, [$nature->slug]);

        // Assert proficiency was created
        $this->assertCount(1, $character->proficiencies);
        $this->assertTrue($character->proficiencies->contains('skill_slug', $nature->slug));
        $this->assertEquals('subclass_feature', $character->proficiencies->first()->source);
        // Note: For subclass_feature, the choice_group stored in DB is the BASE format
        // This ensures consistency with read queries in getChoicesFromEntity which use base names
        $this->assertEquals($baseChoiceGroup, $character->proficiencies->first()->choice_group);
    }

    /**
     * Issue #479 fix: Test that subclass_feature selections are returned correctly in getPendingChoices
     * This is the round-trip test: make a choice, then verify it shows up in selected array
     */
    #[Test]
    public function it_returns_selected_skills_for_subclass_feature_after_making_choice(): void
    {
        // Create skills
        $animalHandling = $this->createSkill('Animal Handling');
        $nature = $this->createSkill('Nature');
        $survival = $this->createSkill('Survival');

        // Create Cleric class
        $clericClass = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric-'.uniqid(),
        ]);

        // Create Nature Domain subclass
        $natureDomainSlug = 'cleric-nature-domain-'.uniqid();
        $natureDomain = CharacterClass::factory()->create([
            'name' => 'Nature Domain',
            'slug' => $natureDomainSlug,
            'parent_class_id' => $clericClass->id,
        ]);

        // Create "Acolyte of Nature" feature with skill choice
        $acolyteFeature = $natureDomain->features()->create([
            'feature_name' => 'Acolyte of Nature (Nature Domain)',
            'level' => 1,
            'is_optional' => false,
            'description' => 'Choose one skill from Animal Handling, Nature, or Survival.',
        ]);

        // Add skill choices to the feature via EntityChoice
        EntityChoice::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $acolyteFeature->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'feature_skill_choice_1',
            'quantity' => 1,
            'target_type' => 'skill',
            'target_slug' => $animalHandling->slug,
        ]);
        EntityChoice::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $acolyteFeature->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'feature_skill_choice_1',
            'target_type' => 'skill',
            'target_slug' => $nature->slug,
        ]);
        EntityChoice::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $acolyteFeature->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'skill',
            'choice_group' => 'feature_skill_choice_1',
            'target_type' => 'skill',
            'target_slug' => $survival->slug,
        ]);

        // Create character with Cleric + Nature Domain subclass
        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_slug' => $clericClass->slug,
            'subclass_slug' => $natureDomain->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Step 1: Get pending choices BEFORE making any selection
        $choicesBefore = $this->service->getPendingChoices($character);
        $this->assertArrayHasKey('subclass_feature', $choicesBefore);
        $this->assertArrayHasKey('Acolyte of Nature (Nature Domain):feature_skill_choice_1', $choicesBefore['subclass_feature']);
        $choiceBefore = $choicesBefore['subclass_feature']['Acolyte of Nature (Nature Domain):feature_skill_choice_1'];
        $this->assertEquals(1, $choiceBefore['quantity']);
        $this->assertEquals(1, $choiceBefore['remaining']);
        $this->assertEmpty($choiceBefore['selected_skills']);

        // Step 2: Make the skill choice using full choice_group format (as frontend does)
        $fullChoiceGroup = 'Acolyte of Nature (Nature Domain):feature_skill_choice_1';
        $this->service->makeSkillChoice($character, 'subclass_feature', $fullChoiceGroup, [$nature->slug]);

        // Step 3: Get pending choices AFTER making selection - THIS is what was broken in #479
        $character->refresh();
        $choicesAfter = $this->service->getPendingChoices($character);
        $this->assertArrayHasKey('subclass_feature', $choicesAfter);
        $this->assertArrayHasKey('Acolyte of Nature (Nature Domain):feature_skill_choice_1', $choicesAfter['subclass_feature']);
        $choiceAfter = $choicesAfter['subclass_feature']['Acolyte of Nature (Nature Domain):feature_skill_choice_1'];

        // Verify the choice now shows as resolved
        $this->assertEquals(1, $choiceAfter['quantity']);
        $this->assertEquals(0, $choiceAfter['remaining'], 'remaining should be 0 after selection');
        $this->assertContains($nature->slug, $choiceAfter['selected_skills'], 'selected_skills should contain the chosen skill');
    }

    // =====================
    // Tool Proficiency Choice Tests (Issue #539)
    // =====================

    #[Test]
    public function it_returns_tool_options_for_unrestricted_tool_choice(): void
    {
        // Create tool proficiency types
        $smithsTools = ProficiencyType::create([
            'name' => "Smith's Tools",
            'slug' => 'test:smiths-tools-'.uniqid(),
            'category' => 'tool',
            'subcategory' => 'artisan',
        ]);
        $brewersSupplies = ProficiencyType::create([
            'name' => "Brewer's Supplies",
            'slug' => 'test:brewers-supplies-'.uniqid(),
            'category' => 'tool',
            'subcategory' => 'artisan',
        ]);
        $thievesTools = ProficiencyType::create([
            'name' => "Thieves' Tools",
            'slug' => 'test:thieves-tools-'.uniqid(),
            'category' => 'tool',
            'subcategory' => null, // Not artisan
        ]);

        // Create class with unrestricted tool choice (no target_type, no subcategory constraint)
        $warforgedLike = CharacterClass::factory()->create(['name' => 'TestClass', 'slug' => 'testclass-'.uniqid()]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $warforgedLike->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'tool',
            'choice_group' => 'tool_choice_1',
            'quantity' => 1,
            'target_type' => null,
            'target_slug' => null,
            'constraints' => null, // No subcategory constraint - all tools are valid
        ]);

        $character = Character::factory()->withClass($warforgedLike)->create();

        // Get pending choices
        $choices = $this->service->getPendingChoices($character);

        // Assert tool choice exists and has options
        $this->assertArrayHasKey('class', $choices);
        $this->assertArrayHasKey('tool_choice_1', $choices['class']);

        $toolChoice = $choices['class']['tool_choice_1'];
        $this->assertEquals('tool', $toolChoice['proficiency_type']);
        $this->assertEquals(1, $toolChoice['quantity']);
        $this->assertEquals(1, $toolChoice['remaining']);

        // Options should contain ALL tools (not filtered by subcategory)
        $this->assertGreaterThanOrEqual(3, count($toolChoice['options']), 'Should have tool options');

        // Verify the options have correct structure
        $optionSlugs = collect($toolChoice['options'])->pluck('proficiency_type_slug')->filter()->all();
        $this->assertContains($smithsTools->slug, $optionSlugs);
        $this->assertContains($brewersSupplies->slug, $optionSlugs);
        $this->assertContains($thievesTools->slug, $optionSlugs);
    }

    #[Test]
    public function it_returns_only_subcategory_tools_when_constraint_set(): void
    {
        // Create tool proficiency types
        $smithsTools = ProficiencyType::create([
            'name' => "Smith's Tools",
            'slug' => 'test:smiths-tools-'.uniqid(),
            'category' => 'tool',
            'subcategory' => 'artisan',
        ]);
        $brewersSupplies = ProficiencyType::create([
            'name' => "Brewer's Supplies",
            'slug' => 'test:brewers-supplies-'.uniqid(),
            'category' => 'tool',
            'subcategory' => 'artisan',
        ]);
        $thievesTools = ProficiencyType::create([
            'name' => "Thieves' Tools",
            'slug' => 'test:thieves-tools-'.uniqid(),
            'category' => 'tool',
            'subcategory' => 'misc', // NOT artisan
        ]);

        // Create class with artisan tools choice (subcategory constraint)
        $artificerClass = CharacterClass::factory()->create(['name' => 'Artificer', 'slug' => 'artificer-'.uniqid()]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $artificerClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'tool',
            'choice_group' => 'tool_choice_1',
            'quantity' => 1,
            'target_type' => null,
            'target_slug' => null,
            'constraints' => ['subcategory' => 'artisan'], // Only artisan tools
        ]);

        $character = Character::factory()->withClass($artificerClass)->create();

        // Get pending choices
        $choices = $this->service->getPendingChoices($character);

        // Assert tool choice exists and has options
        $this->assertArrayHasKey('class', $choices);
        $this->assertArrayHasKey('tool_choice_1', $choices['class']);

        $toolChoice = $choices['class']['tool_choice_1'];
        $this->assertEquals('tool', $toolChoice['proficiency_type']);
        $this->assertEquals('artisan', $toolChoice['proficiency_subcategory']);

        // Options should contain ONLY artisan tools
        $optionSlugs = collect($toolChoice['options'])->pluck('proficiency_type_slug')->filter()->all();
        $this->assertContains($smithsTools->slug, $optionSlugs);
        $this->assertContains($brewersSupplies->slug, $optionSlugs);
        $this->assertNotContains($thievesTools->slug, $optionSlugs, 'Should NOT include non-artisan tools');
    }

    #[Test]
    public function it_returns_tool_options_for_choice_with_placeholder_target_slug(): void
    {
        // Create tool proficiency types
        $smithsTools = ProficiencyType::create([
            'name' => "Smith's Tools",
            'slug' => 'test:smiths-tools-'.uniqid(),
            'category' => 'tool',
            'subcategory' => 'artisan',
        ]);

        // Create class with "restricted" tool choice that has placeholder text as target_slug
        // This is legacy data - target_slug doesn't match any real proficiency type
        $legacyClass = CharacterClass::factory()->create(['name' => 'LegacyClass', 'slug' => 'legacy-'.uniqid()]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $legacyClass->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'tool',
            'choice_group' => 'tool_choice_1',
            'quantity' => 1,
            'target_type' => 'proficiency_type',
            'target_slug' => 'one-type-of-artisans-tools-of-your-choice', // Placeholder text, not real slug
            'constraints' => null, // No constraint - falls back to all tools
        ]);

        $character = Character::factory()->withClass($legacyClass)->create();

        // Get pending choices
        $choices = $this->service->getPendingChoices($character);

        // Assert tool choice exists and has options (should fall back to all tools)
        $this->assertArrayHasKey('class', $choices);
        $this->assertArrayHasKey('tool_choice_1', $choices['class']);

        $toolChoice = $choices['class']['tool_choice_1'];
        $this->assertEquals('tool', $toolChoice['proficiency_type']);
        $this->assertGreaterThanOrEqual(1, count($toolChoice['options']), 'Should fall back to category lookup when target_slug invalid');

        // Should include our created tool
        $optionSlugs = collect($toolChoice['options'])->pluck('proficiency_type_slug')->filter()->all();
        $this->assertContains($smithsTools->slug, $optionSlugs);
    }

    #[Test]
    public function it_returns_gaming_set_options_for_gaming_subcategory(): void
    {
        // Gaming sets are stored with category='gaming_set' (as standalone categories)
        // but background choices may use subcategory='gaming'
        $diceSet = ProficiencyType::create([
            'name' => 'Dice Set',
            'slug' => 'test:dice-set-'.uniqid(),
            'category' => 'gaming_set', // Standalone category
            'subcategory' => null,
        ]);
        $playingCards = ProficiencyType::create([
            'name' => 'Playing Card Set',
            'slug' => 'test:playing-cards-'.uniqid(),
            'category' => 'gaming_set',
            'subcategory' => null,
        ]);
        $regularTool = ProficiencyType::create([
            'name' => 'Regular Tool',
            'slug' => 'test:regular-tool-'.uniqid(),
            'category' => 'tool',
            'subcategory' => 'misc',
        ]);

        // Create background with gaming set choice (using 'gaming' subcategory)
        $knightBackground = \App\Models\Background::factory()->create([
            'name' => 'Knight of the Order',
            'slug' => 'test:knight-'.uniqid(),
        ]);
        EntityChoice::create([
            'reference_type' => \App\Models\Background::class,
            'reference_id' => $knightBackground->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'tool',
            'choice_group' => 'tool_choice_1',
            'quantity' => 1,
            'target_type' => null,
            'target_slug' => null,
            'constraints' => ['subcategory' => 'gaming'], // Uses 'gaming' not 'gaming_set'
        ]);

        $character = Character::factory()->withBackground($knightBackground)->create();

        // Get pending choices
        $choices = $this->service->getPendingChoices($character);

        // Assert gaming set choice exists and has options
        $this->assertArrayHasKey('background', $choices);
        $this->assertArrayHasKey('tool_choice_1', $choices['background']);

        $gamingChoice = $choices['background']['tool_choice_1'];
        $this->assertEquals('tool', $gamingChoice['proficiency_type']);
        $this->assertEquals('gaming', $gamingChoice['proficiency_subcategory']);

        // Should have gaming set options (from gaming_set category)
        $optionSlugs = collect($gamingChoice['options'])->pluck('proficiency_type_slug')->filter()->all();
        $this->assertContains($diceSet->slug, $optionSlugs, 'Should include gaming sets');
        $this->assertContains($playingCards->slug, $optionSlugs, 'Should include gaming sets');
        $this->assertNotContains($regularTool->slug, $optionSlugs, 'Should NOT include regular tools');
    }

    #[Test]
    public function it_returns_musical_instrument_options_for_musical_subcategory(): void
    {
        // Musical instruments are stored with category='musical_instrument' (as standalone categories)
        // but background choices may use subcategory='musical'
        $lute = ProficiencyType::create([
            'name' => 'Lute',
            'slug' => 'test:lute-'.uniqid(),
            'category' => 'musical_instrument', // Standalone category
            'subcategory' => null,
        ]);
        $drums = ProficiencyType::create([
            'name' => 'Drums',
            'slug' => 'test:drums-'.uniqid(),
            'category' => 'musical_instrument',
            'subcategory' => null,
        ]);
        $regularTool = ProficiencyType::create([
            'name' => 'Artisan Tool',
            'slug' => 'test:artisan-tool-'.uniqid(),
            'category' => 'tool',
            'subcategory' => 'artisan',
        ]);

        // Create background with musical instrument choice (using 'musical' subcategory)
        $entertainerBackground = \App\Models\Background::factory()->create([
            'name' => 'Entertainer',
            'slug' => 'test:entertainer-'.uniqid(),
        ]);
        EntityChoice::create([
            'reference_type' => \App\Models\Background::class,
            'reference_id' => $entertainerBackground->id,
            'choice_type' => 'proficiency',
            'proficiency_type' => 'tool',
            'choice_group' => 'tool_choice_1',
            'quantity' => 1,
            'target_type' => null,
            'target_slug' => null,
            'constraints' => ['subcategory' => 'musical'], // Uses 'musical' not 'musical_instrument'
        ]);

        $character = Character::factory()->withBackground($entertainerBackground)->create();

        // Get pending choices
        $choices = $this->service->getPendingChoices($character);

        // Assert musical instrument choice exists and has options
        $this->assertArrayHasKey('background', $choices);
        $this->assertArrayHasKey('tool_choice_1', $choices['background']);

        $musicalChoice = $choices['background']['tool_choice_1'];
        $this->assertEquals('tool', $musicalChoice['proficiency_type']);
        $this->assertEquals('musical', $musicalChoice['proficiency_subcategory']);

        // Should have musical instrument options (from musical_instrument category)
        $optionSlugs = collect($musicalChoice['options'])->pluck('proficiency_type_slug')->filter()->all();
        $this->assertContains($lute->slug, $optionSlugs, 'Should include musical instruments');
        $this->assertContains($drums->slug, $optionSlugs, 'Should include musical instruments');
        $this->assertNotContains($regularTool->slug, $optionSlugs, 'Should NOT include regular tools');
    }
}
