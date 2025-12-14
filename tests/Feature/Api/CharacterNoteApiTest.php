<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterNote;
use App\Support\NoteCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterNoteApiTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // Index Tests
    // =====================

    #[Test]
    public function it_lists_character_notes_grouped_by_category(): void
    {
        $character = Character::factory()->create();

        CharacterNote::factory()->for($character)->personalityTrait()->create();
        CharacterNote::factory()->for($character)->ideal()->create();
        CharacterNote::factory()->for($character)->bond()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/notes");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'personality_trait',
                    'ideal',
                    'bond',
                ],
            ]);
    }

    #[Test]
    public function it_returns_empty_data_when_no_notes(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/notes");

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    #[Test]
    public function it_orders_notes_by_sort_order_within_category(): void
    {
        $character = Character::factory()->create();

        CharacterNote::factory()->for($character)->personalityTrait()->sortOrder(2)->create(['content' => 'Second']);
        CharacterNote::factory()->for($character)->personalityTrait()->sortOrder(0)->create(['content' => 'First']);
        CharacterNote::factory()->for($character)->personalityTrait()->sortOrder(1)->create(['content' => 'Middle']);

        $response = $this->getJson("/api/v1/characters/{$character->id}/notes");

        $response->assertOk();

        $traits = $response->json('data.personality_trait');
        $this->assertEquals('First', $traits[0]['content']);
        $this->assertEquals('Middle', $traits[1]['content']);
        $this->assertEquals('Second', $traits[2]['content']);
    }

    // =====================
    // Store Tests
    // =====================

    #[Test]
    public function it_creates_a_personality_trait_note(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/notes", [
            'category' => 'personality_trait',
            'content' => 'I am slow to trust, but fiercely loyal.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.category', 'personality_trait')
            ->assertJsonPath('data.content', 'I am slow to trust, but fiercely loyal.')
            ->assertJsonPath('data.title', null);

        $this->assertDatabaseHas('character_notes', [
            'character_id' => $character->id,
            'category' => 'personality_trait',
            'content' => 'I am slow to trust, but fiercely loyal.',
        ]);
    }

    #[Test]
    public function it_creates_multiple_personality_traits(): void
    {
        $character = Character::factory()->create();

        $this->postJson("/api/v1/characters/{$character->id}/notes", [
            'category' => 'personality_trait',
            'content' => 'First trait',
        ])->assertCreated();

        $this->postJson("/api/v1/characters/{$character->id}/notes", [
            'category' => 'personality_trait',
            'content' => 'Second trait',
        ])->assertCreated();

        $this->assertDatabaseCount('character_notes', 2);

        // Check sort_order auto-increment
        $notes = $character->notes()->where('category', 'personality_trait')->orderBy('sort_order')->get();
        $this->assertEquals(0, $notes[0]->sort_order);
        $this->assertEquals(1, $notes[1]->sort_order);
    }

    #[Test]
    public function it_creates_a_custom_note_with_title(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/notes", [
            'category' => 'custom',
            'title' => 'Session 3 Notes',
            'content' => 'Met the mysterious stranger in the tavern.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.category', 'custom')
            ->assertJsonPath('data.title', 'Session 3 Notes')
            ->assertJsonPath('data.content', 'Met the mysterious stranger in the tavern.');
    }

    #[Test]
    public function it_allows_custom_notes_without_title(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/notes", [
            'category' => 'custom',
            'content' => 'Some content without title',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.category', 'custom')
            ->assertJsonPath('data.title', null);
    }

    #[Test]
    public function it_requires_title_for_backstory_notes(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/notes", [
            'category' => 'backstory',
            'content' => 'Some backstory without title',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    #[Test]
    public function it_creates_backstory_with_title(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/notes", [
            'category' => 'backstory',
            'title' => 'Early Life',
            'content' => 'Born in a small village near the forest.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.category', 'backstory')
            ->assertJsonPath('data.title', 'Early Life');
    }

    #[Test]
    public function it_validates_category_is_required(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/notes", [
            'content' => 'Some content',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    }

    #[Test]
    public function it_accepts_any_category_string(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/notes", [
            'category' => 'session_notes',
            'content' => 'Notes from session 1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.category', 'session_notes')
            ->assertJsonPath('data.category_label', 'Session Notes');
    }

    #[Test]
    public function it_validates_category_max_length(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/notes", [
            'category' => str_repeat('a', 51),
            'content' => 'Some content',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    }

    #[Test]
    public function it_validates_content_is_required(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/notes", [
            'category' => 'personality_trait',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    #[Test]
    public function it_validates_content_max_length(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/notes", [
            'category' => 'personality_trait',
            'content' => str_repeat('a', 10001),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    // =====================
    // Show Tests
    // =====================

    #[Test]
    public function it_shows_a_single_note(): void
    {
        $character = Character::factory()->create();
        $note = CharacterNote::factory()->for($character)->personalityTrait()->create([
            'content' => 'Test trait',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/notes/{$note->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $note->id)
            ->assertJsonPath('data.content', 'Test trait')
            ->assertJsonPath('data.category', 'personality_trait');
    }

    #[Test]
    public function it_returns_404_for_note_belonging_to_different_character(): void
    {
        $character1 = Character::factory()->create();
        $character2 = Character::factory()->create();
        $note = CharacterNote::factory()->for($character2)->personalityTrait()->create();

        $response = $this->getJson("/api/v1/characters/{$character1->id}/notes/{$note->id}");

        $response->assertNotFound();
    }

    // =====================
    // Update Tests
    // =====================

    #[Test]
    public function it_updates_note_content(): void
    {
        $character = Character::factory()->create();
        $note = CharacterNote::factory()->for($character)->personalityTrait()->create([
            'content' => 'Original content',
        ]);

        $response = $this->putJson("/api/v1/characters/{$character->id}/notes/{$note->id}", [
            'content' => 'Updated content',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.content', 'Updated content');

        $this->assertDatabaseHas('character_notes', [
            'id' => $note->id,
            'content' => 'Updated content',
        ]);
    }

    #[Test]
    public function it_updates_note_title(): void
    {
        $character = Character::factory()->create();
        $note = CharacterNote::factory()->for($character)->custom()->create([
            'title' => 'Original Title',
        ]);

        $response = $this->putJson("/api/v1/characters/{$character->id}/notes/{$note->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated Title');
    }

    #[Test]
    public function it_updates_note_sort_order(): void
    {
        $character = Character::factory()->create();
        $note = CharacterNote::factory()->for($character)->personalityTrait()->create([
            'sort_order' => 0,
        ]);

        $response = $this->putJson("/api/v1/characters/{$character->id}/notes/{$note->id}", [
            'sort_order' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.sort_order', 5);
    }

    #[Test]
    public function it_prevents_removing_title_from_backstory_note(): void
    {
        $character = Character::factory()->create();
        $note = CharacterNote::factory()->for($character)->backstory()->create([
            'title' => 'Required Title',
        ]);

        $response = $this->putJson("/api/v1/characters/{$character->id}/notes/{$note->id}", [
            'title' => null,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    #[Test]
    public function it_allows_removing_title_from_custom_note(): void
    {
        $character = Character::factory()->create();
        $note = CharacterNote::factory()->for($character)->custom()->create([
            'title' => 'Optional Title',
        ]);

        $response = $this->putJson("/api/v1/characters/{$character->id}/notes/{$note->id}", [
            'title' => null,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', null);
    }

    #[Test]
    public function it_returns_404_when_updating_note_belonging_to_different_character(): void
    {
        $character1 = Character::factory()->create();
        $character2 = Character::factory()->create();
        $note = CharacterNote::factory()->for($character2)->personalityTrait()->create();

        $response = $this->putJson("/api/v1/characters/{$character1->id}/notes/{$note->id}", [
            'content' => 'Updated',
        ]);

        $response->assertNotFound();
    }

    #[Test]
    public function it_accepts_patch_for_partial_updates(): void
    {
        $character = Character::factory()->create();
        $note = CharacterNote::factory()->for($character)->custom()->create([
            'title' => 'Original Title',
            'content' => 'Original content',
        ]);

        // PATCH with only title - content should remain unchanged
        $response = $this->patchJson("/api/v1/characters/{$character->id}/notes/{$note->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.content', 'Original content');

        $this->assertDatabaseHas('character_notes', [
            'id' => $note->id,
            'title' => 'Updated Title',
            'content' => 'Original content',
        ]);
    }

    // =====================
    // Delete Tests
    // =====================

    #[Test]
    public function it_deletes_a_note(): void
    {
        $character = Character::factory()->create();
        $note = CharacterNote::factory()->for($character)->personalityTrait()->create();

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/notes/{$note->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('character_notes', ['id' => $note->id]);
    }

    #[Test]
    public function it_returns_404_when_deleting_note_belonging_to_different_character(): void
    {
        $character1 = Character::factory()->create();
        $character2 = Character::factory()->create();
        $note = CharacterNote::factory()->for($character2)->personalityTrait()->create();

        $response = $this->deleteJson("/api/v1/characters/{$character1->id}/notes/{$note->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('character_notes', ['id' => $note->id]);
    }

    // =====================
    // Integration Tests
    // =====================

    #[Test]
    public function it_deletes_notes_when_character_is_deleted(): void
    {
        $character = Character::factory()->create();
        CharacterNote::factory()->for($character)->personalityTrait()->create();
        CharacterNote::factory()->for($character)->ideal()->create();

        $this->assertDatabaseCount('character_notes', 2);

        $character->delete();

        $this->assertDatabaseCount('character_notes', 0);
    }

    #[Test]
    public function it_supports_all_default_category_types(): void
    {
        $character = Character::factory()->create();

        foreach (NoteCategories::DEFAULTS as $category) {
            $payload = [
                'category' => $category,
                'content' => "Content for {$category}",
            ];

            // Add title for categories that require it
            if (NoteCategories::requiresTitle($category)) {
                $payload['title'] = "Title for {$category}";
            }

            $response = $this->postJson("/api/v1/characters/{$character->id}/notes", $payload);

            $response->assertCreated();
        }

        $this->assertDatabaseCount('character_notes', count(NoteCategories::DEFAULTS));
    }

    #[Test]
    public function it_supports_user_created_categories(): void
    {
        $character = Character::factory()->create();

        $customCategories = ['session_notes', 'npcs', 'quest_log', 'My Custom Category'];

        foreach ($customCategories as $category) {
            $response = $this->postJson("/api/v1/characters/{$character->id}/notes", [
                'category' => $category,
                'content' => "Content for {$category}",
            ]);

            $response->assertCreated()
                ->assertJsonPath('data.category', $category);
        }

        $this->assertDatabaseCount('character_notes', count($customCategories));
    }

    #[Test]
    public function it_groups_user_created_categories_in_index(): void
    {
        $character = Character::factory()->create();

        CharacterNote::factory()->for($character)->create([
            'category' => 'session_notes',
            'content' => 'Session 1 notes',
        ]);
        CharacterNote::factory()->for($character)->create([
            'category' => 'session_notes',
            'content' => 'Session 2 notes',
        ]);
        CharacterNote::factory()->for($character)->create([
            'category' => 'npcs',
            'content' => 'NPC notes',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/notes");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'session_notes',
                    'npcs',
                ],
            ]);

        $this->assertCount(2, $response->json('data.session_notes'));
        $this->assertCount(1, $response->json('data.npcs'));
    }
}
