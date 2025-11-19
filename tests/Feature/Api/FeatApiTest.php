<?php

namespace Tests\Feature\Api;

use App\Models\Feat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function can_get_all_feats()
    {
        Feat::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/feats');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'slug', 'name', 'prerequisites', 'description'],
            ],
        ]);
    }

    #[Test]
    public function can_search_feats()
    {
        Feat::factory()->create(['name' => 'Alert']);
        Feat::factory()->create(['name' => 'Actor']);
        Feat::factory()->create(['name' => 'Grappler']);

        $response = $this->getJson('/api/v1/feats?search=Alert');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Alert');
    }

    #[Test]
    public function can_get_single_feat_by_id()
    {
        $feat = Feat::factory()->create(['name' => 'Alert']);

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Alert');
    }

    #[Test]
    public function feat_includes_sources_in_response()
    {
        $feat = Feat::factory()->withSources()->create();

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'sources' => [
                    '*' => ['code', 'name', 'pages'],
                ],
            ],
        ]);
    }

    #[Test]
    public function feat_includes_modifiers_in_response()
    {
        $feat = Feat::factory()->withModifiers()->create();

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'modifiers' => [
                    '*' => ['id', 'modifier_category'],
                ],
            ],
        ]);
    }

    #[Test]
    public function feat_includes_proficiencies_in_response()
    {
        $feat = Feat::factory()->withProficiencies()->create();

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'proficiencies' => [
                    '*' => ['proficiency_type', 'proficiency_name', 'is_choice', 'quantity'],
                ],
            ],
        ]);
    }

    #[Test]
    public function feat_includes_conditions_in_response()
    {
        $feat = Feat::factory()->withConditions()->create();

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'conditions' => [
                    '*' => ['effect_type', 'description'],
                ],
            ],
        ]);
    }

    #[Test]
    public function can_paginate_feats()
    {
        Feat::factory()->count(20)->create();

        $response = $this->getJson('/api/v1/feats?per_page=10');

        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.per_page', 10);
    }

    #[Test]
    public function can_sort_feats()
    {
        Feat::factory()->create(['name' => 'Zebra Feat']);
        Feat::factory()->create(['name' => 'Alpha Feat']);

        $response = $this->getJson('/api/v1/feats?sort_by=name&sort_direction=asc');

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Alpha Feat');
    }
}
