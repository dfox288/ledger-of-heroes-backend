<?php

namespace App\Services\Importers\Concerns;

use App\Models\EntityChoice;
use App\Models\EntityLanguage;
use App\Models\Language;
use App\Services\Parsers\Traits\ParsesChoices;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for importing languages (fixed and choice-based).
 *
 * Handles the common pattern of:
 * 1. Clear existing languages and language choices
 * 2. Look up languages by slug or use provided ID
 * 3. Create EntityLanguage records for fixed languages
 * 4. Create EntityChoice records for language choices (unrestricted or restricted)
 */
trait ImportsLanguages
{
    use ParsesChoices;

    /**
     * Import languages for an entity.
     *
     * Clears existing languages and creates new records.
     * - Fixed languages go to EntityLanguage table
     * - Choice-based languages go to EntityChoice table
     *
     * Supports three data formats:
     * - Fixed language: { 'language_id' => 1, 'is_choice' => false }
     *   OR { 'language_slug' => 'common', 'is_choice' => false }
     *   OR { 'slug' => 'common', 'is_choice' => false }
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

        // Clear existing language choices for this entity
        EntityChoice::where('reference_type', get_class($entity))
            ->where('reference_id', $entity->id)
            ->where('choice_type', 'language')
            ->delete();

        // Track unrestricted choice count for unique group names
        $unrestrictedChoiceIndex = 0;

        foreach ($languagesData as $langData) {
            $isChoice = $langData['is_choice'] ?? false;
            $choiceGroup = $langData['choice_group'] ?? null;
            $choiceOption = $langData['choice_option'] ?? null;
            $languageSlug = null;

            // Determine language slug from various sources
            if (isset($langData['slug']) && $langData['slug'] !== null) {
                $languageSlug = $langData['slug'];
            } elseif (isset($langData['language_slug']) && $langData['language_slug'] !== null) {
                $languageSlug = $langData['language_slug'];
            } elseif (isset($langData['language_id']) && $langData['language_id'] !== null) {
                // Look up slug from ID
                $language = Language::find($langData['language_id']);
                $languageSlug = $language?->slug;
            }

            // Handle choice-based languages (create EntityChoice records)
            if ($isChoice) {
                // Unrestricted choice (no specific language, just quantity)
                if ($choiceGroup === null && $languageSlug === null) {
                    $quantity = $langData['quantity'] ?? 1;

                    // Build constraints if condition is present
                    $constraints = null;
                    if (isset($langData['condition_type'])) {
                        $constraints = ['condition_type' => $langData['condition_type']];

                        // Resolve condition language slug
                        if (isset($langData['condition_language_id'])) {
                            $conditionLang = Language::find($langData['condition_language_id']);
                            $constraints['condition_language_slug'] = $conditionLang?->slug;
                        } elseif (isset($langData['condition_language_slug'])) {
                            $constraints['condition_language_slug'] = $langData['condition_language_slug'];
                        }
                    }

                    // Create one EntityChoice record per unrestricted slot
                    // Each slot gets its own unique group name
                    for ($i = 0; $i < $quantity; $i++) {
                        $unrestrictedChoiceIndex++;
                        $this->createLanguageChoice(
                            referenceType: get_class($entity),
                            referenceId: $entity->id,
                            choiceGroup: 'language_choice_'.$unrestrictedChoiceIndex,
                            quantity: 1,
                            levelGranted: 1,
                            constraints: $constraints
                        );
                    }

                    continue;
                }

                // Restricted choice (specific languages to choose from)
                if ($choiceGroup !== null && $languageSlug !== null) {
                    $this->createRestrictedLanguageChoice(
                        referenceType: get_class($entity),
                        referenceId: $entity->id,
                        choiceGroup: $choiceGroup,
                        languageSlug: $languageSlug,
                        choiceOption: $choiceOption ?? 1,
                        quantity: $langData['quantity'] ?? 1,
                        levelGranted: 1
                    );

                    continue;
                }

                // Skip invalid choice data
                continue;
            }

            // Handle fixed languages (create EntityLanguage records)
            if (! $isChoice && $languageSlug !== null) {
                $language = Language::where('slug', $languageSlug)->first();
                if ($language) {
                    EntityLanguage::create([
                        'reference_type' => get_class($entity),
                        'reference_id' => $entity->id,
                        'language_id' => $language->id,
                    ]);
                }
            }
        }
    }
}
