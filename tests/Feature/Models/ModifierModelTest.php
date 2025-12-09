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
    public function modifier_supports_choice_fields(): void
    {
        $race = Race::factory()->create();

        $modifier = Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'value' => '+1',
            'is_choice' => true,
            'choice_count' => 2,
            'choice_constraint' => 'different',
        ]);

        $this->assertTrue($modifier->is_choice);
        $this->assertEquals(2, $modifier->choice_count);
        $this->assertEquals('different', $modifier->choice_constraint);
    }
}
