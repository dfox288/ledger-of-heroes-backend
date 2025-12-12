<?php

namespace Tests\Unit\Services\Importers\Concerns;

use App\Models\AbilityScore;
use App\Models\DamageType;
use App\Models\EntityChoice;
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

        // Choice modifiers are now stored in EntityChoice table
        $choice = EntityChoice::where('reference_type', Race::class)
            ->where('reference_id', $race->id)
            ->where('choice_type', 'ability_score')
            ->first();

        $this->assertNotNull($choice);
        $this->assertEquals(2, $choice->quantity);
        $this->assertEquals('different', $choice->constraint);
        $this->assertEquals('+1', $choice->constraints['value'] ?? null);
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

    #[Test]
    public function it_resolves_skill_name_to_skill_id()
    {
        $race = Race::factory()->create();
        $perception = Skill::where('name', 'Perception')->first();
        $investigation = Skill::where('name', 'Investigation')->first();

        $modifiersData = [
            [
                'modifier_category' => 'passive_score',
                'value' => 5,
                'skill_name' => 'Perception',
            ],
            [
                'modifier_category' => 'passive_score',
                'value' => 5,
                'skill_name' => 'Investigation',
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData);

        $modifiers = $race->modifiers->sortBy('skill_id')->values();
        $this->assertCount(2, $modifiers);

        $perceptionMod = $modifiers->firstWhere('skill_id', $perception->id);
        $this->assertEquals('passive_score', $perceptionMod->modifier_category);
        $this->assertEquals('5', $perceptionMod->value);
        $this->assertEquals($perception->id, $perceptionMod->skill_id);

        $investigationMod = $modifiers->firstWhere('skill_id', $investigation->id);
        $this->assertEquals('passive_score', $investigationMod->modifier_category);
        $this->assertEquals('5', $investigationMod->value);
        $this->assertEquals($investigation->id, $investigationMod->skill_id);
    }

    #[Test]
    public function it_imports_skill_advantage_modifier()
    {
        $race = Race::factory()->create();
        $deception = Skill::where('name', 'Deception')->first();

        $modifiersData = [
            [
                'modifier_category' => 'skill_advantage',
                'value' => 'advantage',
                'skill_name' => 'Deception',
                'condition' => 'when trying to pass yourself off as a different person',
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData);

        $modifier = $race->modifiers->first();
        $this->assertNotNull($modifier);
        $this->assertEquals('skill_advantage', $modifier->modifier_category);
        $this->assertEquals('advantage', $modifier->value);
        $this->assertEquals($deception->id, $modifier->skill_id);
        $this->assertEquals('when trying to pass yourself off as a different person', $modifier->condition);
    }

    #[Test]
    public function it_uses_import_modifier_helper_method()
    {
        $race = Race::factory()->create();
        $str = AbilityScore::where('code', 'STR')->first();

        $modifier = $this->importModifier($race, 'ability_score', [
            'value' => '+2',
            'ability_score_id' => $str->id,
        ]);

        $this->assertInstanceOf(Modifier::class, $modifier);
        $this->assertEquals('ability_score', $modifier->modifier_category);
        $this->assertEquals('+2', $modifier->value);
        $this->assertEquals($str->id, $modifier->ability_score_id);
    }

    #[Test]
    public function it_uses_import_asi_modifier_helper_method_with_default_value()
    {
        $race = Race::factory()->create();

        $choice = $this->importAsiModifier($race, 4);

        // importAsiModifier now returns EntityChoice
        $this->assertInstanceOf(EntityChoice::class, $choice);
        $this->assertEquals('ability_score', $choice->choice_type);
        $this->assertEquals(4, $choice->level_granted);
        $this->assertEquals('+2', $choice->constraints['value'] ?? null);
        $this->assertEquals('different', $choice->constraint);
        $this->assertEquals(2, $choice->quantity);
    }

    #[Test]
    public function it_uses_import_asi_modifier_helper_method_with_custom_value()
    {
        $race = Race::factory()->create();

        $choice = $this->importAsiModifier($race, 8, '+1');

        // importAsiModifier now returns EntityChoice
        $this->assertEquals(8, $choice->level_granted);
        $this->assertEquals('+1', $choice->constraints['value'] ?? null);
    }

    #[Test]
    public function it_resolves_ability_score_code_to_id()
    {
        $race = Race::factory()->create();

        $modifiersData = [
            [
                'modifier_category' => 'ability_score',
                'value' => '+2',
                'ability_score_code' => 'STR',
            ],
            [
                'modifier_category' => 'ability_score',
                'value' => '+1',
                'ability_score_code' => 'CHA',
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData);

        $modifiers = $race->modifiers->sortBy('ability_score_id')->values();
        $this->assertCount(2, $modifiers);

        $str = AbilityScore::where('code', 'STR')->first();
        $cha = AbilityScore::where('code', 'CHA')->first();

        $this->assertEquals($str->id, $modifiers[0]->ability_score_id);
        $this->assertEquals($cha->id, $modifiers[1]->ability_score_id);
    }

    #[Test]
    public function it_resolves_damage_type_name_to_id()
    {
        $race = Race::factory()->create();

        $modifiersData = [
            [
                'modifier_category' => 'damage_resistance',
                'value' => 'resistance',
                'damage_type_name' => 'Fire',
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData);

        $modifier = $race->modifiers->first();
        $fireDamageType = DamageType::where('name', 'Fire')->first();
        $this->assertEquals($fireDamageType->id, $modifier->damage_type_id);
    }

    #[Test]
    public function it_resolves_damage_type_code_to_id()
    {
        $race = Race::factory()->create();

        $modifiersData = [
            [
                'modifier_category' => 'damage_immunity',
                'value' => 'immunity',
                'damage_type_code' => 'F',
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData);

        $modifier = $race->modifiers->first();
        $fireDamageType = DamageType::where('code', 'F')->first();
        $this->assertEquals($fireDamageType->id, $modifier->damage_type_id);
    }

    #[Test]
    public function it_prefers_damage_type_name_over_code()
    {
        $race = Race::factory()->create();

        // Create a custom damage type with same code but different name
        $fireDamageType = DamageType::where('name', 'Fire')->first();

        $modifiersData = [
            [
                'modifier_category' => 'damage_resistance',
                'value' => 'resistance',
                'damage_type_name' => 'Fire',
                'damage_type_code' => 'WRONG_CODE',
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData);

        $modifier = $race->modifiers->first();
        // Should use name lookup, not code
        $this->assertEquals($fireDamageType->id, $modifier->damage_type_id);
    }

    #[Test]
    public function it_uses_update_or_create_to_prevent_duplicates()
    {
        $race = Race::factory()->create();
        $str = AbilityScore::where('code', 'STR')->first();

        $modifiersData = [
            [
                'modifier_category' => 'ability_score',
                'value' => '+2',
                'ability_score_id' => $str->id,
            ],
        ];

        // Import twice
        $this->importEntityModifiers($race, $modifiersData);
        $this->importEntityModifiers($race, $modifiersData);

        // Should still only have 1 modifier (delete + recreate pattern)
        $this->assertCount(1, $race->fresh()->modifiers);
    }

    #[Test]
    public function it_handles_modifiers_with_level()
    {
        $race = Race::factory()->create();
        $str = AbilityScore::where('code', 'STR')->first();

        $modifiersData = [
            [
                'modifier_category' => 'ability_score',
                'value' => '+2',
                'ability_score_id' => $str->id,
                'level' => 3,
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData);

        $modifier = $race->modifiers->first();
        $this->assertEquals(3, $modifier->level);
    }

    #[Test]
    public function it_handles_modifiers_with_condition()
    {
        $race = Race::factory()->create();

        $modifiersData = [
            [
                'modifier_category' => 'armor_class',
                'value' => '+1',
                'condition' => 'while wearing light armor',
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData);

        $modifier = $race->modifiers->first();
        $this->assertEquals('while wearing light armor', $modifier->condition);
    }

    #[Test]
    public function it_handles_modifier_with_choice_constraint()
    {
        $race = Race::factory()->create();

        $modifiersData = [
            [
                'modifier_category' => 'ability_score',
                'value' => '+1',
                'is_choice' => true,
                'choice_count' => 1,
                'choice_constraint' => 'Intelligence, Wisdom, or Charisma',
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData);

        // Choice modifiers are now stored in EntityChoice table
        $choice = EntityChoice::where('reference_type', Race::class)
            ->where('reference_id', $race->id)
            ->where('choice_type', 'ability_score')
            ->first();

        $this->assertNotNull($choice);
        $this->assertEquals('Intelligence, Wisdom, or Charisma', $choice->constraint);
    }

    #[Test]
    public function it_accepts_category_or_modifier_category_key()
    {
        $race = Race::factory()->create();

        // Test with 'category' key
        $modifiersData1 = [
            [
                'category' => 'speed',
                'value' => '+10',
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData1);
        $this->assertEquals('speed', $race->modifiers->first()->modifier_category);

        // Clear and test with 'modifier_category' key
        $race->modifiers()->delete();

        $modifiersData2 = [
            [
                'modifier_category' => 'speed',
                'value' => '+10',
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData2);
        $this->assertEquals('speed', $race->fresh()->modifiers->first()->modifier_category);
    }

    #[Test]
    public function it_handles_skill_lookup_failure_gracefully()
    {
        $race = Race::factory()->create();

        $modifiersData = [
            [
                'modifier_category' => 'passive_score',
                'value' => 5,
                'skill_name' => 'Nonexistent Skill',
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData);

        $modifier = $race->modifiers->first();
        $this->assertNull($modifier->skill_id);
    }

    #[Test]
    public function it_handles_ability_score_lookup_failure_gracefully()
    {
        $race = Race::factory()->create();

        $modifiersData = [
            [
                'modifier_category' => 'ability_score',
                'value' => '+2',
                'ability_score_code' => 'NONEXISTENT',
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData);

        $modifier = $race->modifiers->first();
        $this->assertNull($modifier->ability_score_id);
    }

    #[Test]
    public function it_handles_damage_type_lookup_failure_gracefully()
    {
        $race = Race::factory()->create();

        $modifiersData = [
            [
                'modifier_category' => 'damage_resistance',
                'value' => 'resistance',
                'damage_type_name' => 'Nonexistent Type',
            ],
        ];

        $this->importEntityModifiers($race, $modifiersData);

        $modifier = $race->modifiers->first();
        $this->assertNull($modifier->damage_type_id);
    }
}
