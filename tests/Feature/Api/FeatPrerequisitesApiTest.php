<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\Feat;
use App\Models\Race;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class FeatPrerequisitesApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    #[Test]
    public function feat_includes_ability_score_prerequisite_in_response()
    {
        // Create a feat with an ability score prerequisite
        $feat = Feat::factory()->create([
            'name' => 'Heavy Armor Master',
            'prerequisites_text' => 'Str 13 or higher',
        ]);

        $strength = AbilityScore::where('code', 'STR')->first();

        $feat->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
            'description' => 'Str 13 or higher',
        ]);

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'prerequisites' => [
                        '*' => [
                            'id',
                            'prerequisite_type',
                            'prerequisite_id',
                            'minimum_value',
                            'description',
                            'group_id',
                            'ability_score',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.prerequisites.0.prerequisite_type', AbilityScore::class)
            ->assertJsonPath('data.prerequisites.0.minimum_value', 13)
            ->assertJsonPath('data.prerequisites.0.description', 'Str 13 or higher')
            ->assertJsonPath('data.prerequisites.0.ability_score.code', 'STR')
            ->assertJsonPath('data.prerequisites.0.ability_score.name', 'Strength');
    }

    #[Test]
    public function feat_includes_race_prerequisite_in_response()
    {
        // Create a feat with a race prerequisite
        $feat = Feat::factory()->create([
            'name' => 'Dwarven Fortitude',
            'prerequisites_text' => 'Dwarf',
        ]);

        $dwarf = Race::factory()->create([
            'name' => 'Dwarf',
            'slug' => 'dwarf',
        ]);

        $feat->prerequisites()->create([
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $dwarf->id,
            'description' => 'Dwarf',
        ]);

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'prerequisites' => [
                        '*' => [
                            'id',
                            'prerequisite_type',
                            'prerequisite_id',
                            'minimum_value',
                            'description',
                            'group_id',
                            'race',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.prerequisites.0.prerequisite_type', Race::class)
            ->assertJsonPath('data.prerequisites.0.description', 'Dwarf')
            ->assertJsonPath('data.prerequisites.0.race.name', 'Dwarf')
            ->assertJsonPath('data.prerequisites.0.race.slug', 'dwarf');
    }

    #[Test]
    public function feat_includes_skill_prerequisite_in_response()
    {
        // Create a feat with a skill prerequisite
        $feat = Feat::factory()->create([
            'name' => 'Skill Expert',
            'prerequisites_text' => 'Proficiency in Athletics',
        ]);

        $athletics = Skill::where('name', 'Athletics')->first();

        $feat->prerequisites()->create([
            'prerequisite_type' => Skill::class,
            'prerequisite_id' => $athletics->id,
            'description' => 'Proficiency in Athletics',
        ]);

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'prerequisites' => [
                        '*' => [
                            'id',
                            'prerequisite_type',
                            'prerequisite_id',
                            'minimum_value',
                            'description',
                            'group_id',
                            'skill',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.prerequisites.0.prerequisite_type', Skill::class)
            ->assertJsonPath('data.prerequisites.0.description', 'Proficiency in Athletics')
            ->assertJsonPath('data.prerequisites.0.skill.name', 'Athletics');
    }

    #[Test]
    public function feat_with_multiple_prerequisites_returns_all()
    {
        // Create a feat with multiple prerequisites (e.g., "Str 13 and proficiency in heavy armor")
        $feat = Feat::factory()->create([
            'name' => 'Heavy Armor Master',
            'prerequisites_text' => 'Str 13, proficiency with heavy armor',
        ]);

        $strength = AbilityScore::where('code', 'STR')->first();

        $feat->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
            'description' => 'Str 13',
        ]);

        $feat->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 15,
            'description' => 'Str 15 (alternative)',
            'group_id' => 1,
        ]);

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.prerequisites')
            ->assertJsonPath('data.prerequisites.0.minimum_value', 13)
            ->assertJsonPath('data.prerequisites.1.minimum_value', 15)
            ->assertJsonPath('data.prerequisites.1.group_id', 1);
    }

    #[Test]
    public function feat_without_prerequisites_returns_empty_array()
    {
        $feat = Feat::factory()->create([
            'name' => 'Alert',
            'prerequisites_text' => null,
        ]);

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk()
            ->assertJsonPath('data.prerequisites', []);
    }
}
