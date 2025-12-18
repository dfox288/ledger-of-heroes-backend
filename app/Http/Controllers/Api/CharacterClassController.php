<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ClassReplacementException;
use App\Exceptions\DuplicateClassException;
use App\Exceptions\IncompleteCharacterException;
use App\Exceptions\InvalidSubclassException;
use App\Exceptions\MaxLevelReachedException;
use App\Exceptions\MulticlassPrerequisiteException;
use App\Exceptions\SubclassLevelRequirementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Character\CharacterClassAddRequest;
use App\Http\Requests\Character\CharacterSubclassSetRequest;
use App\Http\Resources\CharacterClassPivotResource;
use App\Http\Resources\LevelUpResource;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Services\AddClassService;
use App\Services\CharacterFeatureService;
use App\Services\LevelUpService;
use App\Services\ReplaceClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CharacterClassController extends Controller
{
    public function __construct(
        private AddClassService $addClassService,
        private ReplaceClassService $replaceClassService,
        private CharacterFeatureService $featureService,
        private LevelUpService $levelUpService,
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
     * - `class_slug` and class details (name, slug, hit_die)
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
     * {"class": "phb:fighter"}
     *
     * # Force add class (bypass prerequisites)
     * {"class": "phb:fighter", "force": true}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `class` | string | Yes | Full slug of the class (e.g., "phb:fighter") |
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
     *
     * @response CharacterClassPivotResource
     */
    public function store(CharacterClassAddRequest $request, Character $character): JsonResponse
    {
        $classSlug = $request->validated('class_slug');
        $class = CharacterClass::where('slug', $classSlug)->first();

        if (! $class) {
            return response()->json([
                'message' => 'Class not found',
                'errors' => ['class' => ["No class found with slug '{$classSlug}'"]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

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
                'errors' => ['class' => $e->errors],
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
    public function destroy(Character $character, string $classSlugOrFullSlug): HttpResponse
    {
        // Accept either slug (phb:fighter) or simple slug (fighter)
        $class = CharacterClass::where('slug', $classSlugOrFullSlug)
            ->first();

        if (! $class) {
            abort(404, 'Class not found');
        }

        return DB::transaction(function () use ($character, $class) {
            // Lock the character's class rows to prevent concurrent modifications
            $classCount = $character->characterClasses()->lockForUpdate()->count();

            $pivot = $character->characterClasses()->where('class_slug', $class->slug)->first();

            if (! $pivot) {
                abort(404, 'Class not found on character');
            }

            if ($classCount <= 1) {
                abort(422, 'Cannot remove the only class');
            }

            $pivot->delete();

            return response()->noContent();
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
     * **Response Fields:**
     * - `previous_level` - Character level before level-up
     * - `new_level` - Character level after level-up
     * - `hp_increase` - HP gained (0 when using choice system)
     * - `new_max_hp` - Current max HP after level-up
     * - `features_gained` - Array of class features granted at this level
     * - `spell_slots` - Spell slots by level (e.g., {"1": 4, "2": 3})
     * - `asi_pending` - Whether an ASI/Feat choice is pending
     * - `hp_choice_pending` - Whether an HP choice (roll/average) is pending
     * - `pending_choice_summary` - Summary of all pending choices by type and source
     *
     * @param  Character  $character  The character
     * @param  string  $classIdOrSlug  Class ID or slug
     * @return \Illuminate\Http\JsonResponse<LevelUpResource>
     */
    public function levelUp(Character $character, string $classSlugOrFullSlug): JsonResponse
    {
        // Accept either slug (phb:fighter) or simple slug (fighter)
        $class = CharacterClass::where('slug', $classSlugOrFullSlug)
            ->first();

        if (! $class) {
            return response()->json([
                'message' => 'Class not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Verify class exists on character before attempting level-up
        $pivot = $character->characterClasses()->where('class_slug', $class->slug)->first();
        if (! $pivot) {
            return response()->json([
                'message' => 'Class not found on character',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $result = $this->levelUpService->levelUp($character, $class->slug);

            return (new LevelUpResource($result))
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        } catch (MaxLevelReachedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (IncompleteCharacterException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
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
     * {"class": "phb:wizard"}
     *
     * # With force flag
     * {"class": "phb:wizard", "force": true}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `class` | string | Yes | Full slug of the new class (e.g., "phb:wizard") |
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
     * @param  CharacterClassAddRequest  $request  The validated request
     * @param  Character  $character  The character
     * @param  string  $classIdOrSlug  The class ID or slug to replace
     * @return \Illuminate\Http\JsonResponse<CharacterClassPivotResource>
     */
    public function replace(CharacterClassAddRequest $request, Character $character, string $classSlugOrFullSlug): JsonResponse
    {
        // Accept either slug (phb:fighter) or simple slug (fighter)
        $sourceClass = CharacterClass::where('slug', $classSlugOrFullSlug)
            ->first();

        if (! $sourceClass) {
            return response()->json([
                'message' => 'Source class not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $targetClassSlug = $request->validated('class_slug');
        $targetClass = CharacterClass::where('slug', $targetClassSlug)->first();

        if (! $targetClass) {
            return response()->json([
                'message' => 'Target class not found',
                'errors' => ['class' => ["No class found with slug '{$targetClassSlug}'"]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

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
     * {"subclass": "phb:battle-master"}
     *
     * # Clear subclass (set to null) - not currently supported
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `subclass` | string | Yes | Full slug of subclass (e.g., phb:battle-master) |
     *
     * **Subclass Level Requirements (by class):**
     * - Level 1: Cleric (Domain), Sorcerer (Origin), Warlock (Patron)
     * - Level 2: Druid (Circle), Wizard (School)
     * - Level 3: Most other classes (Fighter, Rogue, Ranger, etc.)
     *
     * **Validation:**
     * - Subclass must belong to the specified class (if it exists)
     * - Character must meet the class's subclass level requirement
     * - Class must exist on the character
     * - Dangling references allowed per #288
     *
     * @param  CharacterSubclassSetRequest  $request  The validated request
     * @param  Character  $character  The character
     * @param  string  $classSlugOrFullSlug  Class slug
     * @return \Illuminate\Http\JsonResponse<CharacterClassPivotResource>
     *
     * @throws InvalidSubclassException If subclass doesn't belong to the class
     * @throws SubclassLevelRequirementException If character level is below requirement
     */
    public function setSubclass(CharacterSubclassSetRequest $request, Character $character, string $classSlugOrFullSlug): JsonResponse
    {
        // Accept either slug (phb:fighter) or simple slug (fighter)
        $class = CharacterClass::where('slug', $classSlugOrFullSlug)
            ->first();

        if (! $class) {
            return response()->json([
                'message' => 'Class not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $pivot = $character->characterClasses()->where('class_slug', $class->slug)->first();

        if (! $pivot) {
            return response()->json([
                'message' => 'Class not found on character',
            ], Response::HTTP_NOT_FOUND);
        }

        $subclassSlug = $request->validated('subclass_slug');
        $subclass = CharacterClass::where('slug', $subclassSlug)->first();

        // Validate subclass belongs to this class (only if subclass exists)
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

        $pivot->subclass_slug = $subclassSlug;
        $pivot->save();
        $pivot->load('characterClass', 'subclass');

        // Assign subclass features to the character
        if ($subclass) {
            $this->featureService->populateFromSubclass($character, $class->slug, $subclassSlug);
        }

        return (new CharacterClassPivotResource($pivot))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
