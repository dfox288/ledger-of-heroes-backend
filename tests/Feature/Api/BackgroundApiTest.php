<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use App\Models\CharacterTrait;
use App\Models\Proficiency;
use App\Models\RandomTable;
use App\Models\Skill;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackgroundApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function can_get_all_backgrounds()
    {
        $source = $this->getSource('PHB');

        $bg1 = Background::factory()->create(['name' => 'Acolyte']);
        $bg1->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        $bg2 = Background::factory()->create(['name' => 'Charlatan']);
        $bg2->sources()->create(['source_id' => $source->id, 'pages' => '128']);

        $response = $this->getJson('/api/v1/backgrounds');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'sources' => [
                            '*' => ['code', 'name', 'pages'],
                        ],
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    #[Test]
    public function can_search_backgrounds()
    {
        $source = $this->getSource('PHB');

        $bg1 = Background::factory()->create(['name' => 'Acolyte']);
        $bg1->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        $bg2 = Background::factory()->create(['name' => 'Charlatan']);
        $bg2->sources()->create(['source_id' => $source->id, 'pages' => '128']);

        $response = $this->getJson('/api/v1/backgrounds?search=Acolyte');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Acolyte');
    }

    #[Test]
    public function can_get_single_background()
    {
        $source = $this->getSource('PHB');

        $bg = Background::factory()->create(['name' => 'Acolyte']);
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        $response = $this->getJson("/api/v1/backgrounds/{$bg->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'sources',
                ],
            ])
            ->assertJsonPath('data.name', 'Acolyte');
    }

    #[Test]
    public function it_includes_traits_in_response()
    {
        $source = $this->getSource('PHB');

        $bg = Background::factory()->create(['name' => 'Acolyte']);
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        $bg->traits()->create([
            'name' => 'Description',
            'description' => 'You have spent your life in service.',
            'category' => null,
        ]);

        $bg->traits()->create([
            'name' => 'Feature: Shelter of the Faithful',
            'description' => 'You command respect.',
            'category' => 'feature',
        ]);

        $response = $this->getJson("/api/v1/backgrounds/{$bg->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.traits')
            ->assertJsonPath('data.traits.0.name', 'Description')
            ->assertJsonPath('data.traits.1.category', 'feature');
    }

    #[Test]
    public function it_includes_proficiencies_in_response()
    {
        $source = $this->getSource('PHB');
        $skill = Skill::where('name', 'Insight')->first();

        $bg = Background::factory()->create(['name' => 'Acolyte']);
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        $bg->proficiencies()->create([
            'proficiency_name' => 'Insight',
            'proficiency_type' => 'skill',
            'skill_id' => $skill->id,
        ]);

        $response = $this->getJson("/api/v1/backgrounds/{$bg->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.proficiencies')
            ->assertJsonPath('data.proficiencies.0.proficiency_name', 'Insight')
            ->assertJsonPath('data.proficiencies.0.proficiency_type', 'skill');
    }

    #[Test]
    public function it_includes_sources_as_resource()
    {
        $source = $this->getSource('PHB');

        $bg = Background::factory()->create(['name' => 'Acolyte']);
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        $response = $this->getJson("/api/v1/backgrounds/{$bg->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'sources' => [
                        '*' => ['code', 'name', 'pages'],
                    ],
                ],
            ])
            ->assertJsonPath('data.sources.0.code', 'PHB')
            ->assertJsonPath('data.sources.0.pages', '127');
    }

    #[Test]
    public function background_traits_include_random_tables()
    {
        $source = $this->getSource('PHB');

        $bg = Background::factory()->create(['name' => 'Acolyte']);
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        $trait = $bg->traits()->create([
            'name' => 'Suggested Characteristics',
            'description' => 'Character tables...',
            'category' => 'characteristics',
        ]);

        $table = $trait->randomTables()->create([
            'table_name' => 'Personality Trait',
            'dice_type' => '1d8',
        ]);

        $table->entries()->create([
            'roll_min' => 1,
            'roll_max' => 1,
            'result_text' => 'I idolize a hero of my faith',
            'sort_order' => 0,
        ]);

        $response = $this->getJson("/api/v1/backgrounds/{$bg->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.traits.0.random_tables.0.table_name', 'Personality Trait')
            ->assertJsonPath('data.traits.0.random_tables.0.dice_type', '1d8')
            ->assertJsonCount(1, 'data.traits.0.random_tables.0.entries');
    }

    #[Test]
    public function background_proficiencies_include_skill_resource()
    {
        $source = $this->getSource('PHB');
        $skill = Skill::where('name', 'Insight')->first();

        $bg = Background::factory()->create(['name' => 'Acolyte']);
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        $bg->proficiencies()->create([
            'proficiency_name' => 'Insight',
            'proficiency_type' => 'skill',
            'skill_id' => $skill->id,
        ]);

        $response = $this->getJson("/api/v1/backgrounds/{$bg->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'proficiencies' => [
                        '*' => [
                            'proficiency_name',
                            'proficiency_type',
                            'skill' => ['id', 'name', 'ability_score'],
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function can_paginate_backgrounds()
    {
        $source = $this->getSource('PHB');

        for ($i = 1; $i <= 20; $i++) {
            $bg = Background::factory()->create(['name' => "Background {$i}"]);
            $bg->sources()->create(['source_id' => $source->id, 'pages' => '100']);
        }

        $response = $this->getJson('/api/v1/backgrounds?per_page=5');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 20);
    }

    #[Test]
    public function can_sort_backgrounds()
    {
        $source = $this->getSource('PHB');

        $bg1 = Background::factory()->create(['name' => 'Charlatan']);
        $bg1->sources()->create(['source_id' => $source->id, 'pages' => '128']);

        $bg2 = Background::factory()->create(['name' => 'Acolyte']);
        $bg2->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        $response = $this->getJson('/api/v1/backgrounds?sort_by=name&sort_direction=asc');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Acolyte')
            ->assertJsonPath('data.1.name', 'Charlatan');
    }

    /**
     * Helper to get or create a source
     */
    protected function getSource(string $code): Source
    {
        return Source::where('code', $code)->first()
            ?? Source::factory()->create(['code' => $code]);
    }
}
