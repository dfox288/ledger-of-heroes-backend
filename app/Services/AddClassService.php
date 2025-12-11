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
        private SpellSlotService $spellSlotService,
        private EquipmentManagerService $equipmentService,
        private CharacterFeatureService $featureService,
        private HitPointService $hitPointService,
        private CharacterLanguageService $languageService,
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
            if ($existingClasses->where('class_slug', $class->slug)->isNotEmpty()) {
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
                    'class_slug' => $class->slug,
                    'class_name' => $class->name,
                ]);
            }

            // Determine order and primary status
            $isPrimary = $existingClasses->isEmpty();
            $order = ($existingClasses->max('order') ?? 0) + 1;

            $pivot = CharacterClassPivot::create([
                'character_id' => $character->id,
                'class_slug' => $class->slug,
                'level' => 1,
                'is_primary' => $isPrimary,
                'order' => $order,
                'hit_dice_spent' => 0,
            ]);

            // Refresh character to get updated relationships
            $character->refresh();

            // Recalculate spell slots when adding a new class (may gain spellcasting)
            $this->spellSlotService->recalculateMaxSlots($character);

            // Grant fixed equipment for primary class only (multiclass doesn't get starting equipment)
            if ($isPrimary) {
                $this->equipmentService->populateFromClass($character);
            }

            // Grant fixed class languages for primary class only (e.g., Druid gets Druidic)
            if ($isPrimary) {
                $this->languageService->populateFromClass($character);
            }

            // Populate class features for the new class
            $this->featureService->populateFromClass($character);

            // Auto-initialize HP for first class (level 1) if using calculated HP
            if ($isPrimary && $character->usesCalculatedHp()) {
                $startingHp = $this->hitPointService->calculateStartingHp($character, $class);
                // Add race HP bonus (e.g., Hill Dwarf Dwarven Toughness grants +1 HP per level)
                $startingHp += $this->hitPointService->getRaceHpBonus($character);
                $character->update([
                    'max_hit_points' => $startingHp,
                    'current_hit_points' => $startingHp,
                    'hp_levels_resolved' => [1],
                ]);
            }

            return $pivot;
        });
    }
}
