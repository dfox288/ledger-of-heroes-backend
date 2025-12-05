<?php

namespace App\Services;

use App\Exceptions\ClassReplacementException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReplaceClassService
{
    public function __construct(
        private CharacterProficiencyService $proficiencyService,
        private SpellSlotService $spellSlotService,
    ) {}

    /**
     * Replace a character's class with another class.
     *
     * Only valid for level 1 characters with a single class.
     * Preserves is_primary and order flags, clears subclass and resets hit_dice_spent.
     *
     * @param  Character  $character  The character
     * @param  CharacterClass  $sourceClass  The class being replaced
     * @param  CharacterClass  $targetClass  The new class
     * @param  bool  $force  DM override - currently unused but included for API consistency
     *
     * @throws ClassReplacementException
     */
    public function replaceClass(
        Character $character,
        CharacterClass $sourceClass,
        CharacterClass $targetClass,
        bool $force = false
    ): CharacterClassPivot {
        return DB::transaction(function () use ($character, $sourceClass, $targetClass, $force) {
            // Lock the character's class rows to prevent concurrent modifications
            $existingClasses = $character->characterClasses()->lockForUpdate()->get();

            // Find the source class pivot
            $sourcePivot = $existingClasses->where('class_id', $sourceClass->id)->first();
            if (! $sourcePivot) {
                throw ClassReplacementException::classNotFoundOnCharacter($sourceClass->name);
            }

            // Validate: level must be 1
            if ($sourcePivot->level > 1) {
                throw ClassReplacementException::levelTooHigh($sourcePivot->level);
            }

            // Validate: character must have only one class
            if ($existingClasses->count() > 1) {
                throw ClassReplacementException::multipleClasses();
            }

            // Validate: target class must be different from source
            if ($sourceClass->id === $targetClass->id) {
                throw ClassReplacementException::sameClass();
            }

            // Validate: target class must not be a subclass
            if ($targetClass->parent_class_id !== null) {
                throw ClassReplacementException::targetIsSubclass($targetClass->name);
            }

            // Log the replacement for audit trail
            Log::info('Class replacement', [
                'character_id' => $character->id,
                'character_name' => $character->name,
                'from_class_id' => $sourceClass->id,
                'from_class_name' => $sourceClass->name,
                'to_class_id' => $targetClass->id,
                'to_class_name' => $targetClass->name,
                'force' => $force,
            ]);

            // Clear proficiencies from the old class
            $this->proficiencyService->clearProficiencies($character, 'class');

            // Preserve order and primary status
            $isPrimary = $sourcePivot->is_primary;
            $order = $sourcePivot->order;

            // Delete the old class pivot
            $sourcePivot->delete();

            // Create the new class pivot
            $newPivot = CharacterClassPivot::create([
                'character_id' => $character->id,
                'class_id' => $targetClass->id,
                'subclass_id' => null, // Clear subclass
                'level' => 1,
                'is_primary' => $isPrimary,
                'order' => $order,
                'hit_dice_spent' => 0,
            ]);

            // Recalculate spell slots for the new class
            $this->spellSlotService->recalculateMaxSlots($character->fresh());

            return $newPivot;
        });
    }
}
