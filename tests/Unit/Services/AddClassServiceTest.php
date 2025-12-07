<?php

namespace Tests\Unit\Services;

use App\Exceptions\DuplicateClassException;
use App\Exceptions\MaxLevelReachedException;
use App\Exceptions\MulticlassPrerequisiteException;
use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\Proficiency;
use App\Services\AddClassService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AddClassServiceTest extends TestCase
{
    use RefreshDatabase;

    private AddClassService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AddClassService::class);
        $this->seedAbilityScores();
    }

    #[Test]
    public function it_adds_first_class_to_character(): void
    {
        $character = Character::factory()->create(['strength' => 14]);
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);

        $pivot = $this->service->addClass($character, $fighter);

        $this->assertTrue($pivot->is_primary);
        $this->assertEquals(1, $pivot->level);
        $this->assertEquals(1, $pivot->order);
        $this->assertTrue($pivot->characterClass->is($fighter));
    }

    #[Test]
    public function it_adds_second_class_as_non_primary(): void
    {
        $character = Character::factory()->create([
            'strength' => 14,
            'intelligence' => 14,
        ]);
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);

        // Add first class
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $pivot = $this->service->addClass($character->fresh(), $wizard);

        $this->assertFalse($pivot->is_primary);
        $this->assertEquals(1, $pivot->level);
        $this->assertEquals(2, $pivot->order);
    }

    #[Test]
    public function it_throws_when_prerequisites_not_met(): void
    {
        $character = Character::factory()->create(['charisma' => 8]);
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $bard = CharacterClass::factory()->create(['name' => 'Bard', 'parent_class_id' => null]);

        // Give character a class first (multiclassing requires existing class)
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Add Bard requirement
        $cha = AbilityScore::where('code', 'CHA')->first();
        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $bard->id,
            'proficiency_type' => 'multiclass_requirement',
            'proficiency_name' => 'Charisma 13',
            'ability_score_id' => $cha->id,
            'quantity' => 13,
            'is_choice' => false,
        ]);

        $this->expectException(MulticlassPrerequisiteException::class);

        $this->service->addClass($character->fresh(), $bard);
    }

    #[Test]
    public function it_bypasses_prerequisites_with_force(): void
    {
        $character = Character::factory()->create(['charisma' => 8]);
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $bard = CharacterClass::factory()->create(['name' => 'Bard', 'parent_class_id' => null]);

        // Give character a class first
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Add Bard requirement
        $cha = AbilityScore::where('code', 'CHA')->first();
        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $bard->id,
            'proficiency_type' => 'multiclass_requirement',
            'proficiency_name' => 'Charisma 13',
            'ability_score_id' => $cha->id,
            'quantity' => 13,
            'is_choice' => false,
        ]);

        $pivot = $this->service->addClass($character->fresh(), $bard, force: true);

        $this->assertNotNull($pivot);
    }

    #[Test]
    public function it_throws_when_class_already_exists(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->expectException(DuplicateClassException::class);

        $this->service->addClass($character->fresh(), $fighter);
    }

    #[Test]
    public function it_throws_when_total_level_would_exceed_20(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->full_slug,
            'level' => 20,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->expectException(MaxLevelReachedException::class);

        $this->service->addClass($character->fresh(), $wizard);
    }

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
}
