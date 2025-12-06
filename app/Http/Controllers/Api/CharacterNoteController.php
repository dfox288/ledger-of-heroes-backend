<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CharacterNote\CharacterNoteStoreRequest;
use App\Http\Requests\CharacterNote\CharacterNoteUpdateRequest;
use App\Http\Resources\CharacterNoteResource;
use App\Http\Resources\CharacterNotesGroupedResource;
use App\Models\Character;
use App\Models\CharacterNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CharacterNoteController extends Controller
{
    /**
     * List all notes for a character, grouped by category
     *
     * Returns notes organized by category for easy frontend consumption.
     * Categories match the D&D 5e character sheet sections.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/notes
     * ```
     *
     * **Note Categories (NoteCategory enum):**
     * | Value | Label | Description | Requires Title |
     * |-------|-------|-------------|----------------|
     * | `personality_trait` | Personality Trait | Character's personality aspects | No |
     * | `ideal` | Ideal | What the character believes in | No |
     * | `bond` | Bond | Connections to people, places, or things | No |
     * | `flaw` | Flaw | Character weaknesses or vices | No |
     * | `backstory` | Backstory | Character history and background | Yes |
     * | `custom` | Custom Note | Free-form notes (session logs, etc.) | Yes |
     *
     * **Response Structure:**
     * Notes are grouped by category. Empty categories are omitted.
     * ```json
     * {
     *   "data": {
     *     "personality_trait": [...],
     *     "ideal": [...],
     *     "custom": [...]
     *   }
     * }
     * ```
     */
    public function index(Character $character): CharacterNotesGroupedResource
    {
        $notes = $character->notes()->get();

        return new CharacterNotesGroupedResource($notes);
    }

    /**
     * Add a new note to a character
     *
     * Creates a note in one of the 6 categories. Backstory and custom notes require a title.
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/notes
     *
     * # Add a personality trait
     * {"category": "personality_trait", "content": "I am slow to trust, but fiercely loyal."}
     *
     * # Add an ideal
     * {"category": "ideal", "content": "Freedom. Everyone should be free to pursue their own destiny."}
     *
     * # Add a bond
     * {"category": "bond", "content": "I would die to protect my homeland."}
     *
     * # Add a flaw
     * {"category": "flaw", "content": "I judge others harshly, and myself even more severely."}
     *
     * # Add backstory (requires title)
     * {"category": "backstory", "title": "Early Years", "content": "Born in a small village..."}
     *
     * # Add custom note (requires title)
     * {"category": "custom", "title": "Session 3 Notes", "content": "Met the mysterious stranger..."}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `category` | string | Yes | One of: personality_trait, ideal, bond, flaw, backstory, custom |
     * | `content` | string | Yes | The note content (max 10000 chars) |
     * | `title` | string | Conditional | Required for backstory and custom categories (max 255 chars) |
     * | `sort_order` | integer | No | Display order within category (auto-calculated if omitted) |
     *
     * **Category Validation:**
     * - `backstory` and `custom` categories require a `title` field
     * - Other categories (`personality_trait`, `ideal`, `bond`, `flaw`) should not include title
     */
    public function store(CharacterNoteStoreRequest $request, Character $character): JsonResponse
    {
        $validated = $request->validated();

        // Calculate next sort_order for this category if not provided
        if (! isset($validated['sort_order'])) {
            $maxOrder = $character->notes()
                ->where('category', $validated['category'])
                ->max('sort_order');
            $validated['sort_order'] = ($maxOrder ?? -1) + 1;
        }

        $note = $character->notes()->create($validated);

        return (new CharacterNoteResource($note))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Get a single note
     *
     * Retrieves a specific note by ID.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/notes/5
     * ```
     *
     * @param  Character  $character  The character
     * @param  CharacterNote  $note  The note to retrieve
     */
    public function show(Character $character, CharacterNote $note): CharacterNoteResource
    {
        // Ensure note belongs to character
        abort_if($note->character_id !== $character->id, 404);

        return new CharacterNoteResource($note);
    }

    /**
     * Update a note
     *
     * Updates an existing note. Category cannot be changed after creation.
     *
     * **Examples:**
     * ```
     * PUT /api/v1/characters/1/notes/5
     *
     * # Update content only
     * {"content": "Updated content..."}
     *
     * # Update title for custom/backstory notes
     * {"title": "New Title", "content": "Updated content..."}
     *
     * # Reorder within category
     * {"sort_order": 0}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `content` | string | No | The note content (max 10000 chars) |
     * | `title` | string | No | Title (only for backstory/custom categories) |
     * | `sort_order` | integer | No | Display order within category |
     *
     * **Note:** Category cannot be changed. Create a new note if category change is needed.
     */
    public function update(
        CharacterNoteUpdateRequest $request,
        Character $character,
        CharacterNote $note
    ): CharacterNoteResource {
        // Ensure note belongs to character
        abort_if($note->character_id !== $character->id, 404);

        $note->update($request->validated());

        return new CharacterNoteResource($note);
    }

    /**
     * Delete a note
     *
     * Permanently removes a note from the character.
     *
     * **Examples:**
     * ```
     * DELETE /api/v1/characters/1/notes/5
     * ```
     *
     * @param  Character  $character  The character
     * @param  CharacterNote  $note  The note to delete
     * @return Response 204 on success
     */
    public function destroy(Character $character, CharacterNote $note): Response
    {
        // Ensure note belongs to character
        abort_if($note->character_id !== $character->id, 404);

        $note->delete();

        return response()->noContent();
    }
}
