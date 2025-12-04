<?php

namespace App\Services;

use App\Exceptions\DuplicateClassException;
use App\Exceptions\MaxLevelReachedException;
use App\Exceptions\MulticlassPrerequisiteException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddClassService
{
    public function __construct(
        private MulticlassValidationService $validator,
    ) {}

    /**
     * Add a class to a character.
     *
     * Uses database transaction with row locking to prevent race conditions
     * where concurrent requests could violate the level 20 cap.
     *
     * @param  bool  $force  DM override: bypass multiclass prerequisites
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
        return DB::transaction(function () use ($character, $class, $force) {
            // Lock the character's class rows to prevent concurrent modifications
            $existingClasses = $character->characterClasses()->lockForUpdate()->get();

            // Check for duplicate
            if ($existingClasses->where('class_id', $class->id)->isNotEmpty()) {
                throw new DuplicateClassException($class->name, $character->id, $character->name);
            }

            // Check max level (sum of locked rows)
            $totalLevel = $existingClasses->sum('level');
            if ($totalLevel >= 20) {
                throw new MaxLevelReachedException($character);
            }

            // Check prerequisites (if not first class and not forced)
            if ($existingClasses->isNotEmpty() && ! $force) {
                $result = $this->validator->canAddClass($character, $class);
                if (! $result->passed) {
                    throw new MulticlassPrerequisiteException($result->errors, $character->id, $character->name);
                }
            }

            // Log forced multiclass additions for audit trail
            if ($force && $existingClasses->isNotEmpty()) {
                Log::info('Forced multiclass addition (DM override)', [
                    'character_id' => $character->id,
                    'character_name' => $character->name,
                    'class_id' => $class->id,
                    'class_name' => $class->name,
                ]);
            }

            // Determine order and primary status
            $isPrimary = $existingClasses->isEmpty();
            $order = ($existingClasses->max('order') ?? 0) + 1;

            return CharacterClassPivot::create([
                'character_id' => $character->id,
                'class_id' => $class->id,
                'level' => 1,
                'is_primary' => $isPrimary,
                'order' => $order,
                'hit_dice_spent' => 0,
            ]);
        });
    }
}
