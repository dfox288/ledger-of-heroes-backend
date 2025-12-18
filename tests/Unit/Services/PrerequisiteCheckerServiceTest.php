<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\AbilityScore;
use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\EntityPrerequisite;
use App\Models\Feat;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Models\Skill;
use App\Services\PrerequisiteCheckerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrerequisiteCheckerServiceTest extends TestCase
{
    use RefreshDatabase;

    private PrerequisiteCheckerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PrerequisiteCheckerService;
    }

    #[Test]
    public function it_passes_when_feat_has_no_prerequisites(): void
    {
        $character = Character::factory()->create([
            'strength' => 10,
        ]);
        $feat = Feat::factory()->create();

        $result = $this->service->checkFeatPrerequisites($character, $feat);

        $this->assertTrue($result->met);
        $this->assertEmpty($result->unmet);
    }

    #[Test]
    public function it_passes_when_ability_score_prerequisite_met(): void
    {
        $character = Character::factory()->create([
            'strength' => 15,
        ]);

        $feat = Feat::factory()->create();
        $strength = AbilityScore::firstOrCreate(
            ['code' => 'STR'],
            ['name' => 'Strength']
        );

        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertTrue($result->met);
        $this->assertEmpty($result->unmet);
    }

    #[Test]
    public function it_fails_when_ability_score_prerequisite_not_met(): void
    {
        $character = Character::factory()->create([
            'strength' => 10,
        ]);

        $feat = Feat::factory()->create();
        $strength = AbilityScore::firstOrCreate(
            ['code' => 'STR'],
            ['name' => 'Strength']
        );

        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertFalse($result->met);
        $this->assertCount(1, $result->unmet);
        $this->assertEquals('AbilityScore', $result->unmet[0]['type']);
        $this->assertEquals('Strength 13', $result->unmet[0]['requirement']);
        $this->assertEquals(10, $result->unmet[0]['current']);
    }

    #[Test]
    public function it_passes_when_proficiency_type_prerequisite_met(): void
    {
        $lightArmor = ProficiencyType::firstOrCreate(
            ['name' => 'Light Armor'],
            ['slug' => 'light-armor', 'category' => 'armor']
        );

        // Create a class with light armor proficiency
        $class = CharacterClass::factory()->create();
        $class->proficiencies()->create([
            'proficiency_type' => 'armor',
            'proficiency_name' => 'Light Armor',
        ]);

        $character = Character::factory()->withClass($class)->create();

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => ProficiencyType::class,
            'prerequisite_id' => $lightArmor->id,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertTrue($result->met);
    }

    #[Test]
    public function it_fails_when_proficiency_type_prerequisite_not_met(): void
    {
        $mediumArmor = ProficiencyType::firstOrCreate(
            ['name' => 'Medium Armor'],
            ['slug' => 'medium-armor', 'category' => 'armor']
        );

        // Create a class without medium armor proficiency
        $class = CharacterClass::factory()->create();
        $character = Character::factory()->withClass($class)->create();

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => ProficiencyType::class,
            'prerequisite_id' => $mediumArmor->id,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertFalse($result->met);
        $this->assertCount(1, $result->unmet);
        $this->assertEquals('ProficiencyType', $result->unmet[0]['type']);
        $this->assertStringContainsString('Medium Armor', $result->unmet[0]['requirement']);
    }

    #[Test]
    public function it_passes_when_race_prerequisite_met(): void
    {
        $dragonborn = Race::factory()->create(['name' => 'Dragonborn', 'slug' => 'dragonborn']);
        $character = Character::factory()->create([
            'race_slug' => $dragonborn->slug,
        ]);

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $dragonborn->id,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertTrue($result->met);
    }

    #[Test]
    public function it_fails_when_race_prerequisite_not_met(): void
    {
        $dragonborn = Race::factory()->create(['name' => 'Dragonborn', 'slug' => 'dragonborn']);
        $human = Race::factory()->create(['name' => 'Human', 'slug' => 'human']);

        $character = Character::factory()->create([
            'race_slug' => $human->slug,
        ]);

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $dragonborn->id,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertFalse($result->met);
        $this->assertCount(1, $result->unmet);
        $this->assertEquals('Race', $result->unmet[0]['type']);
        $this->assertEquals('Dragonborn', $result->unmet[0]['requirement']);
        $this->assertEquals('Human', $result->unmet[0]['current']);
    }

    #[Test]
    public function it_passes_when_skill_proficiency_prerequisite_met(): void
    {
        $acrobatics = Skill::firstOrCreate(
            ['name' => 'Acrobatics'],
            ['slug' => 'acrobatics', 'ability_score_id' => 1]
        );

        $character = Character::factory()->create();
        $character->proficiencies()->create([
            'skill_slug' => $acrobatics->slug,
            'source' => 'background',
        ]);

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Skill::class,
            'prerequisite_id' => $acrobatics->id,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertTrue($result->met);
    }

    #[Test]
    public function it_fails_when_skill_proficiency_prerequisite_not_met(): void
    {
        $acrobatics = Skill::firstOrCreate(
            ['name' => 'Acrobatics'],
            ['slug' => 'acrobatics', 'ability_score_id' => 1]
        );

        $character = Character::factory()->create();
        // No proficiency in Acrobatics

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Skill::class,
            'prerequisite_id' => $acrobatics->id,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertFalse($result->met);
        $this->assertCount(1, $result->unmet);
        $this->assertEquals('Skill', $result->unmet[0]['type']);
        $this->assertStringContainsString('Acrobatics', $result->unmet[0]['requirement']);
    }

    #[Test]
    public function it_returns_all_unmet_prerequisites(): void
    {
        $character = Character::factory()->create([
            'strength' => 10,
            'dexterity' => 10,
        ]);

        $feat = Feat::factory()->create();
        $strength = AbilityScore::firstOrCreate(['code' => 'STR'], ['name' => 'Strength']);
        $dexterity = AbilityScore::firstOrCreate(['code' => 'DEX'], ['name' => 'Dexterity']);

        // Both STR 13 AND DEX 13 required (different group_ids = AND logic)
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
            'group_id' => 1, // Different group = AND
        ]);
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $dexterity->id,
            'minimum_value' => 13,
            'group_id' => 2, // Different group = AND
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertFalse($result->met);
        $this->assertCount(2, $result->unmet);
    }

    #[Test]
    public function it_skips_null_type_prerequisites(): void
    {
        // Null-type prerequisites like "ability to cast spells" can't be validated programmatically
        $character = Character::factory()->create();
        $feat = Feat::factory()->create();

        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => null,
            'prerequisite_id' => null,
            'description' => 'The ability to cast at least one spell',
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        // Should pass with a warning
        $this->assertTrue($result->met);
        $this->assertNotEmpty($result->warnings);
        $this->assertStringContainsString('cast at least one spell', $result->warnings[0]);
    }

    #[Test]
    public function it_handles_feats_with_multiple_prerequisites(): void
    {
        $strength = AbilityScore::firstOrCreate(['code' => 'STR'], ['name' => 'Strength']);
        $heavyArmor = ProficiencyType::firstOrCreate(
            ['name' => 'Heavy Armor'],
            ['slug' => 'heavy-armor', 'category' => 'armor']
        );

        // Create character meeting one prereq but not the other
        $class = CharacterClass::factory()->create();
        $class->proficiencies()->create([
            'proficiency_type' => 'armor',
            'proficiency_name' => 'Heavy Armor',
        ]);

        $character = Character::factory()->withClass($class)->create([
            'strength' => 10, // Not enough (need 13)
        ]);

        $feat = Feat::factory()->create();

        // Requires STR 13 AND Heavy Armor proficiency (different groups = AND logic)
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => ProficiencyType::class,
            'prerequisite_id' => $heavyArmor->id,
            'group_id' => 2,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertFalse($result->met);
        $this->assertCount(1, $result->unmet); // Only STR fails
        $this->assertEquals('AbilityScore', $result->unmet[0]['type']);
    }

    #[Test]
    public function it_fails_when_ability_score_is_null(): void
    {
        // Character with no strength score set
        $character = Character::factory()->create([
            'strength' => null,
        ]);

        $feat = Feat::factory()->create();
        $strength = AbilityScore::firstOrCreate(['code' => 'STR'], ['name' => 'Strength']);

        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertFalse($result->met);
        $this->assertCount(1, $result->unmet);
        $this->assertEquals('AbilityScore', $result->unmet[0]['type']);
        $this->assertNull($result->unmet[0]['current']);
    }

    #[Test]
    public function it_checks_proficiency_from_race(): void
    {
        $weaponProficiency = ProficiencyType::firstOrCreate(
            ['name' => 'Longsword'],
            ['slug' => 'longsword', 'category' => 'weapon']
        );

        // Create a race with longsword proficiency (like Elf)
        $race = Race::factory()->create(['name' => 'Elf', 'slug' => 'elf']);
        $race->proficiencies()->create([
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'Longsword',
        ]);

        $character = Character::factory()->withRace($race)->create();

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => ProficiencyType::class,
            'prerequisite_id' => $weaponProficiency->id,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertTrue($result->met);
    }

    #[Test]
    public function it_checks_proficiency_from_background(): void
    {
        // First create the ProficiencyType record
        $toolProficiency = ProficiencyType::create([
            'slug' => 'calligraphy-tools',
            'name' => 'Calligraphy Tools',
            'category' => 'tool',
        ]);

        // Create a background with matching tool proficiency (exact name match)
        $background = Background::factory()->create(['name' => 'Criminal', 'slug' => 'criminal']);
        $background->proficiencies()->create([
            'proficiency_type' => 'tool',
            'proficiency_name' => 'Calligraphy Tools',  // Exact match with ProficiencyType name
        ]);

        $character = Character::factory()->withBackground($background)->create();

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => ProficiencyType::class,
            'prerequisite_id' => $toolProficiency->id,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertTrue($result->met);
    }

    #[Test]
    public function it_fails_race_prerequisite_when_character_has_no_race(): void
    {
        $dragonborn = Race::factory()->create(['name' => 'Dragonborn', 'slug' => 'dragonborn']);

        // Character with no race
        $character = Character::factory()->create([
            'race_slug' => null,
        ]);

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $dragonborn->id,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertFalse($result->met);
        $this->assertCount(1, $result->unmet);
        $this->assertEquals('Race', $result->unmet[0]['type']);
        $this->assertEquals('Dragonborn', $result->unmet[0]['requirement']);
        $this->assertNull($result->unmet[0]['current']);
    }

    #[Test]
    public function it_checks_proficiency_across_multiple_classes(): void
    {
        $martialWeapons = ProficiencyType::firstOrCreate(
            ['name' => 'Martial Weapons'],
            ['slug' => 'martial-weapons', 'category' => 'weapon']
        );

        // Create character with two classes, martial proficiency from second class
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter']);

        $fighter->proficiencies()->create([
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'Martial Weapons',
        ]);

        $character = Character::factory()
            ->withClass($wizard, 3)
            ->withClass($fighter, 2)
            ->create();

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => ProficiencyType::class,
            'prerequisite_id' => $martialWeapons->id,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertTrue($result->met);
    }

    #[Test]
    public function it_handles_unknown_prerequisite_type(): void
    {
        $character = Character::factory()->create();
        $feat = Feat::factory()->create();

        // Create a prerequisite with an unknown type (simulated by using a valid model class
        // that's not in the match statement)
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Feat::class, // Not a valid prerequisite type
            'prerequisite_id' => 1,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        // Unknown types are treated like null types - they pass with no warning
        // (since there's no description)
        $this->assertTrue($result->met);
        $this->assertEmpty($result->warnings);
    }

    #[Test]
    public function it_skips_null_type_prerequisites_without_description(): void
    {
        $character = Character::factory()->create();
        $feat = Feat::factory()->create();

        // Null prerequisite with no description
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => null,
            'prerequisite_id' => null,
            'description' => null, // No description
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        // Should pass with no warning when there's no description
        $this->assertTrue($result->met);
        $this->assertEmpty($result->warnings);
    }

    #[Test]
    public function it_passes_when_ability_score_equals_minimum(): void
    {
        // Edge case: exactly meeting the requirement
        $character = Character::factory()->create([
            'strength' => 13,
        ]);

        $feat = Feat::factory()->create();
        $strength = AbilityScore::firstOrCreate(['code' => 'STR'], ['name' => 'Strength']);

        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertTrue($result->met);
        $this->assertEmpty($result->unmet);
    }

    #[Test]
    public function it_handles_case_insensitive_proficiency_matching(): void
    {
        $lightArmor = ProficiencyType::firstOrCreate(
            ['name' => 'Light Armor'],
            ['slug' => 'light-armor', 'category' => 'armor']
        );

        // Create a class with different case proficiency name
        $class = CharacterClass::factory()->create();
        $class->proficiencies()->create([
            'proficiency_type' => 'armor',
            'proficiency_name' => 'LIGHT ARMOR', // Different case
        ]);

        $character = Character::factory()->withClass($class)->create();

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => ProficiencyType::class,
            'prerequisite_id' => $lightArmor->id,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertTrue($result->met);
    }

    #[Test]
    public function it_passes_when_subrace_qualifies_for_parent_race_prerequisite(): void
    {
        // Create parent race (Elf)
        $elf = Race::factory()->create(['name' => 'Elf', 'slug' => 'elf']);

        // Create subrace (High Elf) with parent_race_id pointing to Elf
        $highElf = Race::factory()->create([
            'name' => 'High Elf',
            'slug' => 'high-elf',
            'parent_race_id' => $elf->id,
        ]);

        // Character is a High Elf
        $character = Character::factory()->create([
            'race_slug' => $highElf->slug,
        ]);

        // Feat requires "Elf" race (the parent)
        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $elf->id,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        // High Elf should qualify for Elf feats
        $this->assertTrue($result->met, 'Subrace should qualify for parent race prerequisites');
        $this->assertEmpty($result->unmet);
    }

    #[Test]
    public function it_passes_when_character_class_prerequisite_met(): void
    {
        // Create a Warlock class
        $warlock = CharacterClass::factory()->create(['name' => 'Warlock', 'slug' => 'warlock']);

        // Create character with that class
        $character = Character::factory()->withClass($warlock, 5)->create();

        // Create feat requiring Warlock (like Eldritch Adept)
        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => CharacterClass::class,
            'prerequisite_id' => $warlock->id,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertTrue($result->met);
    }

    #[Test]
    public function it_fails_when_character_class_prerequisite_not_met(): void
    {
        // Create two classes
        $warlock = CharacterClass::factory()->create(['name' => 'Warlock', 'slug' => 'warlock']);
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter']);

        // Character is a Fighter, not a Warlock
        $character = Character::factory()->withClass($fighter, 5)->create();

        // Feat requires Warlock (like Eldritch Adept)
        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => CharacterClass::class,
            'prerequisite_id' => $warlock->id,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertFalse($result->met);
        $this->assertCount(1, $result->unmet);
        $this->assertEquals('CharacterClass', $result->unmet[0]['type']);
        $this->assertEquals('Warlock', $result->unmet[0]['requirement']);
    }

    #[Test]
    public function it_fails_class_prerequisite_when_character_has_no_class(): void
    {
        $warlock = CharacterClass::factory()->create(['name' => 'Warlock', 'slug' => 'warlock']);

        // Character with no class
        $character = Character::factory()->create();

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => CharacterClass::class,
            'prerequisite_id' => $warlock->id,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertFalse($result->met);
        $this->assertCount(1, $result->unmet);
        $this->assertEquals('CharacterClass', $result->unmet[0]['type']);
    }

    #[Test]
    public function it_passes_when_one_of_multiple_or_race_prerequisites_met(): void
    {
        // Prodigy feat scenario: requires Half-Elf OR Half-Orc OR Human
        $halfElf = Race::factory()->create(['name' => 'Half-Elf', 'slug' => 'half-elf']);
        $halfOrc = Race::factory()->create(['name' => 'Half-Orc', 'slug' => 'half-orc']);
        $human = Race::factory()->create(['name' => 'Human', 'slug' => 'human']);

        // Character is Human
        $character = Character::factory()->create([
            'race_slug' => $human->slug,
        ]);

        // Feat has 3 race prerequisites with SAME group_id (OR logic)
        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $halfElf->id,
            'group_id' => 1, // Same group = OR
        ]);
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $halfOrc->id,
            'group_id' => 1, // Same group = OR
        ]);
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $human->id,
            'group_id' => 1, // Same group = OR
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        // Human satisfies ONE of the OR prerequisites
        $this->assertTrue($result->met, 'Human should satisfy one of the OR race prerequisites');
        $this->assertEmpty($result->unmet);
    }

    #[Test]
    public function it_fails_when_none_of_or_race_prerequisites_met(): void
    {
        // Prodigy feat scenario: requires Half-Elf OR Half-Orc OR Human
        $halfElf = Race::factory()->create(['name' => 'Half-Elf', 'slug' => 'half-elf']);
        $halfOrc = Race::factory()->create(['name' => 'Half-Orc', 'slug' => 'half-orc']);
        $human = Race::factory()->create(['name' => 'Human', 'slug' => 'human']);
        $elf = Race::factory()->create(['name' => 'Elf', 'slug' => 'elf']);

        // Character is Elf (not in the OR group)
        $character = Character::factory()->create([
            'race_slug' => $elf->slug,
        ]);

        // Feat has 3 race prerequisites with SAME group_id (OR logic)
        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $halfElf->id,
            'group_id' => 1,
        ]);
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $halfOrc->id,
            'group_id' => 1,
        ]);
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $human->id,
            'group_id' => 1,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        // Elf does not satisfy any of the OR prerequisites
        $this->assertFalse($result->met);
        // Should show ONE unmet for the entire group, not three individual failures
        $this->assertNotEmpty($result->unmet);
    }

    #[Test]
    public function it_passes_when_subrace_meets_or_race_prerequisite(): void
    {
        // Variant Human should satisfy Human requirement in OR group
        $human = Race::factory()->create(['name' => 'Human', 'slug' => 'human']);
        $variantHuman = Race::factory()->create([
            'name' => 'Variant Human',
            'slug' => 'human-variant',
            'parent_race_id' => $human->id,
        ]);
        $halfElf = Race::factory()->create(['name' => 'Half-Elf', 'slug' => 'half-elf']);
        $halfOrc = Race::factory()->create(['name' => 'Half-Orc', 'slug' => 'half-orc']);

        // Character is Variant Human
        $character = Character::factory()->create([
            'race_slug' => $variantHuman->slug,
        ]);

        // Feat requires Half-Elf OR Half-Orc OR Human (same group_id)
        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $halfElf->id,
            'group_id' => 1,
        ]);
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $halfOrc->id,
            'group_id' => 1,
        ]);
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $human->id,
            'group_id' => 1,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        // Variant Human qualifies for Human requirement via parent race
        $this->assertTrue($result->met, 'Variant Human should satisfy Human in OR group via parent race');
        $this->assertEmpty($result->unmet);
    }
}
