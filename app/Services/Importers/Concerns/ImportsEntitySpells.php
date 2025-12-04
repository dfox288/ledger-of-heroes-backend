<?php

namespace App\Services\Importers\Concerns;

use App\Models\CharacterClass;
use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Trait for importing spell associations for polymorphic entities.
 *
 * Handles both fixed spells and spell choices with constraints.
 *
 * Used by: ItemImporter, RaceImporter, FeatImporter
 */
trait ImportsEntitySpells
{
    /**
     * Import spell associations for a polymorphic entity.
     *
     * @param  Model  $entity  The entity (Item, Race, Feat, etc.)
     * @param  array  $spellsData  Array of spell associations (fixed or choice)
     *
     * Expected format for fixed spells:
     * [
     *     [
     *         'spell_name' => 'Cure Wounds',
     *         'pivot_data' => [
     *             'is_cantrip' => false,
     *             'usage_limit' => 'long_rest',
     *             // ... other entity-specific fields
     *         ]
     *     ],
     *     ...
     * ]
     *
     * Expected format for spell choices:
     * [
     *     [
     *         'is_choice' => true,
     *         'choice_count' => 1,
     *         'choice_group' => 'spell_choice_1',
     *         'max_level' => 1,
     *         'schools' => ['illusion', 'necromancy'], // optional
     *         'class_name' => 'bard',                  // optional
     *         'is_ritual_only' => false,
     *     ],
     *     ...
     * ]
     */
    protected function importEntitySpells(Model $entity, array $spellsData): void
    {
        // Clear existing spell associations
        DB::table('entity_spells')
            ->where('reference_type', get_class($entity))
            ->where('reference_id', $entity->id)
            ->delete();

        foreach ($spellsData as $spellData) {
            if (isset($spellData['is_choice']) && $spellData['is_choice']) {
                $this->importSpellChoice($entity, $spellData);
            } else {
                $this->importFixedSpell($entity, $spellData);
            }
        }
    }

    /**
     * Import a fixed spell (existing behavior).
     */
    private function importFixedSpell(Model $entity, array $spellData): void
    {
        $spell = Spell::whereRaw('LOWER(name) = ?', [strtolower($spellData['spell_name'])])
            ->first();

        if (! $spell) {
            Log::warning("Spell not found: {$spellData['spell_name']} (for {$entity->name})");

            return;
        }

        DB::table('entity_spells')->insert([
            'reference_type' => get_class($entity),
            'reference_id' => $entity->id,
            'spell_id' => $spell->id,
            'is_choice' => false,
            'ability_score_id' => $spellData['pivot_data']['ability_score_id'] ?? null,
            'level_requirement' => $spellData['pivot_data']['level_requirement'] ?? null,
            'is_cantrip' => $spellData['pivot_data']['is_cantrip'] ?? false,
            'usage_limit' => $spellData['pivot_data']['usage_limit'] ?? null,
        ]);
    }

    /**
     * Import a spell choice with constraints.
     *
     * Creates one row per allowed school (if school-constrained),
     * or a single row (if class-constrained).
     */
    private function importSpellChoice(Model $entity, array $choiceData): void
    {
        $schools = $choiceData['schools'] ?? [];
        $className = $choiceData['class_name'] ?? null;

        // Look up class_id if class-constrained
        $classId = null;
        if ($className) {
            $characterClass = CharacterClass::whereRaw('LOWER(name) = ?', [strtolower($className)])->first();
            if ($characterClass) {
                $classId = $characterClass->id;
            } else {
                Log::warning("CharacterClass not found: {$className} (for {$entity->name})");
            }
        }

        // School-constrained: create one row per school
        if (! empty($schools)) {
            foreach ($schools as $schoolName) {
                $school = SpellSchool::whereRaw('LOWER(name) = ?', [strtolower($schoolName)])->first();
                if (! $school) {
                    Log::warning("SpellSchool not found: {$schoolName} (for {$entity->name})");

                    continue;
                }

                DB::table('entity_spells')->insert([
                    'reference_type' => get_class($entity),
                    'reference_id' => $entity->id,
                    'spell_id' => null,
                    'is_choice' => true,
                    'is_cantrip' => ($choiceData['max_level'] ?? null) === 0,
                    'choice_count' => $choiceData['choice_count'],
                    'choice_group' => $choiceData['choice_group'],
                    'max_level' => $choiceData['max_level'],
                    'school_id' => $school->id,
                    'class_id' => $classId,
                    'is_ritual_only' => $choiceData['is_ritual_only'] ?? false,
                ]);
            }
        } else {
            // Class-constrained only (no school constraint): single row
            DB::table('entity_spells')->insert([
                'reference_type' => get_class($entity),
                'reference_id' => $entity->id,
                'spell_id' => null,
                'is_choice' => true,
                'is_cantrip' => ($choiceData['max_level'] ?? null) === 0,
                'choice_count' => $choiceData['choice_count'],
                'choice_group' => $choiceData['choice_group'],
                'max_level' => $choiceData['max_level'],
                'school_id' => null,
                'class_id' => $classId,
                'is_ritual_only' => $choiceData['is_ritual_only'] ?? false,
            ]);
        }
    }
}
