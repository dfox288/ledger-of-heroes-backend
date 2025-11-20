<?php

namespace Tests\Feature\Requests;

use App\Models\Feat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatShowRequestTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_includable_relationships()
    {
        $feat = Feat::factory()->create();

        // Valid single relationship
        $response = $this->getJson("/api/v1/feats/{$feat->id}?include[]=sources");
        $response->assertStatus(200);

        // Valid nested relationship
        $response = $this->getJson("/api/v1/feats/{$feat->id}?include[]=sources.source");
        $response->assertStatus(200);

        // Multiple valid relationships
        $response = $this->getJson("/api/v1/feats/{$feat->id}?include[]=modifiers&include[]=proficiencies");
        $response->assertStatus(200);

        // Invalid relationship
        $response = $this->getJson("/api/v1/feats/{$feat->id}?include[]=invalid_relationship");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['include.0']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_selectable_fields()
    {
        $feat = Feat::factory()->create();

        // Valid single field
        $response = $this->getJson("/api/v1/feats/{$feat->id}?fields[]=name");
        $response->assertStatus(200);

        // Valid multiple fields
        $response = $this->getJson("/api/v1/feats/{$feat->id}?fields[]=id&fields[]=name&fields[]=slug");
        $response->assertStatus(200);

        // Invalid field
        $response = $this->getJson("/api/v1/feats/{$feat->id}?fields[]=invalid_field");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields.0']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_all_defined_includable_relationships()
    {
        $feat = Feat::factory()->create();

        $validRelationships = [
            'sources',
            'sources.source',
            'modifiers',
            'modifiers.abilityScore',
            'modifiers.skill',
            'proficiencies',
            'proficiencies.skill',
            'proficiencies.proficiencyType',
            'conditions',
            'prerequisites',
            'prerequisites.prerequisite',
        ];

        foreach ($validRelationships as $relationship) {
            $response = $this->getJson("/api/v1/feats/{$feat->id}?include[]={$relationship}");
            $response->assertStatus(200, "Failed for relationship: {$relationship}");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_all_defined_selectable_fields()
    {
        $feat = Feat::factory()->create();

        $validFields = [
            'id',
            'name',
            'slug',
            'description',
            'prerequisites_text',
            'created_at',
            'updated_at',
        ];

        foreach ($validFields as $field) {
            $response = $this->getJson("/api/v1/feats/{$feat->id}?fields[]={$field}");
            $response->assertStatus(200, "Failed for field: {$field}");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_works_without_include_or_fields_parameters()
    {
        $feat = Feat::factory()->create();

        // Should work without any query parameters
        $response = $this->getJson("/api/v1/feats/{$feat->id}");
        $response->assertStatus(200);
    }
}
