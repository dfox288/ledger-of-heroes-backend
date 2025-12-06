<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-db')]
class CharacterChoiceApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_empty_choices_for_character_with_no_handlers(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/pending-choices");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'choices',
                    'summary' => [
                        'total_pending',
                        'required_pending',
                        'optional_pending',
                        'by_type',
                        'by_source',
                    ],
                ],
            ])
            ->assertJsonPath('data.choices', [])
            ->assertJsonPath('data.summary.total_pending', 0);
    }

    #[Test]
    public function it_returns_404_for_non_existent_character_on_index(): void
    {
        $response = $this->getJson('/api/v1/characters/99999/pending-choices');

        $response->assertNotFound();
    }

    #[Test]
    public function it_accepts_type_query_parameter(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/pending-choices?type=proficiency");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'choices',
                    'summary',
                ],
            ]);
    }

    #[Test]
    public function it_returns_404_for_unknown_choice_type_on_show(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/pending-choices/unknown:type:1:1:group");

        $response->assertNotFound()
            ->assertJsonStructure([
                'message',
                'choice_id',
            ]);
    }

    #[Test]
    public function it_returns_404_for_non_existent_character_on_show(): void
    {
        $response = $this->getJson('/api/v1/characters/99999/pending-choices/proficiency:class:1:1:skill_choice_1');

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_unknown_choice_type_on_resolve(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson(
            "/api/v1/characters/{$character->id}/choices/unknown:type:1:1:group",
            ['selected' => ['option1']]
        );

        $response->assertNotFound()
            ->assertJsonStructure([
                'message',
                'choice_id',
            ]);
    }

    #[Test]
    public function it_returns_404_for_unregistered_choice_type_on_resolve(): void
    {
        $character = Character::factory()->create();

        // With no handlers registered, this will return 404 for unknown type
        $response = $this->postJson(
            "/api/v1/characters/{$character->id}/choices/proficiency:class:1:1:skill",
            ['selected' => ['stealth', 'athletics']]
        );

        // 404 because no proficiency handler is registered yet
        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_unknown_choice_type_on_undo(): void
    {
        $character = Character::factory()->create();

        $response = $this->deleteJson(
            "/api/v1/characters/{$character->id}/choices/unknown:type:1:1:group"
        );

        $response->assertNotFound()
            ->assertJsonStructure([
                'message',
                'choice_id',
            ]);
    }

    #[Test]
    public function it_returns_404_for_non_existent_character_on_undo(): void
    {
        $response = $this->deleteJson('/api/v1/characters/99999/choices/proficiency:class:1:1:skill');

        $response->assertNotFound();
    }
}
