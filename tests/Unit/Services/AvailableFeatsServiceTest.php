<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterFeature;
use App\Models\EntityPrerequisite;
use App\Models\Feat;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Models\Skill;
use App\Services\AvailableFeatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AvailableFeatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private AvailableFeatsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AvailableFeatsService;
    }

    // =========================================================================
    // Feats with No Prerequisites
    // =========================================================================

    #[Test]
    public function it_returns_feats_without_prerequisites(): void
    {
        $character = Character::factory()->create();
        $feat = Feat::factory()->create();

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(1, $availableFeats);
        $this->assertEquals($feat->id, $availableFeats->first()->id);
    }

    #[Test]
    public function it_returns_all_feats_without_prerequisites_when_character_has_no_qualifications(): void
    {
        $character = Character::factory()->create([
            'strength' => null,
            'race_slug' => null,
        ]);

        Feat::factory()->count(3)->create();

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(3, $availableFeats);
    }

    // =========================================================================
    // Ability Score Prerequisites
    // =========================================================================

    #[Test]
    public function it_returns_feat_when_character_meets_ability_score_prerequisite(): void
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

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(1, $availableFeats);
        $this->assertEquals($feat->id, $availableFeats->first()->id);
    }

    #[Test]
    public function it_does_not_return_feat_when_character_does_not_meet_ability_score_prerequisite(): void
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

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(0, $availableFeats);
    }

    #[Test]
    public function it_returns_feat_when_character_exactly_meets_minimum_ability_score(): void
    {
        $character = Character::factory()->create([
            'strength' => 13,
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

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(1, $availableFeats);
    }

    #[Test]
    public function it_does_not_return_feat_when_character_ability_score_is_null(): void
    {
        $character = Character::factory()->create([
            'strength' => null,
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

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(0, $availableFeats);
    }

    #[Test]
    public function it_handles_prerequisite_with_null_minimum_value_as_zero(): void
    {
        $character = Character::factory()->create([
            'strength' => 5,
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
            'minimum_value' => null,
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(1, $availableFeats);
    }

    // =========================================================================
    // Race Prerequisites
    // =========================================================================

    #[Test]
    public function it_returns_feat_when_character_race_matches_prerequisite(): void
    {
        $dragonborn = Race::factory()->create(['name' => 'Dragonborn', 'slug' => 'test:dragonborn']);
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

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(1, $availableFeats);
        $this->assertEquals($feat->id, $availableFeats->first()->id);
    }

    #[Test]
    public function it_does_not_return_feat_when_character_race_does_not_match_prerequisite(): void
    {
        $dragonborn = Race::factory()->create(['name' => 'Dragonborn', 'slug' => 'test:dragonborn']);
        $human = Race::factory()->create(['name' => 'Human', 'slug' => 'test:human']);

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

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(0, $availableFeats);
    }

    #[Test]
    public function it_does_not_return_feat_when_character_has_no_race(): void
    {
        $dragonborn = Race::factory()->create(['name' => 'Dragonborn', 'slug' => 'test:dragonborn']);

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

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(0, $availableFeats);
    }

    #[Test]
    public function it_returns_feat_for_subrace_when_prerequisite_is_parent_race(): void
    {
        $elf = Race::factory()->create(['name' => 'Elf', 'slug' => 'test:elf', 'parent_race_id' => null]);
        $highElf = Race::factory()->create([
            'name' => 'High Elf',
            'slug' => 'test:high-elf',
            'parent_race_id' => $elf->id,
        ]);

        $character = Character::factory()->create([
            'race_slug' => $highElf->slug,
        ]);

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $elf->id, // Parent race as prerequisite
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(1, $availableFeats);
        $this->assertEquals($feat->id, $availableFeats->first()->id);
    }

    #[Test]
    public function it_does_not_return_feat_for_parent_race_when_prerequisite_is_specific_subrace(): void
    {
        $elf = Race::factory()->create(['name' => 'Elf', 'slug' => 'test:elf', 'parent_race_id' => null]);
        $highElf = Race::factory()->create([
            'name' => 'High Elf',
            'slug' => 'test:high-elf',
            'parent_race_id' => $elf->id,
        ]);

        $character = Character::factory()->create([
            'race_slug' => $elf->slug, // Parent race
        ]);

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $highElf->id, // Specific subrace required
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(0, $availableFeats);
    }

    #[Test]
    public function it_returns_feat_for_any_subrace_when_prerequisite_is_parent_race(): void
    {
        // Multiple subraces of Elf should all qualify for "Elf" prerequisite
        $elf = Race::factory()->create(['name' => 'Elf', 'slug' => 'test:elf', 'parent_race_id' => null]);
        $highElf = Race::factory()->create([
            'name' => 'High Elf',
            'slug' => 'test:high-elf',
            'parent_race_id' => $elf->id,
        ]);
        $woodElf = Race::factory()->create([
            'name' => 'Wood Elf',
            'slug' => 'test:wood-elf',
            'parent_race_id' => $elf->id,
        ]);

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $elf->id, // Parent race as prerequisite
        ]);

        // High Elf character qualifies
        $highElfCharacter = Character::factory()->create([
            'race_slug' => $highElf->slug,
        ]);
        $availableForHighElf = $this->service->getAvailableFeats($highElfCharacter);
        $this->assertCount(1, $availableForHighElf);
        $this->assertEquals($feat->id, $availableForHighElf->first()->id);

        // Wood Elf character also qualifies
        $woodElfCharacter = Character::factory()->create([
            'race_slug' => $woodElf->slug,
        ]);
        $availableForWoodElf = $this->service->getAvailableFeats($woodElfCharacter);
        $this->assertCount(1, $availableForWoodElf);
        $this->assertEquals($feat->id, $availableForWoodElf->first()->id);
    }

    // =========================================================================
    // Proficiency Type Prerequisites
    // =========================================================================

    #[Test]
    public function it_returns_feat_when_character_has_required_proficiency_type(): void
    {
        $lightArmor = ProficiencyType::firstOrCreate(
            ['name' => 'Light Armor'],
            ['slug' => 'light-armor', 'category' => 'armor']
        );

        $class = CharacterClass::factory()->create();
        $character = Character::factory()->withClass($class)->create();

        // Add proficiency to character directly
        $character->proficiencies()->create([
            'proficiency_type_slug' => $lightArmor->slug,
            'source' => 'class',
        ]);

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => ProficiencyType::class,
            'prerequisite_id' => $lightArmor->id,
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(1, $availableFeats);
        $this->assertEquals($feat->id, $availableFeats->first()->id);
    }

    #[Test]
    public function it_does_not_return_feat_when_character_lacks_required_proficiency_type(): void
    {
        $mediumArmor = ProficiencyType::firstOrCreate(
            ['name' => 'Medium Armor'],
            ['slug' => 'medium-armor', 'category' => 'armor']
        );

        $class = CharacterClass::factory()->create();
        $character = Character::factory()->withClass($class)->create();

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => ProficiencyType::class,
            'prerequisite_id' => $mediumArmor->id,
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(0, $availableFeats);
    }

    // =========================================================================
    // Skill Prerequisites
    // =========================================================================

    #[Test]
    public function it_returns_feat_when_character_has_required_skill_proficiency(): void
    {
        // Create or get ability score and skill (handle existing data from seeding)
        $dexterity = AbilityScore::firstOrCreate(['code' => 'DEX'], ['name' => 'Dexterity']);
        $acrobatics = Skill::firstOrCreate(
            ['name' => 'Acrobatics'],
            ['slug' => 'acrobatics', 'ability_score_id' => $dexterity->id]
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

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(1, $availableFeats);
        $this->assertEquals($feat->id, $availableFeats->first()->id);
    }

    #[Test]
    public function it_does_not_return_feat_when_character_lacks_required_skill_proficiency(): void
    {
        // Create or get ability score and skill (handle existing data from seeding)
        $dexterity = AbilityScore::firstOrCreate(['code' => 'DEX'], ['name' => 'Dexterity']);
        $acrobatics = Skill::firstOrCreate(
            ['name' => 'Acrobatics'],
            ['slug' => 'acrobatics', 'ability_score_id' => $dexterity->id]
        );

        $character = Character::factory()->create();

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Skill::class,
            'prerequisite_id' => $acrobatics->id,
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(0, $availableFeats);
    }

    // =========================================================================
    // Class Prerequisites
    // =========================================================================

    #[Test]
    public function it_returns_feat_when_character_has_required_class(): void
    {
        $warlock = CharacterClass::factory()->create(['name' => 'Warlock', 'slug' => 'test:warlock']);

        $character = Character::factory()->withClass($warlock)->create();

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => CharacterClass::class,
            'prerequisite_id' => $warlock->id,
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(1, $availableFeats);
        $this->assertEquals($feat->id, $availableFeats->first()->id);
    }

    #[Test]
    public function it_does_not_return_feat_when_character_lacks_required_class(): void
    {
        $warlock = CharacterClass::factory()->create(['name' => 'Warlock', 'slug' => 'test:warlock']);
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'test:fighter']);

        $character = Character::factory()->withClass($fighter)->create();

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => CharacterClass::class,
            'prerequisite_id' => $warlock->id,
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(0, $availableFeats);
    }

    #[Test]
    public function it_returns_feat_for_multiclass_character_when_any_class_matches(): void
    {
        $warlock = CharacterClass::factory()->create(['name' => 'Warlock', 'slug' => 'test:warlock']);
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'test:fighter']);

        $character = Character::factory()
            ->withClass($fighter, 3)
            ->withClass($warlock, 2)
            ->create();

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => CharacterClass::class,
            'prerequisite_id' => $warlock->id,
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(1, $availableFeats);
        $this->assertEquals($feat->id, $availableFeats->first()->id);
    }

    // =========================================================================
    // OR Group Logic (same group_id)
    // =========================================================================

    #[Test]
    public function it_returns_feat_when_any_prerequisite_in_or_group_is_met(): void
    {
        $character = Character::factory()->create([
            'strength' => 15,
            'dexterity' => 10,
        ]);

        $feat = Feat::factory()->create();
        $strength = AbilityScore::firstOrCreate(['code' => 'STR'], ['name' => 'Strength']);
        $dexterity = AbilityScore::firstOrCreate(['code' => 'DEX'], ['name' => 'Dexterity']);

        // Same group_id = OR logic
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
            'group_id' => 1, // Same group
        ]);
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $dexterity->id,
            'minimum_value' => 13,
            'group_id' => 1, // Same group
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        // Should qualify because STR 15 >= 13 (even though DEX 10 < 13)
        $this->assertCount(1, $availableFeats);
        $this->assertEquals($feat->id, $availableFeats->first()->id);
    }

    #[Test]
    public function it_does_not_return_feat_when_no_prerequisite_in_or_group_is_met(): void
    {
        $character = Character::factory()->create([
            'strength' => 10,
            'dexterity' => 10,
        ]);

        $feat = Feat::factory()->create();
        $strength = AbilityScore::firstOrCreate(['code' => 'STR'], ['name' => 'Strength']);
        $dexterity = AbilityScore::firstOrCreate(['code' => 'DEX'], ['name' => 'Dexterity']);

        // Same group_id = OR logic
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
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $dexterity->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        // Neither STR nor DEX meets the requirement
        $this->assertCount(0, $availableFeats);
    }

    #[Test]
    public function it_returns_feat_when_or_group_has_mixed_prerequisite_types_and_one_is_met(): void
    {
        // Scenario: STR 13 OR Athletics proficiency (same group_id = OR)
        // Character has low STR but has Athletics proficiency
        $strength = AbilityScore::firstOrCreate(['code' => 'STR'], ['name' => 'Strength']);
        $athletics = Skill::firstOrCreate(
            ['name' => 'Athletics'],
            ['slug' => 'athletics', 'ability_score_id' => $strength->id]
        );

        $character = Character::factory()->create([
            'strength' => 10, // Does not meet STR requirement
        ]);
        $character->proficiencies()->create([
            'skill_slug' => $athletics->slug,
            'source' => 'background',
        ]);

        $feat = Feat::factory()->create();

        // Same group_id = OR logic with mixed types
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
            'prerequisite_type' => Skill::class,
            'prerequisite_id' => $athletics->id,
            'group_id' => 1, // Same group as ability score
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        // Should qualify: Athletics proficiency satisfies the OR group
        $this->assertCount(1, $availableFeats);
        $this->assertEquals($feat->id, $availableFeats->first()->id);
    }

    // =========================================================================
    // AND Logic Between Groups
    // =========================================================================

    #[Test]
    public function it_returns_feat_when_all_prerequisite_groups_are_satisfied(): void
    {
        $character = Character::factory()->create([
            'strength' => 15,
            'dexterity' => 15,
        ]);

        $feat = Feat::factory()->create();
        $strength = AbilityScore::firstOrCreate(['code' => 'STR'], ['name' => 'Strength']);
        $dexterity = AbilityScore::firstOrCreate(['code' => 'DEX'], ['name' => 'Dexterity']);

        // Different group_ids = AND logic
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
            'group_id' => 1, // Group 1
        ]);
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $dexterity->id,
            'minimum_value' => 13,
            'group_id' => 2, // Group 2
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        // Both groups satisfied
        $this->assertCount(1, $availableFeats);
        $this->assertEquals($feat->id, $availableFeats->first()->id);
    }

    #[Test]
    public function it_does_not_return_feat_when_any_prerequisite_group_is_not_satisfied(): void
    {
        $character = Character::factory()->create([
            'strength' => 15,
            'dexterity' => 10,
        ]);

        $feat = Feat::factory()->create();
        $strength = AbilityScore::firstOrCreate(['code' => 'STR'], ['name' => 'Strength']);
        $dexterity = AbilityScore::firstOrCreate(['code' => 'DEX'], ['name' => 'Dexterity']);

        // Different group_ids = AND logic
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
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $dexterity->id,
            'minimum_value' => 13,
            'group_id' => 2,
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        // Group 2 (DEX) not satisfied
        $this->assertCount(0, $availableFeats);
    }

    #[Test]
    public function it_handles_complex_mixed_or_and_and_prerequisites(): void
    {
        // Scenario: (STR 13 OR DEX 13) AND (Elf race)
        $elf = Race::factory()->create(['name' => 'Elf', 'slug' => 'test:elf']);
        $character = Character::factory()->create([
            'strength' => 10,
            'dexterity' => 15, // Meets DEX requirement
            'race_slug' => $elf->slug, // Meets race requirement
        ]);

        $feat = Feat::factory()->create();
        $strength = AbilityScore::firstOrCreate(['code' => 'STR'], ['name' => 'Strength']);
        $dexterity = AbilityScore::firstOrCreate(['code' => 'DEX'], ['name' => 'Dexterity']);

        // Group 1: STR OR DEX
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
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $dexterity->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);

        // Group 2: Elf race
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $elf->id,
            'group_id' => 2,
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        // Should qualify: DEX satisfies group 1, Elf satisfies group 2
        $this->assertCount(1, $availableFeats);
        $this->assertEquals($feat->id, $availableFeats->first()->id);
    }

    // =========================================================================
    // Race Source Exclusion
    // =========================================================================

    #[Test]
    public function it_excludes_feats_with_ability_score_prerequisites_when_source_is_race(): void
    {
        $character = Character::factory()->create([
            'strength' => 15, // Would normally meet the prerequisite
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

        $availableFeats = $this->service->getAvailableFeats($character, 'race');

        // Feat with ability score prerequisite excluded for race source
        $this->assertCount(0, $availableFeats);
    }

    #[Test]
    public function it_includes_feats_without_ability_score_prerequisites_when_source_is_race(): void
    {
        $elf = Race::factory()->create(['name' => 'Elf', 'slug' => 'test:elf']);
        $character = Character::factory()->create([
            'race_slug' => $elf->slug,
        ]);

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $elf->id,
        ]);

        $availableFeats = $this->service->getAvailableFeats($character, 'race');

        // Race prerequisite feats are allowed for race source
        $this->assertCount(1, $availableFeats);
    }

    #[Test]
    public function it_includes_feats_with_no_prerequisites_when_source_is_race(): void
    {
        $character = Character::factory()->create();
        Feat::factory()->create();

        $availableFeats = $this->service->getAvailableFeats($character, 'race');

        $this->assertCount(1, $availableFeats);
    }

    #[Test]
    public function it_includes_feats_with_ability_score_prerequisites_when_source_is_asi(): void
    {
        $character = Character::factory()->create([
            'strength' => 15,
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

        $availableFeats = $this->service->getAvailableFeats($character, 'asi');

        // ASI source allows ability score prerequisites
        $this->assertCount(1, $availableFeats);
    }

    #[Test]
    public function it_includes_feats_with_ability_score_prerequisites_when_source_is_null(): void
    {
        $character = Character::factory()->create([
            'strength' => 15,
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

        $availableFeats = $this->service->getAvailableFeats($character, null);

        // No source restriction allows ability score prerequisites
        $this->assertCount(1, $availableFeats);
    }

    // =========================================================================
    // Edge Cases and Unknown Types
    // =========================================================================

    #[Test]
    public function it_returns_false_for_unknown_prerequisite_types(): void
    {
        $character = Character::factory()->create();
        $feat = Feat::factory()->create();

        // Create prerequisite with an unknown type
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Feat::class, // Not a valid prerequisite type
            'prerequisite_id' => 1,
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        // Unknown types default to false
        $this->assertCount(0, $availableFeats);
    }

    #[Test]
    public function it_handles_multiple_feats_with_mixed_eligibility(): void
    {
        $character = Character::factory()->create([
            'strength' => 15,
            'dexterity' => 10,
        ]);

        // Feat 1: No prerequisites (available)
        $feat1 = Feat::factory()->create();

        // Feat 2: STR 13 (available, character has 15)
        $feat2 = Feat::factory()->create();
        $strength = AbilityScore::firstOrCreate(['code' => 'STR'], ['name' => 'Strength']);
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat2->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
        ]);

        // Feat 3: DEX 13 (not available, character has 10)
        $feat3 = Feat::factory()->create();
        $dexterity = AbilityScore::firstOrCreate(['code' => 'DEX'], ['name' => 'Dexterity']);
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat3->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $dexterity->id,
            'minimum_value' => 13,
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(2, $availableFeats);
        $this->assertContains($feat1->id, $availableFeats->pluck('id')->toArray());
        $this->assertContains($feat2->id, $availableFeats->pluck('id')->toArray());
        $this->assertNotContains($feat3->id, $availableFeats->pluck('id')->toArray());
    }

    #[Test]
    public function it_loads_required_relationships_on_character(): void
    {
        $elf = Race::factory()->create(['name' => 'Elf', 'slug' => 'test:elf']);
        $character = Character::factory()->create([
            'race_slug' => $elf->slug,
        ]);

        // Ensure relationships are not loaded initially
        $this->assertFalse($character->relationLoaded('race'));
        $this->assertFalse($character->relationLoaded('proficiencies'));

        $feat = Feat::factory()->create();
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $elf->id,
        ]);

        $this->service->getAvailableFeats($character);

        // After service call, relationships should be loaded
        $this->assertTrue($character->relationLoaded('race'));
        $this->assertTrue($character->relationLoaded('proficiencies'));
    }

    #[Test]
    public function it_loads_required_relationships_on_feats(): void
    {
        $character = Character::factory()->create();

        Feat::factory()->create();

        $availableFeats = $this->service->getAvailableFeats($character);

        // Verify feat has its relationships loaded
        $returnedFeat = $availableFeats->first();
        $this->assertTrue($returnedFeat->relationLoaded('prerequisites'));
        $this->assertTrue($returnedFeat->relationLoaded('sources'));
        $this->assertTrue($returnedFeat->relationLoaded('modifiers'));
        $this->assertTrue($returnedFeat->relationLoaded('proficiencies'));
        $this->assertTrue($returnedFeat->relationLoaded('spells'));
    }

    // =========================================================================
    // Already-Selected Feat Exclusion
    // =========================================================================

    #[Test]
    public function it_excludes_already_selected_feats_by_default(): void
    {
        $character = Character::factory()->create();

        $feat1 = Feat::factory()->create(['slug' => 'test:feat-1']);
        $feat2 = Feat::factory()->create(['slug' => 'test:feat-2']);
        $feat3 = Feat::factory()->create(['slug' => 'test:feat-3']);

        // Character has already selected feat2
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat2->id,
            'feature_slug' => $feat2->slug,
            'source' => 'race',
            'level_acquired' => 1,
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        // feat2 should be excluded
        $this->assertCount(2, $availableFeats);
        $slugs = $availableFeats->pluck('slug')->toArray();
        $this->assertContains('test:feat-1', $slugs);
        $this->assertContains('test:feat-3', $slugs);
        $this->assertNotContains('test:feat-2', $slugs);
    }

    #[Test]
    public function it_excludes_feats_selected_from_any_source(): void
    {
        $character = Character::factory()->create();

        $feat1 = Feat::factory()->create(['slug' => 'test:feat-from-race']);
        $feat2 = Feat::factory()->create(['slug' => 'test:feat-from-asi']);
        $feat3 = Feat::factory()->create(['slug' => 'test:feat-available']);

        // Feats selected from different sources
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat1->id,
            'feature_slug' => $feat1->slug,
            'source' => 'race',
            'level_acquired' => 1,
        ]);

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat2->id,
            'feature_slug' => $feat2->slug,
            'source' => 'class', // ASI from class
            'level_acquired' => 4,
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        // Both selected feats excluded, regardless of source
        $this->assertCount(1, $availableFeats);
        $this->assertEquals('test:feat-available', $availableFeats->first()->slug);
    }

    #[Test]
    public function it_includes_already_selected_feats_when_include_selected_is_true(): void
    {
        $character = Character::factory()->create();

        $feat1 = Feat::factory()->create(['slug' => 'test:feat-1']);
        $feat2 = Feat::factory()->create(['slug' => 'test:feat-2']);

        // Character has already selected feat1
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat1->id,
            'feature_slug' => $feat1->slug,
            'source' => 'race',
            'level_acquired' => 1,
        ]);

        $availableFeats = $this->service->getAvailableFeats($character, null, includeSelected: true);

        // All feats returned when include_selected is true
        $this->assertCount(2, $availableFeats);
    }

    #[Test]
    public function it_excludes_selected_feats_with_race_source_filter(): void
    {
        $character = Character::factory()->create();

        $feat1 = Feat::factory()->create(['slug' => 'test:feat-1']);
        $feat2 = Feat::factory()->create(['slug' => 'test:feat-2']);

        // Character has selected feat1
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat1->id,
            'feature_slug' => $feat1->slug,
            'source' => 'race',
            'level_acquired' => 1,
        ]);

        $availableFeats = $this->service->getAvailableFeats($character, 'race');

        // Selected feat excluded even with source filter
        $this->assertCount(1, $availableFeats);
        $this->assertEquals('test:feat-2', $availableFeats->first()->slug);
    }

    #[Test]
    public function it_excludes_selected_feats_with_asi_source_filter(): void
    {
        $character = Character::factory()->create([
            'strength' => 15,
        ]);

        $feat1 = Feat::factory()->create(['slug' => 'test:feat-1']);
        $feat2 = Feat::factory()->create(['slug' => 'test:feat-2']);

        // Character has selected feat1 from previous ASI
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat1->id,
            'feature_slug' => $feat1->slug,
            'source' => 'class',
            'level_acquired' => 4,
        ]);

        $availableFeats = $this->service->getAvailableFeats($character, 'asi');

        // Selected feat excluded with ASI source filter
        $this->assertCount(1, $availableFeats);
        $this->assertEquals('test:feat-2', $availableFeats->first()->slug);
    }

    #[Test]
    public function it_returns_empty_when_all_feats_already_selected(): void
    {
        $character = Character::factory()->create();

        $feat1 = Feat::factory()->create(['slug' => 'test:feat-1']);
        $feat2 = Feat::factory()->create(['slug' => 'test:feat-2']);

        // Character has selected all feats
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat1->id,
            'feature_slug' => $feat1->slug,
            'source' => 'race',
            'level_acquired' => 1,
        ]);

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat2->id,
            'feature_slug' => $feat2->slug,
            'source' => 'class',
            'level_acquired' => 4,
        ]);

        $availableFeats = $this->service->getAvailableFeats($character);

        $this->assertCount(0, $availableFeats);
    }

    #[Test]
    public function it_only_excludes_feats_from_same_character(): void
    {
        $character1 = Character::factory()->create();
        $character2 = Character::factory()->create();

        $feat = Feat::factory()->create(['slug' => 'test:shared-feat']);

        // Character 2 has selected the feat, but character 1 hasn't
        CharacterFeature::create([
            'character_id' => $character2->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat->id,
            'feature_slug' => $feat->slug,
            'source' => 'race',
            'level_acquired' => 1,
        ]);

        $availableFeatsForChar1 = $this->service->getAvailableFeats($character1);

        // Character 1 should still see the feat
        $this->assertCount(1, $availableFeatsForChar1);
        $this->assertEquals('test:shared-feat', $availableFeatsForChar1->first()->slug);
    }
}
