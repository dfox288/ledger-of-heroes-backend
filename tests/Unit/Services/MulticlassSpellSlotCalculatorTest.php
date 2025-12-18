<?php

namespace Tests\Unit\Services;

use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\ClassLevelProgression;
use App\Services\MulticlassSpellSlotCalculator;
use Database\Seeders\MulticlassSpellSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MulticlassSpellSlotCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private MulticlassSpellSlotCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MulticlassSpellSlotSeeder::class);
        $this->seedAbilityScores();
        $this->calculator = new MulticlassSpellSlotCalculator;
    }

    #[Test]
    public function it_calculates_caster_level_for_full_caster(): void
    {
        $character = Character::factory()->create();
        $wizard = $this->createFullCaster('Wizard');

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $wizard->slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $casterLevel = $this->calculator->calculateCasterLevel($character->fresh());

        $this->assertEquals(5, $casterLevel);
    }

    #[Test]
    public function it_calculates_caster_level_for_half_caster(): void
    {
        $character = Character::factory()->create();
        $paladin = $this->createHalfCaster('Paladin');

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $paladin->slug,
            'level' => 6,
            'is_primary' => true,
            'order' => 1,
        ]);

        $casterLevel = $this->calculator->calculateCasterLevel($character->fresh());

        $this->assertEquals(3, $casterLevel); // floor(6 * 0.5)
    }

    #[Test]
    public function it_calculates_caster_level_for_third_caster(): void
    {
        $character = Character::factory()->create();
        $eldritchKnight = $this->createThirdCaster('Eldritch Knight');

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $eldritchKnight->slug,
            'level' => 9,
            'is_primary' => true,
            'order' => 1,
        ]);

        $casterLevel = $this->calculator->calculateCasterLevel($character->fresh());

        $this->assertEquals(3, $casterLevel); // floor(9 / 3)
    }

    #[Test]
    public function it_excludes_warlock_from_caster_level(): void
    {
        $character = Character::factory()->create();
        $warlock = $this->createWarlock();

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $warlock->slug,
            'level' => 10,
            'is_primary' => true,
            'order' => 1,
        ]);

        $casterLevel = $this->calculator->calculateCasterLevel($character->fresh());

        $this->assertEquals(0, $casterLevel);
    }

    #[Test]
    public function it_combines_caster_levels_from_multiple_classes(): void
    {
        $character = Character::factory()->create();
        $cleric = $this->createFullCaster('Cleric');
        $paladin = $this->createHalfCaster('Paladin');

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $cleric->slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $paladin->slug,
            'level' => 4,
            'is_primary' => false,
            'order' => 2,
        ]);

        $casterLevel = $this->calculator->calculateCasterLevel($character->fresh());

        $this->assertEquals(7, $casterLevel); // 5 + floor(4 * 0.5)
    }

    #[Test]
    public function it_returns_spell_slots_for_multiclass_caster(): void
    {
        $character = Character::factory()->create();
        $cleric = $this->createFullCaster('Cleric');
        $wizard = $this->createFullCaster('Wizard2');

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $cleric->slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $wizard->slug,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $result = $this->calculator->calculate($character->fresh());

        // Caster level 8 = 4/3/3/2/0/0/0/0/0
        $this->assertEquals(4, $result->standardSlots['1st']);
        $this->assertEquals(3, $result->standardSlots['2nd']);
        $this->assertEquals(3, $result->standardSlots['3rd']);
        $this->assertEquals(2, $result->standardSlots['4th']);
        $this->assertEquals(0, $result->standardSlots['5th']);
        $this->assertNull($result->pactSlots);
    }

    #[Test]
    public function it_returns_separate_pact_slots_for_warlock(): void
    {
        $character = Character::factory()->create();
        $wizard = $this->createFullCaster('Wizard');
        $warlock = $this->createWarlock();

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $wizard->slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $warlock->slug,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $result = $this->calculator->calculate($character->fresh());

        // Standard slots from Wizard 5 only (caster level 5)
        $this->assertEquals(4, $result->standardSlots['1st']);
        $this->assertEquals(3, $result->standardSlots['2nd']);
        $this->assertEquals(2, $result->standardSlots['3rd']);

        // Pact slots from Warlock 3 (2 slots at 2nd level)
        $this->assertNotNull($result->pactSlots);
        $this->assertEquals(2, $result->pactSlots->count);
        $this->assertEquals(2, $result->pactSlots->level);
    }

    #[Test]
    public function it_returns_null_for_non_caster(): void
    {
        $character = Character::factory()->create();
        $fighter = $this->createNonCaster('Fighter');

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->slug,
            'level' => 10,
            'is_primary' => true,
            'order' => 1,
        ]);

        $result = $this->calculator->calculate($character->fresh());

        $this->assertNull($result->standardSlots);
        $this->assertNull($result->pactSlots);
    }

    #[Test]
    public function it_uses_subclass_spellcasting_when_base_class_is_non_caster(): void
    {
        // D&D 5e: Fighter (non-caster base) with Eldritch Knight subclass (third caster)
        $character = Character::factory()->create();
        $fighter = $this->createNonCaster('FighterBase');
        $eldritchKnight = $this->createThirdCasterSubclass('Eldritch Knight', $fighter);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->slug,
            'subclass_slug' => $eldritchKnight->slug,
            'level' => 9,
            'is_primary' => true,
            'order' => 1,
        ]);

        $casterLevel = $this->calculator->calculateCasterLevel($character->fresh());

        // Eldritch Knight at level 9 = floor(9 * 0.334) = 3 caster level
        $this->assertEquals(3, $casterLevel);
    }

    #[Test]
    public function it_returns_spell_slots_for_fighter_eldritch_knight(): void
    {
        $character = Character::factory()->create();
        $fighter = $this->createNonCaster('FighterBase2');
        $eldritchKnight = $this->createThirdCasterSubclass('Eldritch Knight 2', $fighter);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->slug,
            'subclass_slug' => $eldritchKnight->slug,
            'level' => 3,
            'is_primary' => true,
            'order' => 1,
        ]);

        $result = $this->calculator->calculate($character->fresh());

        // EK level 3 = caster level 1 = 2 1st level spell slots
        $this->assertNotNull($result->standardSlots);
        $this->assertEquals(2, $result->standardSlots['1st']);
        $this->assertNull($result->pactSlots);
    }

    // Helper methods

    private function seedAbilityScores(): void
    {
        AbilityScore::updateOrCreate(['id' => 4], ['code' => 'INT', 'name' => 'Intelligence']);
        AbilityScore::updateOrCreate(['id' => 5], ['code' => 'WIS', 'name' => 'Wisdom']);
        AbilityScore::updateOrCreate(['id' => 6], ['code' => 'CHA', 'name' => 'Charisma']);
    }

    private function createFullCaster(string $name): CharacterClass
    {
        $class = CharacterClass::factory()->create([
            'name' => $name,
            'slug' => 'test:'.strtolower(str_replace(' ', '-', $name)),
            'parent_class_id' => null,
            'spellcasting_ability_id' => 4, // INT
        ]);

        // Create level progression with 9th level spell slots (full caster)
        $this->createLevelProgression($class->id, 9);

        return $class;
    }

    private function createHalfCaster(string $name): CharacterClass
    {
        $class = CharacterClass::factory()->create([
            'name' => $name,
            'slug' => 'test:'.strtolower(str_replace(' ', '-', $name)),
            'parent_class_id' => null,
            'spellcasting_ability_id' => 6, // CHA
        ]);

        // Create level progression with 5th level spell slots (half caster)
        $this->createLevelProgression($class->id, 5);

        return $class;
    }

    private function createThirdCaster(string $name): CharacterClass
    {
        $class = CharacterClass::factory()->create([
            'name' => $name,
            'slug' => 'test:'.strtolower(str_replace(' ', '-', $name)),
            'parent_class_id' => null,
            'spellcasting_ability_id' => 4, // INT
        ]);

        // Create level progression with 4th level spell slots (third caster)
        $this->createLevelProgression($class->id, 4);

        return $class;
    }

    private function createWarlock(): CharacterClass
    {
        return CharacterClass::factory()->create([
            'name' => 'Warlock',
            'slug' => 'test:warlock',
            'parent_class_id' => null,
            'spellcasting_ability_id' => 6, // CHA
        ]);
    }

    private function createNonCaster(string $name): CharacterClass
    {
        return CharacterClass::factory()->create([
            'name' => $name,
            'slug' => 'test:'.strtolower(str_replace(' ', '-', $name)),
            'parent_class_id' => null,
            'spellcasting_ability_id' => null,
        ]);
    }

    private function createThirdCasterSubclass(string $name, CharacterClass $parentClass): CharacterClass
    {
        $subclass = CharacterClass::factory()->create([
            'name' => $name,
            'slug' => 'test:'.strtolower(str_replace(' ', '-', $name)),
            'parent_class_id' => $parentClass->id,
            'spellcasting_ability_id' => 4, // INT
        ]);

        // Create level progression with 4th level spell slots (third caster)
        $this->createLevelProgression($subclass->id, 4);

        return $subclass;
    }

    private function createLevelProgression(int $classId, int $maxSpellLevel): void
    {
        // Create a single row at level 20 showing max spell level capability
        $data = [
            'class_id' => $classId,
            'level' => 20,
            'proficiency_bonus' => 6,
        ];

        $ordinals = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th'];
        for ($i = 1; $i <= 9; $i++) {
            $column = "spell_slots_{$ordinals[$i - 1]}";
            $data[$column] = $i <= $maxSpellLevel ? 1 : 0;
        }

        ClassLevelProgression::create($data);
    }
}
