<?php

namespace App\Services;

use App\DTOs\CharacterImportResult;
use App\Enums\AbilityScoreMethod;
use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterAbilityScore;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterCondition;
use App\Models\CharacterEquipment;
use App\Models\CharacterFeature;
use App\Models\CharacterLanguage;
use App\Models\CharacterNote;
use App\Models\CharacterProficiency;
use App\Models\CharacterSpell;
use App\Models\CharacterSpellSlot;
use App\Models\ClassFeature;
use App\Models\Condition;
use App\Models\FeatureSelection;
use App\Models\Item;
use App\Models\Language;
use App\Models\OptionalFeature;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Models\Skill;
use App\Models\Spell;
use App\Support\NoteCategories;
use Illuminate\Support\Facades\DB;

/**
 * Service for importing characters from portable JSON.
 *
 * Creates a character from exported data, handling dangling references
 * gracefully and reporting warnings for missing entities.
 */
class CharacterImportService
{
    /**
     * @var array<string>
     */
    private array $warnings = [];

    /**
     * Import a character from export data.
     */
    public function import(array $data): CharacterImportResult
    {
        $this->warnings = [];
        $characterData = $data['character'];

        $character = DB::transaction(function () use ($characterData) {
            // Create base character
            $character = $this->createCharacter($characterData);

            // Import related data
            $this->importClasses($character, $characterData['classes'] ?? []);
            $this->importSpells($character, $characterData['spells'] ?? []);
            $this->importEquipment($character, $characterData['equipment'] ?? []);
            $this->importLanguages($character, $characterData['languages'] ?? []);
            $this->importProficiencies($character, $characterData['proficiencies'] ?? []);
            $this->importConditions($character, $characterData['conditions'] ?? []);
            $this->importFeatureSelections($character, $characterData['feature_selections'] ?? []);
            $this->importNotes($character, $characterData['notes'] ?? []);
            $this->importAbilityScoreChoices($character, $characterData['ability_score_choices'] ?? []);
            $this->importSpellSlots($character, $characterData['spell_slots'] ?? []);
            $this->importFeatures($character, $characterData['features'] ?? []);
            $this->importPortrait($character, $characterData['portrait'] ?? null);

            return $character;
        });

        return new CharacterImportResult($character, $this->warnings);
    }

    private function createCharacter(array $data): Character
    {
        // Handle public_id conflict
        $publicId = $data['public_id'];
        if (Character::where('public_id', $publicId)->exists()) {
            $publicId = $this->generateUniquePublicId();
        }

        // Check for dangling race reference
        if ($data['race'] && ! Race::where('slug', $data['race'])->exists()) {
            $this->warnings[] = "Race '{$data['race']}' not found in database - reference preserved but may not resolve";
        }

        // Check for dangling background reference
        if ($data['background'] && ! Background::where('slug', $data['background'])->exists()) {
            $this->warnings[] = "Background '{$data['background']}' not found in database - reference preserved but may not resolve";
        }

        $abilityScores = $data['ability_scores'] ?? [];

        return Character::create([
            'public_id' => $publicId,
            'name' => $data['name'],
            'race_slug' => $data['race'],
            'background_slug' => $data['background'],
            'alignment' => $data['alignment'],
            'strength' => $abilityScores['strength'] ?? null,
            'dexterity' => $abilityScores['dexterity'] ?? null,
            'constitution' => $abilityScores['constitution'] ?? null,
            'intelligence' => $abilityScores['intelligence'] ?? null,
            'wisdom' => $abilityScores['wisdom'] ?? null,
            'charisma' => $abilityScores['charisma'] ?? null,
            'experience_points' => $data['experience_points'] ?? 0,
            'has_inspiration' => $data['has_inspiration'] ?? false,
            'ability_score_method' => isset($data['ability_score_method'])
                ? AbilityScoreMethod::tryFrom($data['ability_score_method'])
                : AbilityScoreMethod::Manual,
            'max_hit_points' => $data['max_hit_points'] ?? null,
            'current_hit_points' => $data['current_hit_points'] ?? null,
            'temp_hit_points' => $data['temp_hit_points'] ?? 0,
            'death_save_successes' => $data['death_save_successes'] ?? 0,
            'death_save_failures' => $data['death_save_failures'] ?? 0,
            // New fields in v1.1
            'equipment_mode' => $data['equipment_mode'] ?? 'equipment',
            'size_id' => $data['size_id'] ?? null,
            'asi_choices_remaining' => $data['asi_choices_remaining'] ?? 0,
            'hp_levels_resolved' => $data['hp_levels_resolved'] ?? [],
            'hp_calculation_method' => $data['hp_calculation_method'] ?? 'calculated',
        ]);
    }

    private function importClasses(Character $character, array $classes): void
    {
        $order = 1;
        foreach ($classes as $classData) {
            $classSlug = $classData['class'];
            $subclassSlug = $classData['subclass'] ?? null;

            // Check for dangling class reference
            if ($classSlug && ! CharacterClass::where('slug', $classSlug)->exists()) {
                $this->warnings[] = "Class '{$classSlug}' not found in database - reference preserved but may not resolve";
            }

            // Check for dangling subclass reference
            if ($subclassSlug && ! CharacterClass::where('slug', $subclassSlug)->exists()) {
                $this->warnings[] = "Subclass '{$subclassSlug}' not found in database - reference preserved but may not resolve";
            }

            CharacterClassPivot::create([
                'character_id' => $character->id,
                'class_slug' => $classSlug,
                'subclass_slug' => $subclassSlug,
                'level' => $classData['level'] ?? 1,
                'is_primary' => $classData['is_primary'] ?? ($order === 1),
                'order' => $order,
                'hit_dice_spent' => $classData['hit_dice_spent'] ?? 0,
            ]);

            $order++;
        }
    }

    private function importSpells(Character $character, array $spells): void
    {
        foreach ($spells as $spellData) {
            $spellSlug = $spellData['spell'];

            // Check for dangling spell reference
            if ($spellSlug && ! Spell::where('slug', $spellSlug)->exists()) {
                $this->warnings[] = "Spell '{$spellSlug}' not found in database - reference preserved but may not resolve";
            }

            CharacterSpell::create([
                'character_id' => $character->id,
                'spell_slug' => $spellSlug,
                'source' => $spellData['source'] ?? 'class',
                'preparation_status' => $spellData['preparation_status'] ?? 'known',
                'level_acquired' => $spellData['level_acquired'] ?? null,
            ]);
        }
    }

    private function importEquipment(Character $character, array $equipment): void
    {
        foreach ($equipment as $itemData) {
            $itemSlug = $itemData['item'] ?? null;

            // Check for dangling item reference (only for non-custom items)
            if ($itemSlug && ! Item::where('slug', $itemSlug)->exists()) {
                $this->warnings[] = "Item '{$itemSlug}' not found in database - reference preserved but may not resolve";
            }

            CharacterEquipment::create([
                'character_id' => $character->id,
                'item_slug' => $itemSlug,
                'custom_name' => $itemData['custom_name'] ?? null,
                'custom_description' => $itemData['custom_description'] ?? null,
                'quantity' => $itemData['quantity'] ?? 1,
                'equipped' => $itemData['equipped'] ?? false,
                'location' => $itemData['location'] ?? 'backpack',
            ]);
        }
    }

    private function importLanguages(Character $character, array $languages): void
    {
        foreach ($languages as $langData) {
            $languageSlug = $langData['language'];

            // Check for dangling language reference
            if ($languageSlug && ! Language::where('slug', $languageSlug)->exists()) {
                $this->warnings[] = "Language '{$languageSlug}' not found in database - reference preserved but may not resolve";
            }

            CharacterLanguage::create([
                'character_id' => $character->id,
                'language_slug' => $languageSlug,
                'source' => $langData['source'] ?? 'manual',
            ]);
        }
    }

    private function importProficiencies(Character $character, array $proficiencies): void
    {
        // Import skill proficiencies
        foreach ($proficiencies['skills'] ?? [] as $skillData) {
            $skillSlug = $skillData['skill'];

            // Check for dangling skill reference
            if ($skillSlug && ! Skill::where('slug', $skillSlug)->exists()) {
                $this->warnings[] = "Skill '{$skillSlug}' not found in database - reference preserved but may not resolve";
            }

            CharacterProficiency::create([
                'character_id' => $character->id,
                'skill_slug' => $skillSlug,
                'proficiency_type_slug' => null,
                'source' => $skillData['source'] ?? 'manual',
                'expertise' => $skillData['expertise'] ?? false,
                'choice_group' => $skillData['choice_group'] ?? null,
            ]);
        }

        // Import type proficiencies
        foreach ($proficiencies['types'] ?? [] as $typeData) {
            $typeSlug = $typeData['type'];

            // Check for dangling proficiency type reference
            if ($typeSlug && ! ProficiencyType::where('slug', $typeSlug)->exists()) {
                $this->warnings[] = "Proficiency type '{$typeSlug}' not found in database - reference preserved but may not resolve";
            }

            CharacterProficiency::create([
                'character_id' => $character->id,
                'skill_slug' => null,
                'proficiency_type_slug' => $typeSlug,
                'source' => $typeData['source'] ?? 'manual',
                'expertise' => $typeData['expertise'] ?? false,
                'choice_group' => $typeData['choice_group'] ?? null,
            ]);
        }
    }

    private function importConditions(Character $character, array $conditions): void
    {
        foreach ($conditions as $condData) {
            $conditionSlug = $condData['condition'];

            // Check for dangling condition reference
            if ($conditionSlug && ! Condition::where('slug', $conditionSlug)->exists()) {
                $this->warnings[] = "Condition '{$conditionSlug}' not found in database - reference preserved but may not resolve";
            }

            CharacterCondition::create([
                'character_id' => $character->id,
                'condition_slug' => $conditionSlug,
                'level' => $condData['level'] ?? null,
                'source' => $condData['source'] ?? null,
                'duration' => $condData['duration'] ?? null,
            ]);
        }
    }

    private function importFeatureSelections(Character $character, array $featureSelections): void
    {
        foreach ($featureSelections as $fsData) {
            $featureSlug = $fsData['feature'];
            $classSlug = $fsData['class'] ?? null;

            // Check for dangling feature reference
            if ($featureSlug && ! OptionalFeature::where('slug', $featureSlug)->exists()) {
                $this->warnings[] = "Optional feature '{$featureSlug}' not found in database - reference preserved but may not resolve";
            }

            // Check for dangling class reference
            if ($classSlug && ! CharacterClass::where('slug', $classSlug)->exists()) {
                $this->warnings[] = "Class '{$classSlug}' not found in database - reference preserved but may not resolve";
            }

            FeatureSelection::create([
                'character_id' => $character->id,
                'optional_feature_slug' => $featureSlug,
                'class_slug' => $classSlug,
                'subclass_name' => $fsData['subclass_name'] ?? null,
                'level_acquired' => $fsData['level_acquired'] ?? null,
                'uses_remaining' => $fsData['uses_remaining'] ?? null,
                'max_uses' => $fsData['max_uses'] ?? null,
            ]);
        }
    }

    private function importNotes(Character $character, array $notes): void
    {
        foreach ($notes as $noteData) {
            $category = $noteData['category'] ?? null;

            if (empty($category)) {
                $this->warnings[] = 'Note missing category - skipping note';

                continue;
            }

            if (strlen($category) > 50) {
                $this->warnings[] = "Note category '{$category}' exceeds 50 characters - skipping note";

                continue;
            }

            $title = $noteData['title'] ?? null;

            if (NoteCategories::requiresTitle($category) && empty($title)) {
                $this->warnings[] = "Note category '{$category}' requires a title - skipping note";

                continue;
            }

            CharacterNote::create([
                'character_id' => $character->id,
                'category' => $category,
                'title' => $title,
                'content' => $noteData['content'] ?? '',
                'sort_order' => $noteData['sort_order'] ?? 0,
            ]);
        }
    }

    private function generateUniquePublicId(): string
    {
        $adjectives = [
            'ancient', 'arcane', 'bold', 'brave', 'crimson', 'dark', 'divine',
            'elder', 'eternal', 'fallen', 'fierce', 'frozen', 'golden', 'grim',
        ];

        $nouns = [
            'archer', 'bard', 'blade', 'cleric', 'dragon', 'druid', 'falcon',
            'flame', 'fury', 'guardian', 'hawk', 'herald', 'hunter', 'knight',
        ];

        $attempts = 0;
        $maxAttempts = 100;

        do {
            if (++$attempts > $maxAttempts) {
                throw new \RuntimeException("Unable to generate unique public_id after {$maxAttempts} attempts");
            }

            $adjective = $adjectives[array_rand($adjectives)];
            $noun = $nouns[array_rand($nouns)];
            $suffix = $this->generateSuffix();
            $publicId = "{$adjective}-{$noun}-{$suffix}";
        } while (Character::where('public_id', $publicId)->exists());

        return $publicId;
    }

    private function generateSuffix(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $suffix = '';
        for ($i = 0; $i < 4; $i++) {
            $suffix .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $suffix;
    }

    private function importAbilityScoreChoices(Character $character, array $choices): void
    {
        foreach ($choices as $choice) {
            CharacterAbilityScore::create([
                'character_id' => $character->id,
                'ability_score_code' => $choice['ability_score_code'],
                'bonus' => $choice['bonus'],
                'source' => $choice['source'] ?? 'manual',
                'choice_group' => $choice['choice_group'] ?? null,
            ]);
        }
    }

    private function importSpellSlots(Character $character, array $slots): void
    {
        foreach ($slots as $slot) {
            CharacterSpellSlot::create([
                'character_id' => $character->id,
                'spell_level' => $slot['spell_level'],
                'max_slots' => $slot['max_slots'],
                'used_slots' => $slot['used_slots'] ?? 0,
                'slot_type' => $slot['slot_type'] ?? 'standard',
            ]);
        }
    }

    private function importFeatures(Character $character, array $features): void
    {
        foreach ($features as $featureData) {
            $portableId = $featureData['portable_id'] ?? null;
            $featureType = $featureData['feature_type'];
            $featureId = null;

            // Try to resolve the portable ID to an actual feature
            if ($portableId) {
                $featureId = $this->resolveFeatureId($featureType, $portableId);
            }

            if (! $featureId && $portableId) {
                $featureName = $portableId['feature_name'] ?? 'unknown';
                $this->warnings[] = "Feature '{$featureName}' not found - skipping";

                continue;
            }

            CharacterFeature::create([
                'character_id' => $character->id,
                'feature_type' => $featureType,
                'feature_id' => $featureId,
                'source' => $featureData['source'] ?? 'class',
                'level_acquired' => $featureData['level_acquired'] ?? null,
                'uses_remaining' => $featureData['uses_remaining'] ?? null,
                'max_uses' => $featureData['max_uses'] ?? null,
            ]);
        }
    }

    /**
     * Resolve a portable feature ID to an actual database feature ID.
     */
    private function resolveFeatureId(string $featureType, array $portableId): ?int
    {
        $type = $portableId['type'] ?? null;

        return match ($type) {
            'class_feature' => $this->resolveClassFeatureId($portableId),
            'racial_trait' => $this->resolveRacialTraitId($portableId),
            'character_trait' => $this->resolveCharacterTraitId($portableId),
            default => null,
        };
    }

    private function resolveClassFeatureId(array $portableId): ?int
    {
        $classSlug = $portableId['class_slug'] ?? null;
        $featureName = $portableId['feature_name'] ?? null;
        $level = $portableId['level'] ?? null;

        if (! $classSlug || ! $featureName || $level === null) {
            return null;
        }

        // Find the class by slug
        $class = CharacterClass::where('slug', $classSlug)->first();
        if (! $class) {
            return null;
        }

        // Find the feature by class_id, name, and level
        $feature = ClassFeature::where('class_id', $class->id)
            ->where('feature_name', $featureName)
            ->where('level', $level)
            ->first();

        return $feature?->id;
    }

    private function resolveRacialTraitId(array $portableId): ?int
    {
        // TODO: Implement racial trait resolution when RacialTrait model has proper lookup
        // For now, return null and add a warning
        $name = $portableId['name'] ?? 'unknown';
        $this->warnings[] = "Racial trait '{$name}' import not yet supported";

        return null;
    }

    /**
     * Resolve a CharacterTrait portable ID to an actual database ID.
     *
     * CharacterTraits are identified by entity type (race/background), entity slug, and trait name.
     */
    private function resolveCharacterTraitId(array $portableId): ?int
    {
        $entityType = $portableId['entity_type'] ?? null;
        $entitySlug = $portableId['entity_slug'] ?? null;
        $traitName = $portableId['trait_name'] ?? null;

        if (! $entityType || ! $entitySlug || ! $traitName) {
            return null;
        }

        // Determine the entity model class
        $entityClass = match ($entityType) {
            'race' => Race::class,
            'background' => Background::class,
            'class' => CharacterClass::class,
            default => null,
        };

        if (! $entityClass) {
            return null;
        }

        // Find the parent entity
        $entity = $entityClass::where('slug', $entitySlug)->first();
        if (! $entity) {
            return null;
        }

        // Find the trait by parent entity and name
        $trait = \App\Models\CharacterTrait::where('reference_type', $entityClass)
            ->where('reference_id', $entity->id)
            ->where('name', $traitName)
            ->first();

        return $trait?->id;
    }

    /**
     * Import portrait from base64 data.
     */
    private function importPortrait(Character $character, ?array $portraitData): void
    {
        if (! $portraitData) {
            return;
        }

        $filename = $portraitData['filename'] ?? 'portrait.png';
        $mimeType = $portraitData['mime_type'] ?? 'image/png';
        $base64Data = $portraitData['data'] ?? null;

        if (! $base64Data) {
            $this->warnings[] = 'Portrait data is empty - skipping portrait import';

            return;
        }

        try {
            $character->addMediaFromBase64($base64Data)
                ->usingFileName($filename)
                ->toMediaCollection('portrait');
        } catch (\Exception $e) {
            $this->warnings[] = 'Failed to import portrait: '.$e->getMessage();
        }
    }
}
