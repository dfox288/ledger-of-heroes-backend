<?php

namespace Tests\Unit\Services;

use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterProficiency;
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
            'slug' => $slug,
            'full_slug' => 'test:'.$slug,
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
            'slug' => $lightArmorSlug,
            'full_slug' => 'test:'.$lightArmorSlug,
            'category' => 'armor',
        ]);
        $heavyArmor = ProficiencyType::create([
            'name' => 'Heavy Armor',
            'slug' => $heavyArmorSlug,
            'full_slug' => 'test:'.$heavyArmorSlug,
            'category' => 'armor',
        ]);

        // Create class with fixed armor proficiencies
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        $fighterClass->proficiencies()->createMany([
            ['proficiency_type' => 'armor', 'proficiency_type_id' => $lightArmor->id, 'is_choice' => false],
            ['proficiency_type' => 'armor', 'proficiency_type_id' => $heavyArmor->id, 'is_choice' => false],
        ]);

        // Create character with this class
        $character = Character::factory()->withClass($fighterClass)->create();

        // Populate proficiencies
        $this->service->populateFromClass($character);

        // Assert proficiencies were created
        $this->assertCount(2, $character->proficiencies);
        $this->assertTrue($character->proficiencies->contains('proficiency_type_slug', $lightArmor->full_slug));
        $this->assertTrue($character->proficiencies->contains('proficiency_type_slug', $heavyArmor->full_slug));
        $this->assertTrue($character->proficiencies->every(fn ($p) => $p->source === 'class'));
    }

    #[Test]
    public function it_does_not_populate_choice_proficiencies_automatically(): void
    {
        // Create skills
        $athletics = $this->createSkill('Athletics');
        $acrobatics = $this->createSkill('Acrobatics');

        // Create class with skill choices
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        $fighterClass->proficiencies()->createMany([
            ['proficiency_type' => 'skill', 'skill_id' => $athletics->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1', 'quantity' => 2],
            ['proficiency_type' => 'skill', 'skill_id' => $acrobatics->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1'],
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
            'slug' => $lightArmorSlug,
            'full_slug' => 'test:'.$lightArmorSlug,
            'category' => 'armor',
        ]);
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        $fighterClass->proficiencies()->create([
            'proficiency_type' => 'armor',
            'proficiency_type_id' => $lightArmor->id,
            'is_choice' => false,
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
        $fighterClass->proficiencies()->createMany([
            ['proficiency_type' => 'skill', 'skill_id' => $athletics->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1', 'quantity' => 2],
            ['proficiency_type' => 'skill', 'skill_id' => $acrobatics->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1'],
            ['proficiency_type' => 'skill', 'skill_id' => $perception->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1'],
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
        $fighterClass->proficiencies()->createMany([
            ['proficiency_type' => 'skill', 'skill_id' => $athletics->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1', 'quantity' => 1],
            ['proficiency_type' => 'skill', 'skill_id' => $acrobatics->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1'],
        ]);

        $character = Character::factory()->withClass($fighterClass)->create();

        // Character already chose athletics
        CharacterProficiency::create([
            'character_id' => $character->id,
            'skill_slug' => $athletics->full_slug,
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
        $this->assertContains($athletics->full_slug, $choiceData['selected_skills']);
        $this->assertNotContains($acrobatics->full_slug, $choiceData['selected_skills']);

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
        $fighterClass->proficiencies()->createMany([
            ['proficiency_type' => 'skill', 'skill_id' => $athletics->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1', 'quantity' => 2],
            ['proficiency_type' => 'skill', 'skill_id' => $acrobatics->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1'],
            ['proficiency_type' => 'skill', 'skill_id' => $perception->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1'],
        ]);

        $character = Character::factory()->withClass($fighterClass)->create();

        $this->service->makeSkillChoice($character, 'class', 'skill_choice_1', [$athletics->full_slug, $acrobatics->full_slug]);

        $this->assertCount(2, $character->proficiencies);
        $this->assertTrue($character->proficiencies->contains('skill_slug', $athletics->full_slug));
        $this->assertTrue($character->proficiencies->contains('skill_slug', $acrobatics->full_slug));
    }

    #[Test]
    public function it_rejects_invalid_skill_choices(): void
    {
        $athletics = $this->createSkill('Athletics');
        $stealth = $this->createSkill('Stealth'); // Not in fighter options

        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        $fighterClass->proficiencies()->create([
            'proficiency_type' => 'skill',
            'skill_id' => $athletics->id,
            'is_choice' => true,
            'choice_group' => 'skill_choice_1',
            'quantity' => 1,
        ]);

        $character = Character::factory()->withClass($fighterClass)->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->service->makeSkillChoice($character, 'class', 'skill_choice_1', [$stealth->full_slug]);
    }

    #[Test]
    public function it_rejects_wrong_quantity_of_choices(): void
    {
        $athletics = $this->createSkill('Athletics');
        $acrobatics = $this->createSkill('Acrobatics');

        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        $fighterClass->proficiencies()->createMany([
            ['proficiency_type' => 'skill', 'skill_id' => $athletics->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1', 'quantity' => 2],
            ['proficiency_type' => 'skill', 'skill_id' => $acrobatics->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1'],
        ]);

        $character = Character::factory()->withClass($fighterClass)->create();

        $this->expectException(\InvalidArgumentException::class);

        // Trying to choose only 1 when 2 are required
        $this->service->makeSkillChoice($character, 'class', 'skill_choice_1', [$athletics->full_slug]);
    }

    #[Test]
    public function it_replaces_existing_choices_when_resubmitting(): void
    {
        $athletics = $this->createSkill('Athletics');
        $acrobatics = $this->createSkill('Acrobatics');
        $perception = $this->createSkill('Perception');
        $stealth = $this->createSkill('Stealth');

        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter-'.uniqid()]);
        $fighterClass->proficiencies()->createMany([
            ['proficiency_type' => 'skill', 'skill_id' => $athletics->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1', 'quantity' => 2],
            ['proficiency_type' => 'skill', 'skill_id' => $acrobatics->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1'],
            ['proficiency_type' => 'skill', 'skill_id' => $perception->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1'],
            ['proficiency_type' => 'skill', 'skill_id' => $stealth->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1'],
        ]);

        $character = Character::factory()->withClass($fighterClass)->create();

        // First choice: athletics and acrobatics
        $this->service->makeSkillChoice($character, 'class', 'skill_choice_1', [$athletics->full_slug, $acrobatics->full_slug]);
        $this->assertCount(2, $character->proficiencies);
        $this->assertTrue($character->proficiencies->contains('skill_slug', $athletics->full_slug));
        $this->assertTrue($character->proficiencies->contains('skill_slug', $acrobatics->full_slug));

        // Second choice (change of mind): perception and stealth
        $this->service->makeSkillChoice($character, 'class', 'skill_choice_1', [$perception->full_slug, $stealth->full_slug]);

        // Should still have exactly 2 proficiencies, not 4
        $character->refresh();
        $this->assertCount(2, $character->proficiencies);
        $this->assertTrue($character->proficiencies->contains('skill_slug', $perception->full_slug));
        $this->assertTrue($character->proficiencies->contains('skill_slug', $stealth->full_slug));
        // Old choices should be gone
        $this->assertFalse($character->proficiencies->contains('skill_slug', $athletics->full_slug));
        $this->assertFalse($character->proficiencies->contains('skill_slug', $acrobatics->full_slug));
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
        $fighterClass->proficiencies()->createMany([
            // Choice group 1: pick 1 from athletics, acrobatics
            ['proficiency_type' => 'skill', 'skill_id' => $athletics->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1', 'quantity' => 1],
            ['proficiency_type' => 'skill', 'skill_id' => $acrobatics->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1'],
            // Choice group 2: pick 1 from athletics, intimidation, survival
            ['proficiency_type' => 'skill', 'skill_id' => $athletics->id, 'is_choice' => true, 'choice_group' => 'skill_choice_2', 'quantity' => 1],
            ['proficiency_type' => 'skill', 'skill_id' => $intimidation->id, 'is_choice' => true, 'choice_group' => 'skill_choice_2'],
            ['proficiency_type' => 'skill', 'skill_id' => $survival->id, 'is_choice' => true, 'choice_group' => 'skill_choice_2'],
        ]);

        $character = Character::factory()->withClass($fighterClass)->create();

        // Choose athletics from group 1
        $this->service->makeSkillChoice($character, 'class', 'skill_choice_1', [$athletics->full_slug]);
        $this->assertCount(1, $character->proficiencies);

        // Choose intimidation from group 2
        $this->service->makeSkillChoice($character, 'class', 'skill_choice_2', [$intimidation->full_slug]);
        $character->refresh();
        $this->assertCount(2, $character->proficiencies);

        // Now change group 1 to acrobatics - should NOT affect group 2's intimidation
        $this->service->makeSkillChoice($character, 'class', 'skill_choice_1', [$acrobatics->full_slug]);

        $character->refresh();
        $this->assertCount(2, $character->proficiencies);
        $this->assertTrue($character->proficiencies->contains('skill_slug', $acrobatics->full_slug));
        $this->assertTrue($character->proficiencies->contains('skill_slug', $intimidation->full_slug));
        // Athletics from group 1 should be gone
        $this->assertFalse($character->proficiencies->contains('skill_slug', $athletics->full_slug));
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
            'slug' => $mediumArmorSlug,
            'full_slug' => 'test:'.$mediumArmorSlug,
            'category' => 'armor',
        ]);
        $heavyArmor = ProficiencyType::create([
            'name' => 'Heavy Armor',
            'slug' => $heavyArmorSlug,
            'full_slug' => 'test:'.$heavyArmorSlug,
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
            'is_choice' => false,
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
            'is_choice' => false,
            'level' => 1,
        ]);

        // Create character with Cleric + Life Domain subclass
        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_slug' => $clericClass->full_slug,
            'subclass_slug' => $lifeDomain->full_slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Get character proficiencies
        $proficiencies = $this->service->getCharacterProficiencies($character);

        // Should include BOTH base class AND subclass proficiencies
        $this->assertCount(2, $proficiencies);
        $this->assertTrue($proficiencies->contains('proficiency_type_slug', $mediumArmor->full_slug));
        $this->assertTrue($proficiencies->contains('proficiency_type_slug', $heavyArmor->full_slug));
    }

    #[Test]
    public function it_deduplicates_proficiencies_from_class_and_subclass(): void
    {
        // Create proficiency type
        $heavyArmorSlug = 'heavy-armor-'.uniqid();
        $heavyArmor = ProficiencyType::create([
            'name' => 'Heavy Armor',
            'slug' => $heavyArmorSlug,
            'full_slug' => 'test:'.$heavyArmorSlug,
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
            'is_choice' => false,
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
            'is_choice' => false,
        ]);

        // Create character with Fighter + Champion subclass
        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_slug' => $fighterClass->full_slug,
            'subclass_slug' => $champion->full_slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Get character proficiencies
        $proficiencies = $this->service->getCharacterProficiencies($character);

        // Should only have ONE heavy armor proficiency, not duplicated
        $this->assertCount(1, $proficiencies);
        $this->assertTrue($proficiencies->contains('proficiency_type_slug', $heavyArmor->full_slug));
    }
}
