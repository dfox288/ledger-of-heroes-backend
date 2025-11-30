<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\FeatResource;
use App\Models\Feat;
use App\Models\Modifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class FeatResourceTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_is_half_feat_in_response(): void
    {
        $feat = Feat::factory()->create(['name' => 'Actor']);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'ability_score',
            'value' => '1',
        ]);

        $feat->load('modifiers');

        $resource = new FeatResource($feat);
        $response = $resource->toArray(request());

        $this->assertArrayHasKey('is_half_feat', $response);
        $this->assertTrue($response['is_half_feat']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_parent_feat_slug_in_response(): void
    {
        $feat = Feat::factory()->create([
            'name' => 'Resilient (Wisdom)',
            'slug' => 'resilient-wisdom',
        ]);

        $resource = new FeatResource($feat);
        $response = $resource->toArray(request());

        $this->assertArrayHasKey('parent_feat_slug', $response);
        $this->assertEquals('resilient', $response['parent_feat_slug']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_false_is_half_feat_for_non_half_feats(): void
    {
        $feat = Feat::factory()->create(['name' => 'Great Weapon Master']);

        $feat->load('modifiers');

        $resource = new FeatResource($feat);
        $response = $resource->toArray(request());

        $this->assertArrayHasKey('is_half_feat', $response);
        $this->assertFalse($response['is_half_feat']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_parent_feat_slug_for_non_variant_feats(): void
    {
        $feat = Feat::factory()->create([
            'name' => 'Lucky',
            'slug' => 'lucky',
        ]);

        $resource = new FeatResource($feat);
        $response = $resource->toArray(request());

        $this->assertArrayHasKey('parent_feat_slug', $response);
        $this->assertNull($response['parent_feat_slug']);
    }
}
