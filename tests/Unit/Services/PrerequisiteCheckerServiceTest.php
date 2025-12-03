<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\AbilityScore;
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

        $character = Character::factory()->create([
            'class_id' => $class->id,
        ]);

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
        $character = Character::factory()->create([
            'class_id' => $class->id,
        ]);

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
            'race_id' => $dragonborn->id,
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
            'race_id' => $human->id,
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
            'skill_id' => $acrobatics->id,
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

        // Both STR 13 and DEX 13 required
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
        ]);
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $dexterity->id,
            'minimum_value' => 13,
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

        $character = Character::factory()->create([
            'strength' => 10, // Not enough (need 13)
            'class_id' => $class->id,
        ]);

        $feat = Feat::factory()->create();

        // Requires STR 13 AND Heavy Armor proficiency
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
        ]);
        EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'prerequisite_type' => ProficiencyType::class,
            'prerequisite_id' => $heavyArmor->id,
        ]);

        $result = $this->service->checkFeatPrerequisites($character, $feat->fresh());

        $this->assertFalse($result->met);
        $this->assertCount(1, $result->unmet); // Only STR fails
        $this->assertEquals('AbilityScore', $result->unmet[0]['type']);
    }
}
