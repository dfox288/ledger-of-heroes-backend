<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DuplicateClassException;
use App\Exceptions\MaxLevelReachedException;
use App\Exceptions\MulticlassPrerequisiteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Character\AddCharacterClassRequest;
use App\Http\Resources\CharacterClassPivotResource;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Services\AddClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CharacterClassController extends Controller
{
    public function __construct(
        private AddClassService $addClassService,
    ) {}

    /**
     * List all classes for a character.
     */
    public function index(Character $character): AnonymousResourceCollection
    {
        $character->load('characterClasses.characterClass', 'characterClasses.subclass');

        return CharacterClassPivotResource::collection($character->characterClasses);
    }

    /**
     * Add a class to a character.
     */
    public function store(AddCharacterClassRequest $request, Character $character): JsonResponse
    {
        $class = CharacterClass::findOrFail($request->validated('class_id'));
        $force = $request->validated('force', false);

        try {
            $pivot = $this->addClassService->addClass($character, $class, $force);
            $pivot->load('characterClass', 'subclass');

            return (new CharacterClassPivotResource($pivot))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (MulticlassPrerequisiteException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['class_id' => $e->errors],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (DuplicateClassException|MaxLevelReachedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Remove a class from a character.
     */
    public function destroy(Character $character, CharacterClass $class): JsonResponse
    {
        $pivot = $character->characterClasses()->where('class_id', $class->id)->first();

        if (! $pivot) {
            return response()->json([
                'message' => 'Class not found on character',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($character->characterClasses()->count() <= 1) {
            return response()->json([
                'message' => 'Cannot remove the only class',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $pivot->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Level up a specific class.
     */
    public function levelUp(Character $character, CharacterClass $class): JsonResponse
    {
        $pivot = $character->characterClasses()->where('class_id', $class->id)->first();

        if (! $pivot) {
            return response()->json([
                'message' => 'Class not found on character',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($character->total_level >= 20) {
            return response()->json([
                'message' => 'Character has reached maximum level (20)',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $pivot->increment('level');
        $pivot->load('characterClass', 'subclass');

        return (new CharacterClassPivotResource($pivot->fresh()))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
