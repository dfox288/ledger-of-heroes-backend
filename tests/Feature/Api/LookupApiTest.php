<?php

namespace Tests\Feature\Api;

use App\Models\Source;
use App\Models\SpellSchool;
use App\Models\DamageType;
use App\Models\Size;
use App\Models\AbilityScore;
use App\Models\Skill;
use App\Models\ItemType;
use App\Models\ItemProperty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LookupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_all_sources(): void
    {
        $response = $this->getJson('/api/v1/sources');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'code', 'name', 'publisher', 'publication_year', 'edition']
            ])
            ->assertJsonCount(6); // We have 6 sources seeded
    }

    public function test_can_get_all_spell_schools(): void
    {
        $response = $this->getJson('/api/v1/spell-schools');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'code', 'name', 'description']
            ])
            ->assertJsonCount(8); // We have 8 schools seeded
    }

    public function test_can_get_all_damage_types(): void
    {
        $response = $this->getJson('/api/v1/damage-types');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'name']
            ]);
    }

    public function test_can_get_all_sizes(): void
    {
        $response = $this->getJson('/api/v1/sizes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'code', 'name']
            ]);
    }

    public function test_can_get_all_ability_scores(): void
    {
        $response = $this->getJson('/api/v1/ability-scores');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'code', 'name']
            ]);
    }

    public function test_can_get_all_skills(): void
    {
        $response = $this->getJson('/api/v1/skills');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'name', 'ability_score_id']
            ]);
    }

    public function test_can_get_all_item_types(): void
    {
        $response = $this->getJson('/api/v1/item-types');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'name']
            ]);
    }

    public function test_can_get_all_item_properties(): void
    {
        $response = $this->getJson('/api/v1/item-properties');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'code', 'name', 'description']
            ]);
    }

    public function test_can_get_single_source(): void
    {
        $source = Source::first();
        $response = $this->getJson("/api/v1/sources/{$source->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'code', 'name', 'publisher', 'publication_year', 'edition']);
    }

    public function test_can_get_single_spell_school(): void
    {
        $school = SpellSchool::first();
        $response = $this->getJson("/api/v1/spell-schools/{$school->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'code', 'name', 'description']);
    }
}
