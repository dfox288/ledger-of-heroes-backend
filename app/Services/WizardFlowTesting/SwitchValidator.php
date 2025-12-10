<?php

declare(strict_types=1);

namespace App\Services\WizardFlowTesting;

/**
 * Validates that character state is correct after a switch operation.
 * This is the critical piece that detects bugs in cascade/reset logic.
 */
class SwitchValidator
{
    /**
     * Validate a switch operation.
     */
    public function validate(
        string $switchType,
        array $before,
        array $after,
        ?string $equipmentMode = null
    ): ValidationResult {
        return match ($switchType) {
            'switch_race' => $this->validateRaceSwitch($before, $after, $equipmentMode),
            'switch_background' => $this->validateBackgroundSwitch($before, $after, $equipmentMode),
            'switch_class' => $this->validateClassSwitch($before, $after, $equipmentMode),
            default => ValidationResult::pass(),
        };
    }

    /**
     * Validate race switch cascade behavior.
     */
    private function validateRaceSwitch(array $before, array $after, ?string $equipmentMode): ValidationResult
    {
        $errors = [];
        $warnings = [];
        $pattern = null;

        $beforeDerived = $before['derived'] ?? [];
        $afterDerived = $after['derived'] ?? [];

        // 1. Race should have changed
        if ($beforeDerived['race_slug'] === $afterDerived['race_slug']) {
            $errors[] = 'Race did not change';
            $pattern = 'race_not_changed';
        }

        // 2. Racial spells should be cleared
        $beforeRacialSpells = $before['derived']['spells_by_source']['race'] ?? [];
        $afterRacialSpells = $after['derived']['spells_by_source']['race'] ?? [];

        // Old racial spells should not persist
        $persistingRacialSpells = array_intersect($beforeRacialSpells, $afterRacialSpells);
        if (! empty($persistingRacialSpells)) {
            $errors[] = 'Racial spells not cleared after race switch: '.implode(', ', $persistingRacialSpells);
            $pattern = $pattern ?? 'racial_spells_not_cleared';
        }

        // 3. Racial features should be cleared
        $beforeRacialFeatures = $before['derived']['features_by_source']['race'] ?? [];
        $afterRacialFeatures = $after['derived']['features_by_source']['race'] ?? [];

        $persistingRacialFeatures = array_intersect($beforeRacialFeatures, $afterRacialFeatures);
        if (! empty($persistingRacialFeatures)) {
            $errors[] = 'Racial features not cleared after race switch: '.implode(', ', $persistingRacialFeatures);
            $pattern = $pattern ?? 'racial_features_not_cleared';
        }

        // 4. Check for language issues
        // This is tricky - we need to know which languages came from race vs background
        // For now, check that language count changed or new choices appeared
        $beforeLanguageCount = $beforeDerived['language_count'] ?? 0;
        $afterLanguageCount = $afterDerived['language_count'] ?? 0;
        $afterChoiceTypes = $afterDerived['pending_choice_types'] ?? [];

        // If old race granted languages and new race does too, count might stay same
        // But if race granted specific languages, those should be gone
        // This is a soft check - we warn rather than fail
        if ($beforeLanguageCount === $afterLanguageCount && ! in_array('language', $afterChoiceTypes)) {
            $warnings[] = 'Language count unchanged after race switch - verify racial languages were properly replaced';
        }

        // 5. Speed should update if races have different speeds
        // This is informational - both races might have same speed
        if ($beforeDerived['speed'] === $afterDerived['speed']) {
            $warnings[] = 'Speed unchanged after race switch - may be expected if both races have same speed';
        }

        // 6. Size should update if races have different sizes
        if ($beforeDerived['size'] === $afterDerived['size']) {
            $warnings[] = 'Size unchanged after race switch - may be expected if both races have same size';
        }

        if (! empty($errors)) {
            return ValidationResult::fail($errors, $pattern);
        }

        if (! empty($warnings)) {
            return ValidationResult::passWithWarnings($warnings);
        }

        return ValidationResult::pass();
    }

    /**
     * Validate background switch cascade behavior.
     */
    private function validateBackgroundSwitch(array $before, array $after, ?string $equipmentMode): ValidationResult
    {
        $errors = [];
        $warnings = [];
        $pattern = null;

        $beforeDerived = $before['derived'] ?? [];
        $afterDerived = $after['derived'] ?? [];

        // 1. Background should have changed
        if ($beforeDerived['background_slug'] === $afterDerived['background_slug']) {
            $errors[] = 'Background did not change';
            $pattern = 'background_not_changed';
        }

        // 2. Background features should be cleared
        $beforeBgFeatures = $before['derived']['features_by_source']['background'] ?? [];
        $afterBgFeatures = $after['derived']['features_by_source']['background'] ?? [];

        $persistingBgFeatures = array_intersect($beforeBgFeatures, $afterBgFeatures);
        if (! empty($persistingBgFeatures)) {
            $errors[] = 'Background features not cleared: '.implode(', ', $persistingBgFeatures);
            $pattern = $pattern ?? 'background_features_not_cleared';
        }

        // 3. Equipment mode affects equipment reset
        if ($equipmentMode === 'equipment') {
            // In equipment mode, background equipment choices should reset
            $beforeEquipment = $beforeDerived['equipment_slugs'] ?? [];
            $afterEquipment = $afterDerived['equipment_slugs'] ?? [];

            // Equipment count should change or new equipment choices should be pending
            $afterChoiceTypes = $afterDerived['pending_choice_types'] ?? [];
            if ($beforeEquipment === $afterEquipment && ! in_array('equipment', $afterChoiceTypes)) {
                $warnings[] = 'Equipment unchanged after background switch in equipment mode - verify background equipment was reset';
            }
        }

        // 4. Background proficiencies should reset
        // This is complex - need to track which proficiencies came from background
        // For now, check if proficiency choices are pending
        $afterChoiceTypes = $afterDerived['pending_choice_types'] ?? [];
        // Soft check - new background might not grant proficiency choices
        // Just log a warning if proficiency count is identical

        if (! empty($errors)) {
            return ValidationResult::fail($errors, $pattern);
        }

        if (! empty($warnings)) {
            return ValidationResult::passWithWarnings($warnings);
        }

        return ValidationResult::pass();
    }

    /**
     * Validate class switch cascade behavior.
     */
    private function validateClassSwitch(array $before, array $after, ?string $equipmentMode): ValidationResult
    {
        $errors = [];
        $warnings = [];
        $pattern = null;

        $beforeDerived = $before['derived'] ?? [];
        $afterDerived = $after['derived'] ?? [];

        // 1. Class should have changed
        $beforeClassSlugs = $beforeDerived['class_slugs'] ?? [];
        $afterClassSlugs = $afterDerived['class_slugs'] ?? [];

        if ($beforeClassSlugs === $afterClassSlugs) {
            $errors[] = 'Class did not change';
            $pattern = 'class_not_changed';
        }

        // 2. Class spells should be cleared
        $beforeClassSpells = $before['derived']['spells_by_source']['class'] ?? [];
        $afterClassSpells = $after['derived']['spells_by_source']['class'] ?? [];

        $persistingClassSpells = array_intersect($beforeClassSpells, $afterClassSpells);
        if (! empty($persistingClassSpells)) {
            $errors[] = 'Class spells not cleared after class switch: '.implode(', ', $persistingClassSpells);
            $pattern = $pattern ?? 'class_spells_not_cleared';
        }

        // 3. Class features should be cleared
        $beforeClassFeatures = $before['derived']['features_by_source']['class'] ?? [];
        $afterClassFeatures = $after['derived']['features_by_source']['class'] ?? [];

        $persistingClassFeatures = array_intersect($beforeClassFeatures, $afterClassFeatures);
        if (! empty($persistingClassFeatures)) {
            $errors[] = 'Class features not cleared after class switch: '.implode(', ', $persistingClassFeatures);
            $pattern = $pattern ?? 'class_features_not_cleared';
        }

        // 4. Spellcasting should update
        $beforeSpellcasting = $beforeDerived['spellcasting'] ?? null;
        $afterSpellcasting = $afterDerived['spellcasting'] ?? null;

        // If switching from spellcaster to non-spellcaster, spellcasting should be null
        // If switching between spellcasters, ability should change
        if ($beforeSpellcasting !== null && $afterSpellcasting !== null) {
            if ($beforeSpellcasting === $afterSpellcasting) {
                $warnings[] = 'Spellcasting unchanged after class switch - verify this is expected';
            }
        }

        // 5. Equipment mode affects equipment reset
        if ($equipmentMode === 'equipment') {
            $beforeEquipment = $beforeDerived['equipment_slugs'] ?? [];
            $afterEquipment = $afterDerived['equipment_slugs'] ?? [];

            $afterChoiceTypes = $afterDerived['pending_choice_types'] ?? [];
            if ($beforeEquipment === $afterEquipment && ! in_array('equipment', $afterChoiceTypes)) {
                $warnings[] = 'Equipment unchanged after class switch in equipment mode - verify class equipment was reset';
            }
        }

        // 6. Saving throw proficiencies should update (different classes have different saves)
        // This is informational as both classes might have same save proficiencies
        $beforeSaves = $before['stats']['data']['saving_throws'] ?? [];
        $afterSaves = $after['stats']['data']['saving_throws'] ?? [];

        $beforeProficient = array_keys(array_filter($beforeSaves, fn ($s) => $s['proficient'] ?? false));
        $afterProficient = array_keys(array_filter($afterSaves, fn ($s) => $s['proficient'] ?? false));

        if ($beforeProficient === $afterProficient) {
            $warnings[] = 'Saving throw proficiencies unchanged after class switch';
        }

        if (! empty($errors)) {
            return ValidationResult::fail($errors, $pattern);
        }

        if (! empty($warnings)) {
            return ValidationResult::passWithWarnings($warnings);
        }

        return ValidationResult::pass();
    }
}
