<?php

namespace App\Services\Importers\Concerns;

use App\Models\EntityLanguage;
use App\Models\Language;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for importing languages (fixed, unrestricted choices, and restricted choices).
 *
 * Handles the common pattern of:
 * 1. Clear existing languages
 * 2. Look up languages by slug or use provided ID
 * 3. Create EntityLanguage records with polymorphic reference
 * 4. Support unrestricted choices (language_id = null, is_choice = true)
 * 5. Support restricted choices (language_id set, is_choice = true, choice_group/choice_option set)
 */
trait ImportsLanguages
{
    /**
     * Import languages for an entity.
     *
     * Clears existing languages and creates new EntityLanguage records.
     * Supports three data formats:
     * - Fixed language: { 'language_id' => 1, 'is_choice' => false }
     *   OR { 'language_slug' => 'common', 'is_choice' => false }
     * - Unrestricted choice: { 'is_choice' => true, 'quantity' => 2 }
     * - Restricted choice: { 'language_id' => 1, 'is_choice' => true, 'choice_group' => 'lang_choice_1', 'choice_option' => 1 }
     *
     * @param  Model  $entity  The entity (Race, Background, Class, etc.)
     * @param  array  $languagesData  Array of language data
     */
    protected function importEntityLanguages(Model $entity, array $languagesData): void
    {
        // Clear existing languages for this entity
        $entity->languages()->delete();

        foreach ($languagesData as $langData) {
            $isChoice = $langData['is_choice'] ?? false;
            $choiceGroup = $langData['choice_group'] ?? null;
            $choiceOption = $langData['choice_option'] ?? null;
            $languageId = null;

            // Determine language_id from various sources
            if (isset($langData['language_id']) && $langData['language_id'] !== null) {
                $languageId = $langData['language_id'];
            } elseif (isset($langData['language_slug']) && $langData['language_slug'] !== null) {
                $language = Language::where('slug', $langData['language_slug'])->first();
                $languageId = $language?->id;
            } elseif (isset($langData['slug']) && $langData['slug'] !== null) {
                // Legacy format support
                $language = Language::where('slug', $langData['slug'])->first();
                $languageId = $language?->id;
            }

            // For unrestricted/conditional choices (no choice_group), language_id should be null
            if ($isChoice && $choiceGroup === null && $languageId === null) {
                $data = [
                    'reference_type' => get_class($entity),
                    'reference_id' => $entity->id,
                    'language_id' => null,
                    'is_choice' => true,
                    'choice_group' => null,
                    'choice_option' => null,
                    'quantity' => $langData['quantity'] ?? 1,
                ];

                // Add condition fields if present (e.g., "already_knows" Dwarvish)
                if (isset($langData['condition_type'])) {
                    $data['condition_type'] = $langData['condition_type'];

                    // Resolve condition_language_id from ID or slug
                    if (isset($langData['condition_language_id']) && $langData['condition_language_id'] !== null) {
                        $data['condition_language_id'] = $langData['condition_language_id'];
                    } elseif (isset($langData['condition_language_slug']) && $langData['condition_language_slug'] !== null) {
                        $conditionLang = Language::where('slug', $langData['condition_language_slug'])->first();
                        $data['condition_language_id'] = $conditionLang?->id;
                    }
                }

                EntityLanguage::create($data);

                continue;
            }

            // For restricted choices (has choice_group), language_id should be set
            if ($isChoice && $choiceGroup !== null) {
                if ($languageId === null) {
                    // Skip if we can't resolve the language for a restricted choice
                    continue;
                }

                $data = [
                    'reference_type' => get_class($entity),
                    'reference_id' => $entity->id,
                    'language_id' => $languageId,
                    'is_choice' => true,
                    'choice_group' => $choiceGroup,
                    'choice_option' => $choiceOption,
                ];

                // Only first option in group has quantity (others use default)
                if (isset($langData['quantity']) && $langData['quantity'] !== null) {
                    $data['quantity'] = $langData['quantity'];
                }

                EntityLanguage::create($data);

                continue;
            }

            // For fixed languages, language_id must be set
            if (! $isChoice) {
                if ($languageId === null) {
                    // Skip if we can't resolve the language
                    continue;
                }

                EntityLanguage::create([
                    'reference_type' => get_class($entity),
                    'reference_id' => $entity->id,
                    'language_id' => $languageId,
                    'is_choice' => false,
                    'choice_group' => null,
                    'choice_option' => null,
                    'quantity' => $langData['quantity'] ?? 1,
                ]);
            }
        }
    }
}
