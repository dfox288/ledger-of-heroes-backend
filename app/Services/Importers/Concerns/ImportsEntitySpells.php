<?php

namespace App\Services\Importers\Concerns;

use App\Models\CharacterClass;
use App\Models\EntityChoice;
use App\Models\EntitySpell;
use App\Models\Spell;
use App\Services\Parsers\Traits\ParsesChoices;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Trait for importing spell associations for polymorphic entities.
 *
 * Handles both fixed spells and spell choices with constraints.
 * - Fixed spells go to EntitySpell table
 * - Choice-based spells go to EntityChoice table
 *
 * Used by: ItemImporter, RaceImporter, FeatImporter
 */
trait ImportsEntitySpells
{
    use ParsesChoices;

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

        // Clear existing spell choices
        EntityChoice::where('reference_type', get_class($entity))
            ->where('reference_id', $entity->id)
            ->where('choice_type', 'spell')
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
     * Import a fixed spell.
     * Creates an EntitySpell record.
     */
    private function importFixedSpell(Model $entity, array $spellData): void
    {
        $spell = Spell::whereRaw('LOWER(name) = ?', [strtolower($spellData['spell_name'])])
            ->first();

        if (! $spell) {
            Log::warning("Spell not found: {$spellData['spell_name']} (for {$entity->name})");

            return;
        }

        EntitySpell::create([
            'reference_type' => get_class($entity),
            'reference_id' => $entity->id,
            'spell_id' => $spell->id,
            'ability_score_id' => $spellData['pivot_data']['ability_score_id'] ?? null,
            'level_requirement' => $spellData['pivot_data']['level_requirement'] ?? null,
            'is_cantrip' => $spellData['pivot_data']['is_cantrip'] ?? false,
            'usage_limit' => $spellData['pivot_data']['usage_limit'] ?? null,
            // Item-specific charge cost fields
            'charges_cost_min' => $spellData['pivot_data']['charges_cost_min'] ?? null,
            'charges_cost_max' => $spellData['pivot_data']['charges_cost_max'] ?? null,
            'charges_cost_formula' => $spellData['pivot_data']['charges_cost_formula'] ?? null,
        ]);
    }

    /**
     * Import a spell choice with constraints.
     * Creates EntityChoice records.
     *
     * Creates one EntityChoice per allowed school (if school-constrained),
     * or a single EntityChoice (if class-constrained only).
     */
    private function importSpellChoice(Model $entity, array $choiceData): void
    {
        $schools = $choiceData['schools'] ?? [];
        $className = $choiceData['class_name'] ?? null;
        $choiceGroup = $choiceData['choice_group'] ?? 'spell_choice';
        $quantity = $choiceData['choice_count'] ?? 1;
        $maxLevel = $choiceData['max_level'] ?? null;

        // Look up class slug if class-constrained
        $classSlug = null;
        if ($className) {
            $characterClass = CharacterClass::whereRaw('LOWER(name) = ?', [strtolower($className)])->first();
            if ($characterClass) {
                $classSlug = $characterClass->slug;
            } else {
                Log::warning("CharacterClass not found: {$className} (for {$entity->name})");
            }
        }

        // Build constraints for additional properties
        $constraints = null;
        if (! empty($choiceData['is_ritual_only'])) {
            $constraints = ['is_ritual_only' => true];
        }

        // School-constrained: create one EntityChoice per school
        if (! empty($schools)) {
            foreach ($schools as $schoolName) {
                $schoolSlug = strtolower($schoolName);

                $this->createSpellChoice(
                    referenceType: get_class($entity),
                    referenceId: $entity->id,
                    choiceGroup: $choiceGroup.'_'.$schoolSlug,
                    quantity: $quantity,
                    maxLevel: $maxLevel ?? 0,
                    classSlug: $classSlug,
                    schoolSlug: $schoolSlug,
                    levelGranted: 1,
                    constraints: $constraints
                );
            }
        } else {
            // Class-constrained only (no school constraint): single EntityChoice
            $this->createSpellChoice(
                referenceType: get_class($entity),
                referenceId: $entity->id,
                choiceGroup: $choiceGroup,
                quantity: $quantity,
                maxLevel: $maxLevel ?? 0,
                classSlug: $classSlug,
                schoolSlug: null,
                levelGranted: 1,
                constraints: $constraints
            );
        }
    }
}
