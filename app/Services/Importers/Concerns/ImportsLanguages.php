<?php

namespace App\Services\Importers\Concerns;

use App\Models\EntityLanguage;
use App\Models\Language;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for importing languages (both fixed languages and choice slots).
 *
 * Handles the common pattern of:
 * 1. Clear existing languages
 * 2. Look up languages by slug or use provided ID
 * 3. Create EntityLanguage records with polymorphic reference
 * 4. Support choice slots (language_id = null, is_choice = true)
 */
trait ImportsLanguages
{
    /**
     * Import languages for an entity.
     *
     * Clears existing languages and creates new EntityLanguage records.
     * Supports two data formats:
     * - Fixed language: { 'language_id' => 1, 'is_choice' => false } OR { 'slug' => 'common', 'is_choice' => false }
     * - Choice slot: { 'is_choice' => true }
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
            $languageId = null;

            // For choice slots, language_id is null
            if ($isChoice) {
                EntityLanguage::create([
                    'reference_type' => get_class($entity),
                    'reference_id' => $entity->id,
                    'language_id' => null,
                    'is_choice' => true,
                ]);

                continue;
            }

            // For fixed languages, determine language_id
            // Prefer explicit language_id over slug lookup
            if (isset($langData['language_id'])) {
                $languageId = $langData['language_id'];
            } elseif (isset($langData['slug'])) {
                $language = Language::where('slug', $langData['slug'])->first();

                if ($language) {
                    $languageId = $language->id;
                } else {
                    // Skip if language lookup fails
                    continue;
                }
            } else {
                // No way to determine language - skip
                continue;
            }

            EntityLanguage::create([
                'reference_type' => get_class($entity),
                'reference_id' => $entity->id,
                'language_id' => $languageId,
                'is_choice' => false,
            ]);
        }
    }
}
