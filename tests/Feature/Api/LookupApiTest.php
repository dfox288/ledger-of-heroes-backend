<?php

namespace Tests\Feature\Api;

use App\Models\Source;
use App\Models\SpellSchool;
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
                'data' => [
                    '*' => ['id', 'code', 'name', 'publisher', 'publication_year', 'edition'],
                ],
                'meta',
                'links',
            ]);
    }

    public function test_can_get_all_spell_schools(): void
    {
        $response = $this->getJson('/api/v1/spell-schools');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'description'],
                ],
                'meta',
                'links',
            ]);
    }

    public function test_can_get_all_damage_types(): void
    {
        $response = $this->getJson('/api/v1/damage-types');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name'],
                ],
                'meta',
                'links',
            ]);
    }

    public function test_can_get_all_sizes(): void
    {
        $response = $this->getJson('/api/v1/sizes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name'],
                ],
                'meta',
                'links',
            ]);
    }

    public function test_can_get_all_ability_scores(): void
    {
        $response = $this->getJson('/api/v1/ability-scores');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name'],
                ],
                'meta',
                'links',
            ]);
    }

    public function test_can_get_all_skills(): void
    {
        $response = $this->getJson('/api/v1/skills');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'ability_score'],
                ],
                'meta',
                'links',
            ]);
    }

    public function test_can_get_all_item_types(): void
    {
        $response = $this->getJson('/api/v1/item-types');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name'],
                ],
                'meta',
                'links',
            ]);
    }

    public function test_can_get_all_item_properties(): void
    {
        $response = $this->getJson('/api/v1/item-properties');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'description'],
                ],
                'meta',
                'links',
            ]);
    }

    public function test_can_get_single_source(): void
    {
        $source = Source::first();
        $response = $this->getJson("/api/v1/sources/{$source->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'code', 'name', 'publisher', 'publication_year', 'edition'],
            ]);
    }

    public function test_can_get_single_spell_school(): void
    {
        $school = SpellSchool::first();
        $response = $this->getJson("/api/v1/spell-schools/{$school->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'code', 'name', 'description'],
            ]);
    }
}
