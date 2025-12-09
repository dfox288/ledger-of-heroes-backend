<?php

namespace Tests\Feature\Requests;

use App\Models\Background;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class BackgroundIndexRequestTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    #[Test]
    public function it_whitelists_sortable_columns()
    {
        $source = $this->getSource('PHB');

        // Create backgrounds to test against (use factory-generated unique names)
        $bg1 = Background::factory()->create();
        $bg1->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        $bg2 = Background::factory()->create();
        $bg2->sources()->create(['source_id' => $source->id, 'pages' => '128']);

        // Valid sortable columns (no timestamps - models use BaseModel with $timestamps = false)
        $response = $this->getJson('/api/v1/backgrounds?sort_by=name');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/backgrounds?sort_by=slug');
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

        // Create a background to test against (use factory-generated unique name)
        $bg = Background::factory()->create();
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        // Valid pagination
        $response = $this->getJson('/api/v1/backgrounds?per_page=25&page=1');
        $response->assertStatus(200);

        // per_page must be at least 1
        $response = $this->getJson('/api/v1/backgrounds?per_page=0');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);

        // per_page must not exceed 200
        $response = $this->getJson('/api/v1/backgrounds?per_page=201');
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

        // Create a background to test against (use factory-generated unique name)
        $bg = Background::factory()->create();
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        // Valid search string (use created background's name)
        $response = $this->getJson('/api/v1/backgrounds?q='.urlencode($bg->name));
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

        // Create a background to test against (use factory-generated unique name)
        $bg = Background::factory()->create();
        $bg->sources()->create(['source_id' => $source->id, 'pages' => '127']);

        // Valid filter string (use created background's name)
        $response = $this->getJson('/api/v1/backgrounds?filter=name="'.urlencode($bg->name).'"');
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
