<?php

namespace App\Http\Controllers\Api;

use App\Enums\NoteCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\CharacterNote\CharacterNoteStoreRequest;
use App\Http\Requests\CharacterNote\CharacterNoteUpdateRequest;
use App\Http\Resources\CharacterNoteResource;
use App\Models\Character;
use App\Models\CharacterNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CharacterNoteController extends Controller
{
    /**
     * List all notes for a character, grouped by category.
     *
     * Returns notes organized by category: personality_trait, ideal, bond, flaw, backstory, custom.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/notes
     * ```
     */
    public function index(Character $character): JsonResponse
    {
        $notes = $character->notes()->get();

        // Group notes by category for easier frontend consumption
        $grouped = [];
        foreach (NoteCategory::cases() as $category) {
            $categoryNotes = $notes->where('category', $category);
            if ($categoryNotes->isNotEmpty()) {
                $grouped[$category->value] = CharacterNoteResource::collection($categoryNotes);
            }
        }

        return response()->json([
            'data' => $grouped,
        ]);
    }

    /**
     * Add a new note to a character.
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/notes
     * {
     *     "category": "personality_trait",
     *     "content": "I am slow to trust, but fiercely loyal."
     * }
     *
     * POST /api/v1/characters/1/notes
     * {
     *     "category": "custom",
     *     "title": "Session 3 Notes",
     *     "content": "Met the mysterious stranger in the tavern..."
     * }
     * ```
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
     * Get a single note.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/notes/5
     * ```
     */
    public function show(Character $character, CharacterNote $note): CharacterNoteResource
    {
        // Ensure note belongs to character
        abort_if($note->character_id !== $character->id, 404);

        return new CharacterNoteResource($note);
    }

    /**
     * Update a note.
     *
     * **Examples:**
     * ```
     * PUT /api/v1/characters/1/notes/5
     * {
     *     "content": "Updated content..."
     * }
     * ```
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
     * Delete a note.
     *
     * **Examples:**
     * ```
     * DELETE /api/v1/characters/1/notes/5
     * ```
     */
    public function destroy(Character $character, CharacterNote $note): Response
    {
        // Ensure note belongs to character
        abort_if($note->character_id !== $character->id, 404);

        $note->delete();

        return response()->noContent();
    }
}
