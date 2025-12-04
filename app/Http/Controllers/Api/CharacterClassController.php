<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DuplicateClassException;
use App\Exceptions\InvalidSubclassException;
use App\Exceptions\MaxLevelReachedException;
use App\Exceptions\MulticlassPrerequisiteException;
use App\Exceptions\SubclassLevelRequirementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Character\AddCharacterClassRequest;
use App\Http\Requests\Character\SetSubclassRequest;
use App\Http\Resources\CharacterClassPivotResource;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Services\AddClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
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
     *
     * Uses pessimistic locking to prevent race conditions where concurrent
     * requests could leave a character with zero classes.
     */
    public function destroy(Character $character, CharacterClass $class): JsonResponse
    {
        return DB::transaction(function () use ($character, $class) {
            // Lock the character's class rows to prevent concurrent modifications
            $classCount = $character->characterClasses()->lockForUpdate()->count();

            $pivot = $character->characterClasses()->where('class_id', $class->id)->first();

            if (! $pivot) {
                return response()->json([
                    'message' => 'Class not found on character',
                ], Response::HTTP_NOT_FOUND);
            }

            if ($classCount <= 1) {
                return response()->json([
                    'message' => 'Cannot remove the only class',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $pivot->delete();

            return response()->json(null, Response::HTTP_NO_CONTENT);
        });
    }

    /**
     * Level up a specific class.
     *
     * Uses pessimistic locking to prevent race conditions where concurrent
     * requests could exceed the level 20 cap.
     */
    public function levelUp(Character $character, CharacterClass $class): JsonResponse
    {
        return DB::transaction(function () use ($character, $class) {
            // Lock the character's class rows to prevent concurrent modifications
            $existingClasses = $character->characterClasses()->lockForUpdate()->get();

            $pivot = $existingClasses->where('class_id', $class->id)->first();

            if (! $pivot) {
                return response()->json([
                    'message' => 'Class not found on character',
                ], Response::HTTP_NOT_FOUND);
            }

            $totalLevel = $existingClasses->sum('level');
            if ($totalLevel >= 20) {
                return response()->json([
                    'message' => 'Character has reached maximum level (20)',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $pivot->increment('level');
            $pivot->load('characterClass', 'subclass');

            return (new CharacterClassPivotResource($pivot->fresh()))
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        });
    }

    /**
     * Set the subclass for a character's class.
     *
     * Validates that:
     * - The subclass belongs to the class being modified
     * - The character is at least level 3 in this class (D&D 5e rule)
     *
     * @throws InvalidSubclassException If subclass doesn't belong to the class
     * @throws SubclassLevelRequirementException If character level is below 3
     */
    public function setSubclass(SetSubclassRequest $request, Character $character, CharacterClass $class): JsonResponse
    {
        $pivot = $character->characterClasses()->where('class_id', $class->id)->first();

        if (! $pivot) {
            return response()->json([
                'message' => 'Class not found on character',
            ], Response::HTTP_NOT_FOUND);
        }

        $subclassId = $request->validated('subclass_id');
        $subclass = CharacterClass::find($subclassId);

        // Validate subclass belongs to this class
        if ($subclass && $subclass->parent_class_id !== $class->id) {
            throw new InvalidSubclassException($subclass->name, $class->name);
        }

        // Validate level requirement (most classes require level 3 for subclass)
        // Note: Some classes like Cleric/Sorcerer/Warlock get subclass at level 1,
        // but we use level 3 as the default. Override via class configuration if needed.
        $requiredLevel = $class->subclass_level ?? 3;
        if ($pivot->level < $requiredLevel) {
            throw new SubclassLevelRequirementException($class->name, $pivot->level, $requiredLevel);
        }

        $pivot->subclass_id = $subclassId;
        $pivot->save();
        $pivot->load('characterClass', 'subclass');

        return (new CharacterClassPivotResource($pivot))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
