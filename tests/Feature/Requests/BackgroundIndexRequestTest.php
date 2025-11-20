<?php

namespace Tests\Feature\Requests;

use App\Models\Background;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackgroundIndexRequestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_validates_grants_proficiency_as_string()
    {
        $source = $this->getSource('PHB');

        // Create a background to test against
        $bg = Background::factory()->create(['name' => 'Acolyte']);
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        // Valid string
        $response = $this->getJson('/api/v1/backgrounds?grants_proficiency=longsword');
        $response->assertStatus(200);

        // String too long (max 255)
        $response = $this->getJson('/api/v1/backgrounds?grants_proficiency='.str_repeat('a', 256));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['grants_proficiency']);
    }

    #[Test]
    public function it_validates_grants_skill_as_string()
    {
        $source = $this->getSource('PHB');

        // Create a background to test against
        $bg = Background::factory()->create(['name' => 'Acolyte']);
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        // Valid string
        $response = $this->getJson('/api/v1/backgrounds?grants_skill=insight');
        $response->assertStatus(200);

        // String too long (max 255)
        $response = $this->getJson('/api/v1/backgrounds?grants_skill='.str_repeat('a', 256));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['grants_skill']);
    }

    #[Test]
    public function it_validates_speaks_language_as_string()
    {
        $source = $this->getSource('PHB');

        // Create a background to test against
        $bg = Background::factory()->create(['name' => 'Acolyte']);
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        // Valid string
        $response = $this->getJson('/api/v1/backgrounds?speaks_language=elvish');
        $response->assertStatus(200);

        // String too long (max 255)
        $response = $this->getJson('/api/v1/backgrounds?speaks_language='.str_repeat('a', 256));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['speaks_language']);
    }

    #[Test]
    public function it_validates_language_choice_count_range()
    {
        $source = $this->getSource('PHB');

        // Create a background to test against
        $bg = Background::factory()->create(['name' => 'Acolyte']);
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        // Valid count within range (0-10)
        $response = $this->getJson('/api/v1/backgrounds?language_choice_count=1');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/backgrounds?language_choice_count=0');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/backgrounds?language_choice_count=10');
        $response->assertStatus(200);

        // Negative count should fail
        $response = $this->getJson('/api/v1/backgrounds?language_choice_count=-1');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language_choice_count']);

        // Count > 10 should fail
        $response = $this->getJson('/api/v1/backgrounds?language_choice_count=11');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language_choice_count']);

        // Non-integer should fail
        $response = $this->getJson('/api/v1/backgrounds?language_choice_count=abc');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language_choice_count']);
    }

    #[Test]
    public function it_validates_grants_languages_boolean()
    {
        $source = $this->getSource('PHB');

        // Create a background to test against
        $bg = Background::factory()->create(['name' => 'Acolyte']);
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        // Valid boolean representations
        $response = $this->getJson('/api/v1/backgrounds?grants_languages=1');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/backgrounds?grants_languages=0');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/backgrounds?grants_languages=true');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/backgrounds?grants_languages=false');
        $response->assertStatus(200);

        // Invalid non-boolean values should fail
        $response = $this->getJson('/api/v1/backgrounds?grants_languages=maybe');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['grants_languages']);
    }

    #[Test]
    public function it_whitelists_sortable_columns()
    {
        $source = $this->getSource('PHB');

        // Create backgrounds to test against
        $bg1 = Background::factory()->create(['name' => 'Acolyte']);
        $bg1->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        $bg2 = Background::factory()->create(['name' => 'Charlatan']);
        $bg2->sources()->create(['source_id' => $source->id, 'pages' => '128']);

        // Valid sortable columns
        $response = $this->getJson('/api/v1/backgrounds?sort_by=name');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/backgrounds?sort_by=created_at');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/backgrounds?sort_by=updated_at');
        $response->assertStatus(200);

        // Invalid column should fail validation
        $response = $this->getJson('/api/v1/backgrounds?sort_by=invalid_column');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sort_by']);
    }

    #[Test]
    public function it_validates_pagination_parameters()
    {
        $source = $this->getSource('PHB');

        // Create a background to test against
        $bg = Background::factory()->create(['name' => 'Acolyte']);
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        // Valid pagination
        $response = $this->getJson('/api/v1/backgrounds?per_page=25&page=1');
        $response->assertStatus(200);

        // per_page must be at least 1
        $response = $this->getJson('/api/v1/backgrounds?per_page=0');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);

        // per_page must not exceed 100
        $response = $this->getJson('/api/v1/backgrounds?per_page=101');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);

        // page must be at least 1
        $response = $this->getJson('/api/v1/backgrounds?page=0');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    }

    #[Test]
    public function it_validates_search_parameter()
    {
        $source = $this->getSource('PHB');

        // Create a background to test against
        $bg = Background::factory()->create(['name' => 'Acolyte']);
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        // Valid search string
        $response = $this->getJson('/api/v1/backgrounds?search=Acolyte');
        $response->assertStatus(200);

        // Search string too long (max 255)
        $longString = str_repeat('a', 256);
        $response = $this->getJson('/api/v1/backgrounds?search='.$longString);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['search']);
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
