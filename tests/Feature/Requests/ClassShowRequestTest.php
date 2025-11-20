<?php

namespace Tests\Feature\Requests;

use App\Models\CharacterClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassShowRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_includable_relationships(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Wizard']);

        // Valid includes
        $validIncludes = [
            'sources',
            'sources.source',
            'features',
            'proficiencies',
            'proficiencies.skill',
            'proficiencies.proficiencyType',
            'levelProgression',
            'counters',
            'spellcastingAbility',
        ];

        foreach ($validIncludes as $include) {
            $response = $this->getJson("/api/v1/classes/{$class->id}?include[]={$include}");
            $response->assertStatus(200);
        }

        // Invalid include
        $response = $this->getJson("/api/v1/classes/{$class->id}?include[]=invalid_relationship");
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('include.0');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_selectable_fields(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Cleric']);

        // Valid fields
        $validFields = ['id', 'name', 'slug', 'description', 'hit_die', 'created_at', 'updated_at'];

        foreach ($validFields as $field) {
            $response = $this->getJson("/api/v1/classes/{$class->id}?fields[]={$field}");
            $response->assertStatus(200);
        }

        // Invalid field
        $response = $this->getJson("/api/v1/classes/{$class->id}?fields[]=invalid_field");
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('fields.0');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_multiple_includes(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Bard']);

        $response = $this->getJson(
            "/api/v1/classes/{$class->id}?include[]=sources&include[]=features&include[]=counters"
        );

        $response->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_multiple_fields(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Sorcerer']);

        $response = $this->getJson(
            "/api/v1/classes/{$class->id}?fields[]=id&fields[]=name&fields[]=slug"
        );

        $response->assertStatus(200);
    }
}
