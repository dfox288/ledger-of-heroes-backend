<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\Feat;
use App\Models\Item;
use App\Models\Monster;
use App\Models\Race;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagIntegrationTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function spell_api_includes_tags_by_default()
    {
        $spell = Spell::factory()->create(['name' => 'Test Spell']);
        $spell->attachTag('Ritual Caster');
        $spell->attachTag('Touch Spells');

        $response = $this->getJson("/api/v1/spells/{$spell->slug}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'tags' => [
                    '*' => ['id', 'name', 'slug', 'type'],
                ],
            ],
        ]);

        $tags = $response->json('data.tags');
        $this->assertCount(2, $tags);
        $tagNames = collect($tags)->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Ritual Caster', 'Touch Spells'], $tagNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function race_api_includes_tags_by_default()
    {
        $race = Race::factory()->create(['name' => 'Test Race']);
        $race->attachTag('Fey Ancestry');
        $race->attachTag('Elven');

        $response = $this->getJson("/api/v1/races/{$race->slug}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'tags' => [
                    '*' => ['id', 'name', 'slug', 'type'],
                ],
            ],
        ]);

        $tags = $response->json('data.tags');
        $this->assertCount(2, $tags);
        $tagNames = collect($tags)->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Elven', 'Fey Ancestry'], $tagNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function item_api_includes_tags_by_default()
    {
        $item = Item::factory()->create(['name' => 'Test Item']);
        $item->attachTag('Legendary');
        $item->attachTag('Artifact');

        $response = $this->getJson("/api/v1/items/{$item->slug}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'tags' => [
                    '*' => ['id', 'name', 'slug', 'type'],
                ],
            ],
        ]);

        $tags = $response->json('data.tags');
        $this->assertCount(2, $tags);
        $tagNames = collect($tags)->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Artifact', 'Legendary'], $tagNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function background_api_includes_tags_by_default()
    {
        $background = Background::factory()->create(['name' => 'Test Background']);
        $background->attachTag('Urban');
        $background->attachTag('Guild Member');

        $response = $this->getJson("/api/v1/backgrounds/{$background->slug}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'tags' => [
                    '*' => ['id', 'name', 'slug', 'type'],
                ],
            ],
        ]);

        $tags = $response->json('data.tags');
        $this->assertCount(2, $tags);
        $tagNames = collect($tags)->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Guild Member', 'Urban'], $tagNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function class_api_includes_tags_by_default()
    {
        $class = CharacterClass::factory()->create(['name' => 'Test Class']);
        $class->attachTag('Spellcaster');
        $class->attachTag('Full Caster');

        $response = $this->getJson("/api/v1/classes/{$class->slug}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'tags' => [
                    '*' => ['id', 'name', 'slug', 'type'],
                ],
            ],
        ]);

        $tags = $response->json('data.tags');
        $this->assertCount(2, $tags);
        $tagNames = collect($tags)->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Full Caster', 'Spellcaster'], $tagNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function feat_api_includes_tags_by_default()
    {
        $feat = Feat::factory()->create(['name' => 'Test Feat']);
        $feat->attachTag('Combat');
        $feat->attachTag('Martial');

        $response = $this->getJson("/api/v1/feats/{$feat->slug}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'tags' => [
                    '*' => ['id', 'name', 'slug', 'type'],
                ],
            ],
        ]);

        $tags = $response->json('data.tags');
        $this->assertCount(2, $tags);
        $tagNames = collect($tags)->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Combat', 'Martial'], $tagNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function monster_api_includes_tags_by_default()
    {
        $monster = Monster::factory()->create(['name' => 'Test Monster']);
        $monster->attachTag('Fiend');
        $monster->attachTag('Fire Immune');

        $response = $this->getJson("/api/v1/monsters/{$monster->slug}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'tags' => [
                    '*' => ['id', 'name', 'slug', 'type'],
                ],
            ],
        ]);

        $tags = $response->json('data.tags');
        $this->assertCount(2, $tags);
        $tagNames = collect($tags)->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Fiend', 'Fire Immune'], $tagNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function entities_without_tags_return_empty_array()
    {
        $spell = Spell::factory()->create(['name' => 'Untagged Spell']);

        $response = $this->getJson("/api/v1/spells/{$spell->slug}");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'tags' => [],
            ],
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function tag_resource_includes_type_field()
    {
        $spell = Spell::factory()->create(['name' => 'Test Spell']);
        $spell->attachTag('Ritual Caster', 'spell_list');

        $response = $this->getJson("/api/v1/spells/{$spell->slug}");

        $response->assertStatus(200);
        $tags = $response->json('data.tags');
        $this->assertCount(1, $tags);
        $this->assertEquals('spell_list', $tags[0]['type']);
    }
}
