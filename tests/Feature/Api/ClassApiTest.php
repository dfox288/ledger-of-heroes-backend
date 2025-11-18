<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClassApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_class_resource_includes_all_fields()
    {
        $intAbility = $this->getAbilityScore('INT');
        $source = $this->getSource('PHB');

        $class = CharacterClass::factory()->spellcaster('INT')->create([
            'name' => 'Wizard',
            'hit_die' => 6,
            'description' => 'A scholarly magic-user',
            'primary_ability' => 'Intelligence',
        ]);

        $class->sources()->create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'source_id' => $source->id,
            'pages' => '110',
        ]);

        $resource = new \App\Http\Resources\ClassResource($class->load('spellcastingAbility', 'sources.source'));
        $data = $resource->toArray(request());

        $this->assertEquals('Wizard', $data['name']);
        $this->assertEquals(6, $data['hit_die']);
        $this->assertEquals('Intelligence', $data['primary_ability']);
        $this->assertArrayHasKey('spellcasting_ability', $data);
        $this->assertEquals('INT', $data['spellcasting_ability']['code']);
        $this->assertArrayHasKey('sources', $data);
    }
}
