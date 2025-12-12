<?php

namespace Tests\Feature\Models;

use App\Models\AbilityScore;
use App\Models\Modifier;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class ModifierModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function modifier_belongs_to_race_via_polymorphic(): void
    {
        $race = Race::factory()->create();
        $abilityScore = AbilityScore::where('code', 'STR')->first();

        $modifier = Modifier::factory()->forEntity(Race::class, $race->id)->create([
            'modifier_category' => 'ability_score',
            'ability_score_id' => $abilityScore->id,
            'value' => '+2',
        ]);

        $this->assertEquals($race->id, $modifier->reference->id);
        $this->assertInstanceOf(Race::class, $modifier->reference);
    }

    #[Test]
    public function race_has_many_modifiers(): void
    {
        $race = Race::factory()->create();
        $str = AbilityScore::where('code', 'STR')->first();
        $cha = AbilityScore::where('code', 'CHA')->first();

        Modifier::factory()->forEntity(Race::class, $race->id)->create([
            'modifier_category' => 'ability_score',
            'ability_score_id' => $str->id,
            'value' => '+2',
        ]);

        Modifier::factory()->forEntity(Race::class, $race->id)->create([
            'modifier_category' => 'ability_score',
            'ability_score_id' => $cha->id,
            'value' => '+1',
        ]);

        $this->assertCount(2, $race->modifiers);
    }

    #[Test]
    public function modifier_choice_fields_are_now_in_entity_choices(): void
    {
        $race = Race::factory()->create();

        // Choice modifiers are now stored in entity_choices table
        $entityChoice = \App\Models\EntityChoice::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'choice_type' => 'ability_score',
            'choice_group' => 'test_ability_choice',
            'quantity' => 2,
            'constraint' => 'different',
            'constraints' => ['value' => '+1'],
            'level_granted' => 1,
            'is_required' => true,
        ]);

        $this->assertEquals('ability_score', $entityChoice->choice_type);
        $this->assertEquals(2, $entityChoice->quantity);
        $this->assertEquals('different', $entityChoice->constraint);
    }
}
