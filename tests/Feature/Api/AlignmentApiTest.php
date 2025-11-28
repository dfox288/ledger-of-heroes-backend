<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class AlignmentApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    #[Test]
    public function it_can_list_all_alignments(): void
    {
        Monster::factory()->create(['alignment' => 'Lawful Good']);
        Monster::factory()->create(['alignment' => 'Chaotic Evil']);
        Monster::factory()->create(['alignment' => 'True Neutral']);

        $response = $this->getJson('/api/v1/lookups/alignments');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['slug', 'name'],
                ],
            ]);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    #[Test]
    public function it_returns_distinct_alignments(): void
    {
        // Create multiple monsters with the same alignment
        Monster::factory()->count(5)->create(['alignment' => 'Lawful Good']);
        Monster::factory()->create(['alignment' => 'Neutral Evil']);

        $response = $this->getJson('/api/v1/lookups/alignments');

        $response->assertOk();

        $alignments = collect($response->json('data'))->pluck('name')->toArray();

        // Should only have each alignment once (distinct)
        $this->assertCount(2, $alignments);
        $this->assertContains('Lawful Good', $alignments);
        $this->assertContains('Neutral Evil', $alignments);
    }

    #[Test]
    public function it_returns_alignments_ordered_alphabetically(): void
    {
        Monster::factory()->create(['alignment' => 'True Neutral']);
        Monster::factory()->create(['alignment' => 'Chaotic Good']);
        Monster::factory()->create(['alignment' => 'Lawful Evil']);

        $response = $this->getJson('/api/v1/lookups/alignments');

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();

        $sorted = $names;
        sort($sorted);
        $this->assertEquals($sorted, $names, 'Alignments should be ordered alphabetically');
    }

    #[Test]
    public function it_excludes_null_and_empty_alignments(): void
    {
        Monster::factory()->create(['alignment' => 'Unaligned']);
        Monster::factory()->create(['alignment' => null]);
        Monster::factory()->create(['alignment' => '']);

        $response = $this->getJson('/api/v1/lookups/alignments');

        $response->assertOk();

        $alignments = collect($response->json('data'))->pluck('name')->toArray();

        $this->assertCount(1, $alignments);
        $this->assertContains('Unaligned', $alignments);
    }

    #[Test]
    public function it_returns_slug_and_name_for_each_alignment(): void
    {
        Monster::factory()->create(['alignment' => 'Chaotic Neutral']);

        $response = $this->getJson('/api/v1/lookups/alignments');

        $response->assertOk();

        $alignment = collect($response->json('data'))->firstWhere('name', 'Chaotic Neutral');

        $this->assertNotNull($alignment);
        $this->assertEquals('chaotic-neutral', $alignment['slug']);
        $this->assertEquals('Chaotic Neutral', $alignment['name']);
    }

    #[Test]
    public function it_handles_special_alignment_values(): void
    {
        Monster::factory()->create(['alignment' => 'Any alignment']);
        Monster::factory()->create(['alignment' => 'Unaligned']);

        $response = $this->getJson('/api/v1/lookups/alignments');

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();

        $this->assertContains('Any alignment', $names);
        $this->assertContains('Unaligned', $names);
    }

    #[Test]
    public function it_returns_empty_data_when_no_monsters_exist(): void
    {
        $response = $this->getJson('/api/v1/lookups/alignments');

        $response->assertOk()
            ->assertJsonPath('data', []);
    }
}
