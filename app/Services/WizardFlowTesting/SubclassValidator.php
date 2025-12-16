<?php

declare(strict_types=1);

namespace App\Services\WizardFlowTesting;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use Illuminate\Support\Facades\DB;

/**
 * Validates that subclass features, spells, and proficiencies are properly assigned after subclass selection.
 *
 * This catches bugs where:
 * - Subclass features marked as is_optional are not granted
 * - Subclass spells (always prepared) are not granted
 * - Subclass proficiencies are not granted
 */
class SubclassValidator
{
    /**
     * Validate that subclass features were assigned after subclass selection.
     *
     * @param  array  $snapshotAfter  State snapshot after subclass selection
     * @param  CharacterClass  $subclass  The selected subclass
     * @param  int  $characterLevel  The character's level in the parent class
     */
    public function validateSubclassFeatures(
        array $snapshotAfter,
        CharacterClass $subclass,
        int $characterLevel = 1
    ): ValidationResult {
        $errors = [];
        $warnings = [];
        $pattern = null;

        // Get expected subclass features at or below character level
        $expectedFeatures = $subclass->features()
            ->where('level', '<=', $characterLevel)
            ->whereNull('parent_feature_id') // Exclude child features (choice options)
            ->get();

        if ($expectedFeatures->isEmpty()) {
            // No features expected at this level - this is a warning since most subclasses have level 1 features
            $warnings[] = "No subclass features found for {$subclass->name} at level {$characterLevel}";

            return ValidationResult::passWithWarnings($warnings);
        }

        // Get character's current features
        $characterFeatures = $snapshotAfter['features']['data'] ?? [];

        // Get subclass features specifically
        $subclassFeatures = array_filter(
            $characterFeatures,
            fn ($f) => ($f['source'] ?? '') === 'subclass'
        );

        // Check if any subclass features were assigned
        if (empty($subclassFeatures)) {
            $errors[] = "No subclass features assigned for {$subclass->name}. Expected ".count($expectedFeatures).' features at level '.$characterLevel.'.';
            $errors[] = 'Expected features: '.implode(', ', $expectedFeatures->pluck('feature_name')->toArray());
            $pattern = 'subclass_features_not_assigned';

            return ValidationResult::fail($errors, $pattern);
        }

        // Check if the correct number of features were assigned
        $expectedCount = $expectedFeatures->count();
        $actualCount = count($subclassFeatures);

        if ($actualCount < $expectedCount) {
            $errors[] = "Only {$actualCount} of {$expectedCount} expected subclass features assigned for {$subclass->name}.";
            $pattern = 'subclass_features_incomplete';

            // List what's missing
            $assignedSlugs = array_column($subclassFeatures, 'slug');
            $expectedSlugs = $expectedFeatures->pluck('slug')->toArray();
            $missingSlugs = array_diff($expectedSlugs, $assignedSlugs);

            if (! empty($missingSlugs)) {
                $errors[] = 'Missing features: '.implode(', ', $missingSlugs);
            }

            return ValidationResult::fail($errors, $pattern);
        }

        // Optional: Verify specific expected features are present
        $assignedSlugs = array_column($subclassFeatures, 'slug');
        $expectedSlugs = $expectedFeatures->pluck('slug')->toArray();
        $missingSlugs = array_diff($expectedSlugs, $assignedSlugs);

        if (! empty($missingSlugs)) {
            $warnings[] = 'Some expected subclass features not found: '.implode(', ', $missingSlugs);
        }

        // Success with optional warnings
        if (! empty($warnings)) {
            return ValidationResult::passWithWarnings($warnings);
        }

        return ValidationResult::pass();
    }

    /**
     * Validate that subclass spells were granted.
     *
     * Checks both:
     * - Spells linked to subclass features (entity_spells) for "always prepared" classes
     * - Spells linked to the subclass itself (class_spells)
     *
     * @param  array  $snapshotAfter  State snapshot after subclass selection
     * @param  CharacterClass  $subclass  The selected subclass
     * @param  int  $characterLevel  The character's level in the parent class
     */
    public function validateSubclassSpells(
        array $snapshotAfter,
        CharacterClass $subclass,
        int $characterLevel = 1
    ): ValidationResult {
        $errors = [];
        $warnings = [];

        // Check if this is an "always prepared" class
        $parentClass = $subclass->parentClass;
        $parentClassName = $parentClass ? strtolower($parentClass->name) : '';
        $isAlwaysPrepared = in_array($parentClassName, ClassFeature::ALWAYS_PREPARED_CLASSES);

        if (! $isAlwaysPrepared) {
            // Warlock and similar classes don't auto-grant spells
            return ValidationResult::pass();
        }

        // Get expected spells from subclass features (entity_spells) - this is the canonical source with level_requirement
        $expectedSpellsFromFeatures = $this->getExpectedSpellsFromFeatures($subclass, $characterLevel);

        // Get expected spells from subclass class_spells - only if no feature spells found
        // (class_spells often lacks level_learned data, so we fall back to it only when needed)
        $expectedSpellsFromClass = [];
        if (empty($expectedSpellsFromFeatures)) {
            $expectedSpellsFromClass = $this->getExpectedSpellsFromClass($subclass, $characterLevel);
        }

        // Combine both sources (but prefer entity_spells if available)
        $expectedSpells = array_unique(array_merge($expectedSpellsFromFeatures, $expectedSpellsFromClass));

        if (empty($expectedSpells)) {
            // No spells expected - this is okay for some subclasses
            return ValidationResult::pass();
        }

        // Get character's current spells
        $characterSpells = $snapshotAfter['spells']['data'] ?? [];
        $characterSpellSlugs = array_column($characterSpells, 'spell_slug');

        // Check which expected spells are missing
        $missingSpells = array_diff($expectedSpells, $characterSpellSlugs);

        if (! empty($missingSpells)) {
            $errors[] = "Missing subclass spells for {$subclass->name}: ".implode(', ', $missingSpells);
            $errors[] = 'Expected '.count($expectedSpells).' spells, found '.count(array_intersect($expectedSpells, $characterSpellSlugs));

            return ValidationResult::fail($errors, 'subclass_spells_not_granted');
        }

        // Verify spells have correct preparation status
        $subclassSpells = array_filter(
            $characterSpells,
            fn ($s) => in_array($s['spell_slug'], $expectedSpells)
        );

        $wrongPreparation = array_filter(
            $subclassSpells,
            fn ($s) => ($s['preparation_status'] ?? '') !== 'always_prepared'
        );

        if (! empty($wrongPreparation)) {
            $wrongSlugs = array_column($wrongPreparation, 'spell_slug');
            $warnings[] = 'Subclass spells not marked as always_prepared: '.implode(', ', $wrongSlugs);
        }

        if (! empty($warnings)) {
            return ValidationResult::passWithWarnings($warnings);
        }

        return ValidationResult::pass();
    }

    /**
     * Validate that subclass proficiencies were granted.
     *
     * @param  array  $snapshotAfter  State snapshot after subclass selection
     * @param  CharacterClass  $subclass  The selected subclass
     * @param  int  $characterLevel  The character's level in the parent class
     */
    public function validateSubclassProficiencies(
        array $snapshotAfter,
        CharacterClass $subclass,
        int $characterLevel = 1
    ): ValidationResult {
        $errors = [];
        $warnings = [];

        // Get expected proficiencies from subclass features
        $expectedProficiencies = $this->getExpectedProficienciesFromFeatures($subclass, $characterLevel);

        if (empty($expectedProficiencies)) {
            // No proficiencies expected - this is okay
            return ValidationResult::pass();
        }

        // Get character's current proficiencies
        $characterProficiencies = $snapshotAfter['proficiencies']['data'] ?? [];

        // Extract skill slugs and type slugs
        $characterSkillSlugs = array_filter(array_column($characterProficiencies, 'skill_slug'));
        $characterTypeSlugs = array_filter(array_column($characterProficiencies, 'proficiency_type_slug'));
        $allCharacterProfSlugs = array_merge($characterSkillSlugs, $characterTypeSlugs);

        // Check which expected proficiencies are missing
        $missingProficiencies = [];
        foreach ($expectedProficiencies as $prof) {
            $slug = $prof['slug'] ?? null;
            $name = $prof['name'] ?? 'unknown';

            if ($slug && ! in_array($slug, $allCharacterProfSlugs)) {
                $missingProficiencies[] = "{$name} ({$slug})";
            } elseif (! $slug) {
                // Proficiency without a slug - check by name in warnings
                $warnings[] = "Proficiency '{$name}' has no slug - cannot verify if granted";
            }
        }

        if (! empty($missingProficiencies)) {
            $errors[] = "Missing subclass proficiencies for {$subclass->name}: ".implode(', ', $missingProficiencies);

            return ValidationResult::fail($errors, 'subclass_proficiencies_not_granted');
        }

        if (! empty($warnings)) {
            return ValidationResult::passWithWarnings($warnings);
        }

        return ValidationResult::pass();
    }

    /**
     * Get expected spells from subclass features (entity_spells table).
     */
    private function getExpectedSpellsFromFeatures(CharacterClass $subclass, int $characterLevel): array
    {
        $featureIds = $subclass->features()
            ->where('level', '<=', $characterLevel)
            ->whereNull('parent_feature_id')
            ->pluck('id')
            ->toArray();

        if (empty($featureIds)) {
            return [];
        }

        return DB::table('entity_spells')
            ->join('spells', 'entity_spells.spell_id', '=', 'spells.id')
            ->where('entity_spells.reference_type', ClassFeature::class)
            ->whereIn('entity_spells.reference_id', $featureIds)
            ->where(function ($query) use ($characterLevel) {
                $query->where('entity_spells.level_requirement', '<=', $characterLevel)
                    ->orWhereNull('entity_spells.level_requirement');
            })
            ->pluck('spells.slug')
            ->toArray();
    }

    /**
     * Get expected spells from subclass class_spells table.
     */
    private function getExpectedSpellsFromClass(CharacterClass $subclass, int $characterLevel): array
    {
        return DB::table('class_spells')
            ->join('spells', 'class_spells.spell_id', '=', 'spells.id')
            ->where('class_spells.class_id', $subclass->id)
            ->where(function ($query) use ($characterLevel) {
                $query->where('class_spells.level_learned', '<=', $characterLevel)
                    ->orWhereNull('class_spells.level_learned');
            })
            ->pluck('spells.slug')
            ->toArray();
    }

    /**
     * Get expected proficiencies from subclass features.
     */
    private function getExpectedProficienciesFromFeatures(CharacterClass $subclass, int $characterLevel): array
    {
        $featureIds = $subclass->features()
            ->where('level', '<=', $characterLevel)
            ->whereNull('parent_feature_id')
            ->pluck('id')
            ->toArray();

        if (empty($featureIds)) {
            return [];
        }

        $proficiencies = DB::table('entity_proficiencies')
            ->leftJoin('proficiency_types', 'entity_proficiencies.proficiency_type_id', '=', 'proficiency_types.id')
            ->leftJoin('skills', 'entity_proficiencies.skill_id', '=', 'skills.id')
            ->where('entity_proficiencies.reference_type', ClassFeature::class)
            ->whereIn('entity_proficiencies.reference_id', $featureIds)
            ->select([
                'entity_proficiencies.proficiency_name as name',
                'entity_proficiencies.proficiency_type as type',
                'proficiency_types.slug as type_slug',
                'skills.slug as skill_slug',
            ])
            ->get();

        return $proficiencies->map(function ($prof) {
            return [
                'name' => $prof->name ?? $prof->type ?? 'unknown',
                'slug' => $prof->type_slug ?? $prof->skill_slug,
            ];
        })->toArray();
    }
}
