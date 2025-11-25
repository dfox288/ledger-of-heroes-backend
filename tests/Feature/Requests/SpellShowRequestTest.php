<?php

namespace Tests\Feature\Requests;

use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpellShowRequestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_validates_includable_relationships()
    {
        $spell = Spell::factory()->create();

        // Valid: spellSchool
        $response = $this->getJson("/api/v1/spells/{$spell->id}?include[]=spellSchool");
        $response->assertStatus(200);

        // Valid: sources
        $response = $this->getJson("/api/v1/spells/{$spell->id}?include[]=sources");
        $response->assertStatus(200);

        // Valid: sources.source
        $response = $this->getJson("/api/v1/spells/{$spell->id}?include[]=sources.source");
        $response->assertStatus(200);

        // Valid: effects
        $response = $this->getJson("/api/v1/spells/{$spell->id}?include[]=effects");
        $response->assertStatus(200);

        // Valid: effects.damageType
        $response = $this->getJson("/api/v1/spells/{$spell->id}?include[]=effects.damageType");
        $response->assertStatus(200);

        // Valid: classes
        $response = $this->getJson("/api/v1/spells/{$spell->id}?include[]=classes");
        $response->assertStatus(200);

        // Invalid: invalid_relation
        $response = $this->getJson("/api/v1/spells/{$spell->id}?include[]=invalid_relation");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['include.0']);
    }

    #[Test]
    public function it_validates_selectable_fields()
    {
        $spell = Spell::factory()->create();

        // Valid: name
        $response = $this->getJson("/api/v1/spells/{$spell->id}?fields[]=name");
        $response->assertStatus(200);

        // Valid: level
        $response = $this->getJson("/api/v1/spells/{$spell->id}?fields[]=level");
        $response->assertStatus(200);

        // Valid: slug
        $response = $this->getJson("/api/v1/spells/{$spell->id}?fields[]=slug");
        $response->assertStatus(200);

        // Valid: description
        $response = $this->getJson("/api/v1/spells/{$spell->id}?fields[]=description");
        $response->assertStatus(200);

        // Valid: casting_time
        $response = $this->getJson("/api/v1/spells/{$spell->id}?fields[]=casting_time");
        $response->assertStatus(200);

        // Valid: range
        $response = $this->getJson("/api/v1/spells/{$spell->id}?fields[]=range");
        $response->assertStatus(200);

        // Valid: components
        $response = $this->getJson("/api/v1/spells/{$spell->id}?fields[]=components");
        $response->assertStatus(200);

        // Valid: duration
        $response = $this->getJson("/api/v1/spells/{$spell->id}?fields[]=duration");
        $response->assertStatus(200);

        // Valid: needs_concentration
        $response = $this->getJson("/api/v1/spells/{$spell->id}?fields[]=needs_concentration");
        $response->assertStatus(200);

        // Valid: is_ritual
        $response = $this->getJson("/api/v1/spells/{$spell->id}?fields[]=is_ritual");
        $response->assertStatus(200);

        // Valid: created_at
        $response = $this->getJson("/api/v1/spells/{$spell->id}?fields[]=created_at");
        $response->assertStatus(200);

        // Valid: updated_at
        $response = $this->getJson("/api/v1/spells/{$spell->id}?fields[]=updated_at");
        $response->assertStatus(200);

        // Invalid: password
        $response = $this->getJson("/api/v1/spells/{$spell->id}?fields[]=password");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields.0']);
    }

    #[Test]
    public function it_allows_multiple_includes()
    {
        $spell = Spell::factory()->create();

        // Valid: multiple relationships
        $response = $this->getJson("/api/v1/spells/{$spell->id}?include[]=spellSchool&include[]=sources&include[]=effects");
        $response->assertStatus(200);
    }

    #[Test]
    public function it_allows_multiple_fields()
    {
        $spell = Spell::factory()->create();

        // Valid: multiple fields
        $response = $this->getJson("/api/v1/spells/{$spell->id}?fields[]=name&fields[]=level&fields[]=description");
        $response->assertStatus(200);
    }

    #[Test]
    public function it_validates_include_must_be_array()
    {
        $spell = Spell::factory()->create();

        // Invalid: string instead of array
        $response = $this->getJson("/api/v1/spells/{$spell->id}?include=spellSchool");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['include']);
    }

    #[Test]
    public function it_validates_fields_must_be_array()
    {
        $spell = Spell::factory()->create();

        // Invalid: string instead of array
        $response = $this->getJson("/api/v1/spells/{$spell->id}?fields=name");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields']);
    }
}
