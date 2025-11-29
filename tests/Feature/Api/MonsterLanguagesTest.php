<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class MonsterLanguagesTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    #[Test]
    public function monster_includes_languages_in_response(): void
    {
        $monster = Monster::factory()->create([
            'languages' => 'Common, Elvish',
        ]);

        $response = $this->getJson("/api/v1/monsters/{$monster->id}");

        $response->assertOk();
        $response->assertJsonPath('data.languages', 'Common, Elvish');
    }

    #[Test]
    public function monster_can_have_null_languages(): void
    {
        $monster = Monster::factory()->create([
            'languages' => null,
        ]);

        $response = $this->getJson("/api/v1/monsters/{$monster->id}");

        $response->assertOk();
        $response->assertJsonPath('data.languages', null);
    }

    #[Test]
    public function monster_can_have_telepathy_in_languages(): void
    {
        $monster = Monster::factory()->create([
            'languages' => 'Deep Speech, telepathy 120 ft.',
        ]);

        $response = $this->getJson("/api/v1/monsters/{$monster->id}");

        $response->assertOk();
        $response->assertJsonPath('data.languages', 'Deep Speech, telepathy 120 ft.');
    }

    #[Test]
    public function monster_can_have_understands_but_cannot_speak(): void
    {
        $monster = Monster::factory()->create([
            'languages' => 'understands Common but can\'t speak',
        ]);

        $response = $this->getJson("/api/v1/monsters/{$monster->id}");

        $response->assertOk();
        $response->assertJsonPath('data.languages', 'understands Common but can\'t speak');
    }

    #[Test]
    public function languages_field_appears_in_list_view(): void
    {
        Monster::factory()->create([
            'languages' => 'Draconic',
        ]);

        $response = $this->getJson('/api/v1/monsters?per_page=5');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'slug',
                    'name',
                    'languages',
                ],
            ],
        ]);
    }
}
