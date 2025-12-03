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

        return Skill::create([
            'name' => $baseName.' '.$uniqueId,
            'slug' => strtolower($baseName).'-'.$uniqueId,
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
        $lightArmor = ProficiencyType::create([
            'name' => 'Light Armor',
            'slug' => 'light-armor-'.uniqid(),
            'category' => 'armor',
        ]);
        $heavyArmor = ProficiencyType::create([
            'name' => 'Heavy Armor',
            'slug' => 'heavy-armor-'.uniqid(),
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
        $this->assertTrue($character->proficiencies->contains('proficiency_type_id', $lightArmor->id));
        $this->assertTrue($character->proficiencies->contains('proficiency_type_id', $heavyArmor->id));
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
        $lightArmor = ProficiencyType::create([
            'name' => 'Light Armor',
            'slug' => 'light-armor-'.uniqid(),
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
    public function it_excludes_already_chosen_skills_from_pending(): void
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
            'skill_id' => $athletics->id,
            'source' => 'class',
        ]);

        $choices = $this->service->getPendingChoices($character);

        // Should have 0 remaining since quantity (1) is fulfilled
        $this->assertEquals(0, $choices['class']['skill_choice_1']['remaining']);
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

        $this->service->makeSkillChoice($character, 'class', 'skill_choice_1', [$athletics->id, $acrobatics->id]);

        $this->assertCount(2, $character->proficiencies);
        $this->assertTrue($character->proficiencies->contains('skill_id', $athletics->id));
        $this->assertTrue($character->proficiencies->contains('skill_id', $acrobatics->id));
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

        $this->service->makeSkillChoice($character, 'class', 'skill_choice_1', [$stealth->id]);
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
        $this->service->makeSkillChoice($character, 'class', 'skill_choice_1', [$athletics->id]);
    }
}
