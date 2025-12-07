<?php

namespace Tests\Unit\Models;

use App\Models\AbilityScore;
use App\Models\CharacterClass;
use App\Models\ClassLevelProgression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-db')]
class CharacterClassAccessorsTest extends TestCase
{
    use RefreshDatabase;

    private function createAbilityScore(string $code): AbilityScore
    {
        return AbilityScore::firstOrCreate(
            ['code' => $code],
            ['name' => ucfirst(strtolower($code)), 'description' => 'Test ability']
        );
    }

    #[Test]
    public function spellcasting_type_returns_full_for_full_caster(): void
    {
        $intelligence = $this->createAbilityScore('INT');
        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'spellcasting_ability_id' => $intelligence->id,
        ]);

        // Create level progression with 9th level spells (full caster)
        for ($level = 1; $level <= 20; $level++) {
            ClassLevelProgression::factory()->create([
                'class_id' => $wizard->id,
                'level' => $level,
                'spell_slots_9th' => $level >= 17 ? 1 : 0,
            ]);
        }

        $wizard->load('levelProgression');

        $this->assertEquals('full', $wizard->spellcasting_type);
    }

    #[Test]
    public function spellcasting_type_returns_half_for_half_caster(): void
    {
        $wisdom = $this->createAbilityScore('WIS');
        $paladin = CharacterClass::factory()->create([
            'name' => 'Paladin',
            'spellcasting_ability_id' => $wisdom->id,
        ]);

        // Create level progression with max 5th level spells (half caster)
        for ($level = 1; $level <= 20; $level++) {
            ClassLevelProgression::factory()->create([
                'class_id' => $paladin->id,
                'level' => $level,
                'spell_slots_5th' => $level >= 17 ? 2 : 0,
                'spell_slots_9th' => 0, // Half casters don't get 9th
            ]);
        }

        $paladin->load('levelProgression');

        $this->assertEquals('half', $paladin->spellcasting_type);
    }

    #[Test]
    public function spellcasting_type_returns_none_for_non_caster(): void
    {
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'spellcasting_ability_id' => null,
        ]);

        $this->assertEquals('none', $fighter->spellcasting_type);
    }

    #[Test]
    public function spellcasting_type_returns_pact_for_warlock(): void
    {
        $charisma = $this->createAbilityScore('CHA');
        $warlock = CharacterClass::factory()->create([
            'name' => 'Warlock',
            'spellcasting_ability_id' => $charisma->id,
        ]);

        $this->assertEquals('pact', $warlock->spellcasting_type);
    }

    #[Test]
    public function subclass_inherits_spellcasting_type_from_parent(): void
    {
        $intelligence = $this->createAbilityScore('INT');
        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'spellcasting_ability_id' => $intelligence->id,
        ]);

        // Create full caster progression for parent
        for ($level = 1; $level <= 20; $level++) {
            ClassLevelProgression::factory()->create([
                'class_id' => $wizard->id,
                'level' => $level,
                'spell_slots_9th' => $level >= 17 ? 1 : 0,
            ]);
        }

        // Create subclass without its own progression
        $abjurationWizard = CharacterClass::factory()->create([
            'name' => 'School of Abjuration',
            'parent_class_id' => $wizard->id,
            'spellcasting_ability_id' => $intelligence->id,
        ]);

        // Load relationships
        $abjurationWizard->load(['levelProgression', 'parentClass.levelProgression']);

        $this->assertEquals('full', $abjurationWizard->spellcasting_type);
    }

    #[Test]
    public function subclass_returns_none_when_parent_is_non_caster(): void
    {
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'spellcasting_ability_id' => null,
        ]);

        $champion = CharacterClass::factory()->create([
            'name' => 'Champion',
            'parent_class_id' => $fighter->id,
            'spellcasting_ability_id' => null,
        ]);

        $this->assertEquals('none', $champion->spellcasting_type);
    }

    #[Test]
    public function warlock_subclass_returns_pact(): void
    {
        $charisma = $this->createAbilityScore('CHA');
        $warlock = CharacterClass::factory()->create([
            'name' => 'Warlock',
            'spellcasting_ability_id' => $charisma->id,
        ]);

        $fiend = CharacterClass::factory()->create([
            'name' => 'The Fiend',
            'parent_class_id' => $warlock->id,
            'spellcasting_ability_id' => $charisma->id,
        ]);

        $fiend->load('parentClass');

        $this->assertEquals('pact', $fiend->spellcasting_type);
    }
}
