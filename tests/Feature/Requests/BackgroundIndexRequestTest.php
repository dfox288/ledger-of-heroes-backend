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
    public function it_validates_q_parameter()
    {
        $source = $this->getSource('PHB');

        // Create a background to test against
        $bg = Background::factory()->create(['name' => 'Acolyte']);
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        // Valid search string
        $response = $this->getJson('/api/v1/backgrounds?q=Acolyte');
        $response->assertStatus(200);

        // Search string too short (min 2)
        $response = $this->getJson('/api/v1/backgrounds?q=a');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);

        // Search string too long (max 255)
        $longString = str_repeat('a', 256);
        $response = $this->getJson('/api/v1/backgrounds?q='.$longString);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[Test]
    public function it_validates_filter_parameter()
    {
        $source = $this->getSource('PHB');

        // Create a background to test against
        $bg = Background::factory()->create(['name' => 'Acolyte']);
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        // Valid filter string
        $response = $this->getJson('/api/v1/backgrounds?filter=name="Acolyte"');
        $response->assertStatus(200);

        // Filter string too long (max 1000)
        $longString = str_repeat('a', 1001);
        $response = $this->getJson('/api/v1/backgrounds?filter='.$longString);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['filter']);
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
