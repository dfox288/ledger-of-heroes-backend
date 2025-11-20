<?php

namespace Tests\Feature\Requests;

use App\Models\Background;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackgroundShowRequestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_validates_includable_relationships()
    {
        $source = $this->getSource('PHB');

        $bg = Background::factory()->create(['name' => 'Acolyte']);
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        // Valid relationships
        $validIncludes = [
            'sources',
            'sources.source',
            'traits',
            'proficiencies',
            'proficiencies.skill',
            'proficiencies.proficiencyType',
            'languages',
            'randomTables',
        ];

        foreach ($validIncludes as $include) {
            $response = $this->getJson("/api/v1/backgrounds/{$bg->id}?include[]={$include}");
            $response->assertStatus(200);
        }

        // Invalid relationship should fail validation
        $response = $this->getJson("/api/v1/backgrounds/{$bg->id}?include[]=invalid_relationship");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['include.0']);
    }

    #[Test]
    public function it_validates_selectable_fields()
    {
        $source = $this->getSource('PHB');

        $bg = Background::factory()->create(['name' => 'Acolyte']);
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        // Valid fields
        $validFields = [
            'id',
            'name',
            'slug',
            'description',
            'created_at',
            'updated_at',
        ];

        foreach ($validFields as $field) {
            $response = $this->getJson("/api/v1/backgrounds/{$bg->id}?fields[]={$field}");
            $response->assertStatus(200);
        }

        // Invalid field should fail validation
        $response = $this->getJson("/api/v1/backgrounds/{$bg->id}?fields[]=invalid_field");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields.0']);
    }

    #[Test]
    public function it_allows_multiple_includes_and_fields()
    {
        $source = $this->getSource('PHB');

        $bg = Background::factory()->create(['name' => 'Acolyte']);
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        // Multiple valid includes
        $response = $this->getJson("/api/v1/backgrounds/{$bg->id}?include[]=sources&include[]=traits&include[]=proficiencies");
        $response->assertStatus(200);

        // Multiple valid fields
        $response = $this->getJson("/api/v1/backgrounds/{$bg->id}?fields[]=id&fields[]=name&fields[]=slug");
        $response->assertStatus(200);

        // Mix of valid and invalid includes should fail
        $response = $this->getJson("/api/v1/backgrounds/{$bg->id}?include[]=sources&include[]=invalid_relationship");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['include.1']);

        // Mix of valid and invalid fields should fail
        $response = $this->getJson("/api/v1/backgrounds/{$bg->id}?fields[]=id&fields[]=invalid_field");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields.1']);
    }

    #[Test]
    public function it_works_without_include_or_fields_parameters()
    {
        $source = $this->getSource('PHB');

        $bg = Background::factory()->create(['name' => 'Acolyte']);
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        // Should work without any query parameters
        $response = $this->getJson("/api/v1/backgrounds/{$bg->id}");
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                ],
            ]);
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
