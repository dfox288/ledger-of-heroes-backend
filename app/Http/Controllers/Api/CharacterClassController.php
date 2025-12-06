<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ClassReplacementException;
use App\Exceptions\DuplicateClassException;
use App\Exceptions\InvalidSubclassException;
use App\Exceptions\MaxLevelReachedException;
use App\Exceptions\MulticlassPrerequisiteException;
use App\Exceptions\SubclassLevelRequirementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Character\AddCharacterClassRequest;
use App\Http\Requests\Character\ReplaceCharacterClassRequest;
use App\Http\Requests\Character\SetSubclassRequest;
use App\Http\Resources\CharacterClassPivotResource;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Services\AddClassService;
use App\Services\ReplaceClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CharacterClassController extends Controller
{
    public function __construct(
        private AddClassService $addClassService,
        private ReplaceClassService $replaceClassService,
    ) {}

    /**
     * List all classes for a character
     *
     * Returns all classes the character has, with their levels and subclasses.
     * Characters can have multiple classes (multiclassing).
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/classes
     * ```
     *
     * **Response includes:**
     * - `class_id` and class details (name, slug, hit_die)
     * - `level` in this class
     * - `subclass` (if selected, includes full subclass details)
     * - `is_primary` flag for the character's main class
     */
    public function index(Character $character): AnonymousResourceCollection
    {
        $character->load('characterClasses.characterClass', 'characterClasses.subclass');

        return CharacterClassPivotResource::collection($character->characterClasses);
    }

    /**
     * Add a class to a character
     *
     * Adds a new class to the character (multiclassing) at level 1. The character
     * must meet multiclass prerequisites unless `force: true` is specified.
     *
     * @x-flow character-creation
     *
     * @x-flow-step 3
     *
     * @x-flow gameplay-level-up
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/classes
     *
     * # Add Fighter class
     * {"class_id": 5}
     *
     * # Force add class (bypass prerequisites)
     * {"class_id": 5, "force": true}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `class_id` | integer | Yes | ID of the class to add |
     * | `force` | boolean | No | Bypass multiclass prerequisites (default: false) |
     *
     * **Multiclass Prerequisites (D&D 5e):**
     * - Each class has ability score requirements
     * - Character must meet both old class and new class prerequisites
     * - Example: Fighter requires STR 13 or DEX 13
     * - Example: Wizard requires INT 13
     *
     * **Validation:**
     * - Character cannot have the same class twice (use levelUp instead)
     * - Character's total level cannot exceed 20
     * - Must meet multiclass prerequisites (unless force=true)
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
     * Remove a class from a character
     *
     * Removes a class from the character's multiclass configuration. Cannot remove
     * the character's only class. Uses pessimistic locking to prevent race conditions.
     * Accepts either class ID or slug.
     *
     * **Examples:**
     * ```
     * DELETE /api/v1/characters/1/classes/5           # Remove by ID
     * DELETE /api/v1/characters/1/classes/fighter     # Remove by slug
     * ```
     *
     * **Validation:**
     * - Cannot remove the character's only class
     * - Class must exist on the character
     *
     * **Side Effects:**
     * - Removes all levels in this class
     * - Associated subclass is also removed
     * - May affect character features, spells, etc. (handle in UI)
     *
     * @param  Character  $character  The character
     * @param  string  $classIdOrSlug  Class ID or slug
     */
    public function destroy(Character $character, string $classIdOrSlug): JsonResponse
    {
        $class = is_numeric($classIdOrSlug)
            ? CharacterClass::findOrFail($classIdOrSlug)
            : CharacterClass::where('slug', $classIdOrSlug)->firstOrFail();

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
     * Level up a specific class
     *
     * **Preferred Method:** This is the recommended way to level up characters,
     * especially in multiclass builds, as it provides explicit control over which
     * class gains a level.
     *
     * Increases the character's level in a specific class by 1. The total character
     * level cannot exceed 20. Uses pessimistic locking to prevent race conditions.
     * Accepts either class ID or slug.
     *
     * @x-flow gameplay-level-up
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/classes/5/level-up           # Level up by ID
     * POST /api/v1/characters/1/classes/fighter/level-up     # Level up by slug
     * ```
     *
     * **D&D 5e Level Rules:**
     * - Total character level (sum of all classes) caps at 20
     * - Each class level grants new features, proficiencies, etc.
     * - Leveling up may trigger subclass selection (typically at level 3)
     *
     * **Multiclass Benefits:**
     * - Explicit control over which class levels up
     * - No ambiguity in multiclass progression
     * - Consistent API for both single-class and multiclass characters
     *
     * **Validation:**
     * - Class must exist on the character
     * - Total character level cannot exceed 20
     *
     * @param  Character  $character  The character
     * @param  string  $classIdOrSlug  Class ID or slug
     */
    public function levelUp(Character $character, string $classIdOrSlug): JsonResponse
    {
        $class = is_numeric($classIdOrSlug)
            ? CharacterClass::findOrFail($classIdOrSlug)
            : CharacterClass::where('slug', $classIdOrSlug)->firstOrFail();

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
     * Replace a character's class (level 1 only)
     *
     * Replaces the character's current class with a different class. Only valid for
     * level 1 characters with a single class. This is useful in character creation
     * wizards where users can go back and change their class selection.
     *
     * @x-flow character-creation
     *
     * **Examples:**
     * ```
     * PUT /api/v1/characters/1/classes/5           # Replace by ID
     * PUT /api/v1/characters/1/classes/fighter     # Replace by slug
     *
     * # Request body
     * {"class_id": 7}
     *
     * # With force flag
     * {"class_id": 7, "force": true}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `class_id` | integer | Yes | ID of the new class |
     * | `force` | boolean | No | Reserved for future DM override (default: false) |
     *
     * **Validation:**
     * - Character must have exactly one class
     * - Character must be at level 1 in that class
     * - Target class must be a base class (not a subclass)
     * - Target class must be different from the current class
     *
     * **Side Effects:**
     * - Old class is removed
     * - Subclass is cleared (set to null)
     * - Hit dice spent is reset to 0
     * - Class proficiencies from old class are cleared
     * - Spell slots are recalculated
     * - `is_primary` and `order` are preserved
     *
     * @param  ReplaceCharacterClassRequest  $request  The validated request
     * @param  Character  $character  The character
     * @param  string  $classIdOrSlug  The class ID or slug to replace
     */
    public function replace(ReplaceCharacterClassRequest $request, Character $character, string $classIdOrSlug): JsonResponse
    {
        $sourceClass = is_numeric($classIdOrSlug)
            ? CharacterClass::findOrFail($classIdOrSlug)
            : CharacterClass::where('slug', $classIdOrSlug)->firstOrFail();

        $targetClass = CharacterClass::findOrFail($request->validated('class_id'));
        $force = $request->validated('force', false);

        try {
            $pivot = $this->replaceClassService->replaceClass($character, $sourceClass, $targetClass, $force);
            $pivot->load('characterClass', 'subclass');

            return (new CharacterClassPivotResource($pivot))
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        } catch (ClassReplacementException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->statusCode);
        }
    }

    /**
     * Set the subclass for a character's class
     *
     * Assigns a subclass (archetype/specialization) to the character's class.
     * Most classes unlock subclasses at level 3, though some (Cleric, Sorcerer, Warlock) get them at level 1.
     * Accepts either class ID or slug.
     *
     * @x-flow gameplay-level-up
     *
     * **Examples:**
     * ```
     * PUT /api/v1/characters/1/classes/5/subclass           # Set by ID
     * PUT /api/v1/characters/1/classes/fighter/subclass     # Set by slug
     *
     * # Set Battle Master subclass for Fighter
     * {"subclass_id": 42}
     *
     * # Clear subclass (set to null)
     * {"subclass_id": null}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `subclass_id` | integer\|null | Yes | ID of subclass, or null to clear |
     *
     * **Subclass Level Requirements (by class):**
     * - Level 1: Cleric (Domain), Sorcerer (Origin), Warlock (Patron)
     * - Level 2: Druid (Circle), Wizard (School)
     * - Level 3: Most other classes (Fighter, Rogue, Ranger, etc.)
     *
     * **Validation:**
     * - Subclass must belong to the specified class
     * - Character must meet the class's subclass level requirement
     * - Class must exist on the character
     *
     * @param  SetSubclassRequest  $request  The validated request
     * @param  Character  $character  The character
     * @param  string  $classIdOrSlug  Class ID or slug
     *
     * @throws InvalidSubclassException If subclass doesn't belong to the class
     * @throws SubclassLevelRequirementException If character level is below requirement
     */
    public function setSubclass(SetSubclassRequest $request, Character $character, string $classIdOrSlug): JsonResponse
    {
        $class = is_numeric($classIdOrSlug)
            ? CharacterClass::findOrFail($classIdOrSlug)
            : CharacterClass::where('slug', $classIdOrSlug)->firstOrFail();
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
