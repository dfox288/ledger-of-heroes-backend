<?php

namespace Tests\Feature\Models;

use App\Models\AbilityScore;
use App\Models\Modifier;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModifierModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_modifier_belongs_to_race_via_polymorphic(): void
    {
        $race = Race::factory()->create();
        $abilityScore = AbilityScore::where('code', 'STR')->first();

        $modifier = Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $abilityScore->id,
            'value' => '+2',
        ]);

        $this->assertEquals($race->id, $modifier->reference->id);
        $this->assertInstanceOf(Race::class, $modifier->reference);
    }

    public function test_race_has_many_modifiers(): void
    {
        $race = Race::factory()->create();
        $str = AbilityScore::where('code', 'STR')->first();
        $cha = AbilityScore::where('code', 'CHA')->first();

        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $str->id,
            'value' => '+2',
        ]);

        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $cha->id,
            'value' => '+1',
        ]);

        $this->assertCount(2, $race->modifiers);
    }
}
