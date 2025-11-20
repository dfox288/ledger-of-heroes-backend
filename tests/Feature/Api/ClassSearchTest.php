<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('search:configure-indexes');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_searches_classes_using_scout_when_available(): void
    {
        CharacterClass::factory()->create([
            'name' => 'Fighter',
            'description' => 'A master of martial combat, wielding weapons with unparalleled skill',
        ]);

        CharacterClass::factory()->create([
            'name' => 'Wizard',
            'description' => 'An arcane spellcaster harnessing the power of magic',
        ]);

        $this->artisan('scout:import', ['model' => CharacterClass::class]);
        sleep(1);

        $response = $this->getJson('/api/v1/classes?q=fighter');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'name', 'description']],
                'meta',
            ])
            ->assertJsonPath('data.0.name', 'Fighter');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_search_query_minimum_length(): void
    {
        $response = $this->getJson('/api/v1/classes?q=a');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_empty_search_query_gracefully(): void
    {
        CharacterClass::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/classes');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonCount(3, 'data');
    }
}
