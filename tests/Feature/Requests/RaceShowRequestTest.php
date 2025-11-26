<?php

namespace Tests\Feature\Requests;

use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class RaceShowRequestTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    #[Test]
    public function it_validates_includable_relationships()
    {
        $race = Race::factory()->create();

        $validRelationships = [
            'sources',
            'sources.source',
            'traits',
            'proficiencies',
            'proficiencies.skill',
            'proficiencies.proficiencyType',
            'modifiers',
            'languages',
        ];

        foreach ($validRelationships as $relationship) {
            $response = $this->getJson("/api/v1/races/{$race->id}?include[]={$relationship}");
            $response->assertOk();
        }

        // Invalid relationship
        $response = $this->getJson("/api/v1/races/{$race->id}?include[]=invalid_relationship");
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('include.0');
    }

    #[Test]
    public function it_validates_selectable_fields()
    {
        $race = Race::factory()->create();

        $validFields = [
            'id',
            'name',
            'slug',
            'description',
            'size',
            'speed',
            'created_at',
            'updated_at',
        ];

        foreach ($validFields as $field) {
            $response = $this->getJson("/api/v1/races/{$race->id}?fields[]={$field}");
            $response->assertOk();
        }

        // Invalid field
        $response = $this->getJson("/api/v1/races/{$race->id}?fields[]=invalid_field");
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('fields.0');
    }

    #[Test]
    public function it_loads_specified_relationships_only()
    {
        $race = Race::factory()->create();
        $race->traits()->create([
            'name' => 'Test Trait',
            'description' => 'Test Description',
        ]);

        // Request with specific include
        $response = $this->getJson("/api/v1/races/{$race->id}?include[]=traits");
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'traits',
            ],
        ]);
    }

    #[Test]
    public function it_accepts_multiple_includes()
    {
        $race = Race::factory()->create();

        $response = $this->getJson("/api/v1/races/{$race->id}?include[]=traits&include[]=proficiencies&include[]=modifiers");
        $response->assertOk();
    }

    #[Test]
    public function it_accepts_multiple_field_selections()
    {
        $race = Race::factory()->create();

        $response = $this->getJson("/api/v1/races/{$race->id}?fields[]=id&fields[]=name&fields[]=slug");
        $response->assertOk();
    }
}
