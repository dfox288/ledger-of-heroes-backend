<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\TagResource;
use Spatie\Tags\Tag;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class TagResourceTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_serializes_tag_with_basic_attributes()
    {
        $tag = new Tag([
            'id' => 1,
            'name' => ['en' => 'Touch Spells'],
            'slug' => ['en' => 'touch-spells'],
            'type' => null,
            'order_column' => 0,
        ]);
        $tag->id = 1;

        $resource = new TagResource($tag);
        $array = $resource->toArray(request());

        $this->assertEquals(1, $array['id']);
        $this->assertEquals('Touch Spells', $array['name']);
        $this->assertEquals('touch-spells', $array['slug']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_serializes_tag_with_type()
    {
        $tag = new Tag([
            'id' => 2,
            'name' => ['en' => 'Ritual Caster'],
            'slug' => ['en' => 'ritual-caster'],
            'type' => 'spell_list',
            'order_column' => 0,
        ]);
        $tag->id = 2;

        $resource = new TagResource($tag);
        $array = $resource->toArray(request());

        $this->assertEquals(2, $array['id']);
        $this->assertEquals('Ritual Caster', $array['name']);
        $this->assertEquals('ritual-caster', $array['slug']);
        $this->assertEquals('spell_list', $array['type']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_tag_with_no_type()
    {
        $tag = new Tag([
            'id' => 3,
            'name' => ['en' => 'Mark of Finding'],
            'slug' => ['en' => 'mark-of-finding'],
            'type' => null,
            'order_column' => 0,
        ]);
        $tag->id = 3;

        $resource = new TagResource($tag);
        $array = $resource->toArray(request());

        $this->assertNull($array['type']);
    }
}
