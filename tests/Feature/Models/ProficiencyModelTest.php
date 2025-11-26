<?php

namespace Tests\Feature\Models;

use App\Models\Proficiency;
use App\Models\Race;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class ProficiencyModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_proficiency_belongs_to_race_via_polymorphic(): void
    {
        $race = Race::factory()->create();
        $skill = Skill::where('name', 'Perception')->first();

        $proficiency = Proficiency::factory()->forEntity(Race::class, $race->id)->create([
            'proficiency_type' => 'skill',
            'skill_id' => $skill->id,
        ]);

        $this->assertEquals($race->id, $proficiency->reference->id);
        $this->assertInstanceOf(Race::class, $proficiency->reference);
    }

    public function test_race_has_many_proficiencies(): void
    {
        $race = Race::factory()->create();
        $skill = Skill::where('name', 'Perception')->first();

        Proficiency::factory()->forEntity(Race::class, $race->id)->create([
            'proficiency_type' => 'skill',
            'skill_id' => $skill->id,
        ]);

        Proficiency::factory()->forEntity(Race::class, $race->id)->create([
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'Longsword',
        ]);

        $this->assertCount(2, $race->proficiencies);
    }
}
