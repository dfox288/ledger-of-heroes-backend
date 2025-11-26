<?php

namespace Tests\Unit\Services\Importers\Concerns;

use App\Models\AbilityScore;
use App\Models\DamageType;
use App\Models\Modifier;
use App\Models\Race;
use App\Models\Skill;
use App\Services\Importers\Concerns\ImportsModifiers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class ImportsModifiersTest extends TestCase
{
    use ImportsModifiers;
    use RefreshDatabase;

    #[Test]
    public function it_imports_ability_score_modifiers()
    {
        $race = Race::factory()->create();
        $str = AbilityScore::where('code', 'STR')->first();
        $cha = AbilityScore::where('code', 'CHA')->first();

        $modifiersData = [
            [
                'category' => 'ability_score',
                'value' => '+2',
                'ability_score_id' => $str->id,
            ],
            [
                'category' => 'ability_score',
                'value' => '+1',
                'ability_score_id' => $cha->id,
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData);

        $this->assertCount(2, $race->modifiers);
        $this->assertEquals('ability_score', $race->modifiers[0]->modifier_category);
        $this->assertEquals($str->id, $race->modifiers[0]->ability_score_id);
        $this->assertEquals('+2', $race->modifiers[0]->value);
    }

    #[Test]
    public function it_imports_skill_modifiers()
    {
        $race = Race::factory()->create();
        $skill = Skill::where('name', 'Perception')->first();

        $modifiersData = [
            [
                'category' => 'skill',
                'value' => '+5',
                'skill_id' => $skill->id,
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData);

        $modifier = $race->modifiers->first();
        $this->assertEquals('skill', $modifier->modifier_category);
        $this->assertEquals($skill->id, $modifier->skill_id);
        $this->assertEquals('+5', $modifier->value);
    }

    #[Test]
    public function it_imports_damage_resistance_modifiers()
    {
        $race = Race::factory()->create();
        $damageType = DamageType::where('code', 'F')->first(); // F = Fire

        $modifiersData = [
            [
                'category' => 'damage_resistance',
                'value' => 'resistance',
                'damage_type_id' => $damageType->id,
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData);

        $modifier = $race->modifiers->first();
        $this->assertEquals('damage_resistance', $modifier->modifier_category);
        $this->assertEquals($damageType->id, $modifier->damage_type_id);
        $this->assertEquals('resistance', $modifier->value);
    }

    #[Test]
    public function it_clears_existing_modifiers_before_import()
    {
        $race = Race::factory()->create();
        $str = AbilityScore::where('code', 'STR')->first();

        // Create initial modifiers
        Modifier::factory()->forEntity(Race::class, $race->id)->create([
            'modifier_category' => 'ability_score',
            'ability_score_id' => $str->id,
            'value' => '+2',
        ]);
        Modifier::factory()->forEntity(Race::class, $race->id)->create([
            'modifier_category' => 'ability_score',
            'ability_score_id' => $str->id,
            'value' => '+1',
        ]);

        $this->assertCount(2, $race->fresh()->modifiers);

        // Import new modifiers (should clear old ones)
        $modifiersData = [
            [
                'category' => 'ability_score',
                'value' => '+3',
                'ability_score_id' => $str->id,
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData);

        $race->refresh();
        $this->assertCount(1, $race->modifiers);
        $this->assertEquals('+3', $race->modifiers[0]->value);
    }

    #[Test]
    public function it_handles_modifiers_with_choice_fields()
    {
        $race = Race::factory()->create();

        $modifiersData = [
            [
                'category' => 'ability_score',
                'value' => '+1',
                'is_choice' => true,
                'choice_count' => 2,
                'choice_constraint' => 'different',
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData);

        $modifier = $race->modifiers->first();
        $this->assertTrue($modifier->is_choice);
        $this->assertEquals(2, $modifier->choice_count);
        $this->assertEquals('different', $modifier->choice_constraint);
        $this->assertNull($modifier->ability_score_id); // Choices don't have specific ability
    }

    #[Test]
    public function it_handles_empty_modifiers_array()
    {
        $race = Race::factory()->create();

        // Create initial modifier
        Modifier::factory()->forEntity(Race::class, $race->id)->create();
        $this->assertCount(1, $race->fresh()->modifiers);

        // Import empty array (should clear all)
        $this->importEntityModifiers($race, []);

        $this->assertCount(0, $race->fresh()->modifiers);
    }

    #[Test]
    public function it_imports_modifiers_with_all_optional_fields_null()
    {
        $race = Race::factory()->create();

        $modifiersData = [
            [
                'category' => 'other',
                'value' => 'special_bonus',
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData);

        $modifier = $race->modifiers->first();
        $this->assertEquals('other', $modifier->modifier_category);
        $this->assertEquals('special_bonus', $modifier->value);
        $this->assertNull($modifier->ability_score_id);
        $this->assertNull($modifier->skill_id);
        $this->assertNull($modifier->damage_type_id);
    }
}
