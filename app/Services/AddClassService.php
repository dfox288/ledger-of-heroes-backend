<?php

namespace App\Services;

use App\Exceptions\DuplicateClassException;
use App\Exceptions\MaxLevelReachedException;
use App\Exceptions\MulticlassPrerequisiteException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;

class AddClassService
{
    public function __construct(
        private MulticlassValidationService $validator,
    ) {}

    /**
     * Add a class to a character.
     *
     * @throws MulticlassPrerequisiteException
     * @throws DuplicateClassException
     * @throws MaxLevelReachedException
     */
    public function addClass(
        Character $character,
        CharacterClass $class,
        bool $force = false
    ): CharacterClassPivot {
        // Check for duplicate
        if ($character->characterClasses()->where('class_id', $class->id)->exists()) {
            throw new DuplicateClassException($class->name);
        }

        // Check max level
        if ($character->total_level >= 20) {
            throw new MaxLevelReachedException($character);
        }

        // Check prerequisites (if not first class and not forced)
        if ($character->characterClasses->isNotEmpty() && ! $force) {
            $result = $this->validator->canAddClass($character, $class);
            if (! $result->passed) {
                throw new MulticlassPrerequisiteException($result->errors);
            }
        }

        // Determine order and primary status
        $isPrimary = $character->characterClasses->isEmpty();
        $order = $character->characterClasses->max('order') + 1 ?: 1;

        return CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $class->id,
            'level' => 1,
            'is_primary' => $isPrimary,
            'order' => $order,
            'hit_dice_spent' => 0,
        ]);
    }
}
