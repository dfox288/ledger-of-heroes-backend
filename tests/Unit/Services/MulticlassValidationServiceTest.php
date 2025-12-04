<?php

namespace Tests\Unit\Services;

use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\Proficiency;
use App\Services\MulticlassValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MulticlassValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private MulticlassValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MulticlassValidationService;
        $this->seedAbilityScores();
    }

    #[Test]
    public function it_passes_when_character_meets_single_ability_requirement(): void
    {
        $character = Character::factory()->create(['charisma' => 13]);
        $bard = $this->createClassWithRequirement('Bard', 'CHA', 13);

        $result = $this->service->canAddClass($character, $bard);

        $this->assertTrue($result->passed);
    }

    #[Test]
    public function it_fails_when_character_does_not_meet_requirement(): void
    {
        $character = Character::factory()->create(['charisma' => 10]);
        $bard = $this->createClassWithRequirement('Bard', 'CHA', 13);

        $result = $this->service->canAddClass($character, $bard);

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('Charisma 13', $result->errors[0]);
    }

    #[Test]
    public function it_passes_with_or_requirements_when_one_is_met(): void
    {
        $character = Character::factory()->create([
            'strength' => 10,
            'dexterity' => 14,
        ]);
        $fighter = $this->createClassWithOrRequirement('Fighter', [
            ['ability' => 'STR', 'minimum' => 13],
            ['ability' => 'DEX', 'minimum' => 13],
        ]);

        $result = $this->service->canAddClass($character, $fighter);

        $this->assertTrue($result->passed);
    }

    #[Test]
    public function it_fails_with_or_requirements_when_none_are_met(): void
    {
        $character = Character::factory()->create([
            'strength' => 10,
            'dexterity' => 10,
        ]);
        $fighter = $this->createClassWithOrRequirement('Fighter', [
            ['ability' => 'STR', 'minimum' => 13],
            ['ability' => 'DEX', 'minimum' => 13],
        ]);

        $result = $this->service->canAddClass($character, $fighter);

        $this->assertFalse($result->passed);
    }

    #[Test]
    public function it_passes_with_and_requirements_when_all_are_met(): void
    {
        $character = Character::factory()->create([
            'strength' => 14,
            'charisma' => 15,
        ]);
        $paladin = $this->createClassWithAndRequirement('Paladin', [
            ['ability' => 'STR', 'minimum' => 13],
            ['ability' => 'CHA', 'minimum' => 13],
        ]);

        $result = $this->service->canAddClass($character, $paladin);

        $this->assertTrue($result->passed);
    }

    #[Test]
    public function it_fails_with_and_requirements_when_one_is_not_met(): void
    {
        $character = Character::factory()->create([
            'strength' => 14,
            'charisma' => 10,
        ]);
        $paladin = $this->createClassWithAndRequirement('Paladin', [
            ['ability' => 'STR', 'minimum' => 13],
            ['ability' => 'CHA', 'minimum' => 13],
        ]);

        $result = $this->service->canAddClass($character, $paladin);

        $this->assertFalse($result->passed);
    }

    #[Test]
    public function it_bypasses_validation_with_force_flag(): void
    {
        $character = Character::factory()->create(['charisma' => 8]);
        $bard = $this->createClassWithRequirement('Bard', 'CHA', 13);

        $result = $this->service->canAddClass($character, $bard, force: true);

        $this->assertTrue($result->passed);
    }

    #[Test]
    public function it_checks_current_class_requirements_for_multiclass(): void
    {
        $fighter = $this->createClassWithOrRequirement('Fighter', [
            ['ability' => 'STR', 'minimum' => 13],
            ['ability' => 'DEX', 'minimum' => 13],
        ]);
        $wizard = $this->createClassWithRequirement('Wizard', 'INT', 13);

        $character = Character::factory()->create([
            'strength' => 10,
            'dexterity' => 14,  // Meets Fighter via DEX
            'intelligence' => 14,  // Meets Wizard
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $result = $this->service->canAddClass($character->fresh(), $wizard);

        $this->assertTrue($result->passed);
    }

    #[Test]
    public function it_fails_if_current_class_requirements_not_met(): void
    {
        $fighter = $this->createClassWithOrRequirement('Fighter', [
            ['ability' => 'STR', 'minimum' => 13],
            ['ability' => 'DEX', 'minimum' => 13],
        ]);
        $wizard = $this->createClassWithRequirement('Wizard', 'INT', 13);

        // Character doesn't meet Fighter's multiclass requirements
        $character = Character::factory()->create([
            'strength' => 10,
            'dexterity' => 10,  // Doesn't meet Fighter
            'intelligence' => 14,  // Meets Wizard
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $result = $this->service->canAddClass($character->fresh(), $wizard);

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('Fighter', $result->errors[0]);
    }

    #[Test]
    public function it_passes_for_class_without_requirements(): void
    {
        $character = Character::factory()->create([
            'strength' => 8,
            'dexterity' => 8,
            'constitution' => 8,
            'intelligence' => 8,
            'wisdom' => 8,
            'charisma' => 8,
        ]);

        // Class with no multiclass requirements
        $classWithNoReqs = CharacterClass::factory()->create([
            'name' => 'NoReqClass',
            'slug' => 'noreqclass',
            'parent_class_id' => null,
        ]);

        $result = $this->service->canAddClass($character, $classWithNoReqs);

        $this->assertTrue($result->passed);
    }

    // Helper methods

    private function seedAbilityScores(): void
    {
        $abilities = [
            ['id' => 1, 'code' => 'STR', 'name' => 'Strength'],
            ['id' => 2, 'code' => 'DEX', 'name' => 'Dexterity'],
            ['id' => 3, 'code' => 'CON', 'name' => 'Constitution'],
            ['id' => 4, 'code' => 'INT', 'name' => 'Intelligence'],
            ['id' => 5, 'code' => 'WIS', 'name' => 'Wisdom'],
            ['id' => 6, 'code' => 'CHA', 'name' => 'Charisma'],
        ];

        foreach ($abilities as $ability) {
            AbilityScore::updateOrCreate(['id' => $ability['id']], $ability);
        }
    }

    private function createClassWithRequirement(string $name, string $abilityCode, int $minimum): CharacterClass
    {
        $class = CharacterClass::factory()->create([
            'name' => $name,
            'slug' => strtolower($name),
            'parent_class_id' => null,
        ]);

        $abilityScore = AbilityScore::where('code', $abilityCode)->first();

        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'multiclass_requirement',
            'proficiency_name' => $abilityScore->name.' '.$minimum,
            'ability_score_id' => $abilityScore->id,
            'quantity' => $minimum,
            'is_choice' => false,
        ]);

        return $class;
    }

    private function createClassWithOrRequirement(string $name, array $requirements): CharacterClass
    {
        $class = CharacterClass::factory()->create([
            'name' => $name,
            'slug' => strtolower($name),
            'parent_class_id' => null,
        ]);

        foreach ($requirements as $req) {
            $abilityScore = AbilityScore::where('code', $req['ability'])->first();

            Proficiency::create([
                'reference_type' => CharacterClass::class,
                'reference_id' => $class->id,
                'proficiency_type' => 'multiclass_requirement',
                'proficiency_name' => $abilityScore->name.' '.$req['minimum'],
                'ability_score_id' => $abilityScore->id,
                'quantity' => $req['minimum'],
                'is_choice' => true,  // OR condition
            ]);
        }

        return $class;
    }

    private function createClassWithAndRequirement(string $name, array $requirements): CharacterClass
    {
        $class = CharacterClass::factory()->create([
            'name' => $name,
            'slug' => strtolower($name),
            'parent_class_id' => null,
        ]);

        foreach ($requirements as $req) {
            $abilityScore = AbilityScore::where('code', $req['ability'])->first();

            Proficiency::create([
                'reference_type' => CharacterClass::class,
                'reference_id' => $class->id,
                'proficiency_type' => 'multiclass_requirement',
                'proficiency_name' => $abilityScore->name.' '.$req['minimum'],
                'ability_score_id' => $abilityScore->id,
                'quantity' => $req['minimum'],
                'is_choice' => false,  // AND condition
            ]);
        }

        return $class;
    }
}
