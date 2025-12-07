<?php

namespace App\Services;

use App\DTOs\CharacterImportResult;
use App\Enums\AbilityScoreMethod;
use App\Enums\NoteCategory;
use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterCondition;
use App\Models\CharacterEquipment;
use App\Models\CharacterLanguage;
use App\Models\CharacterNote;
use App\Models\CharacterProficiency;
use App\Models\CharacterSpell;
use App\Models\Condition;
use App\Models\FeatureSelection;
use App\Models\Item;
use App\Models\Language;
use App\Models\OptionalFeature;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Models\Skill;
use App\Models\Spell;
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
        if ($data['race'] && ! Race::where('full_slug', $data['race'])->exists()) {
            $this->warnings[] = "Race '{$data['race']}' not found in database - reference preserved but may not resolve";
        }

        // Check for dangling background reference
        if ($data['background'] && ! Background::where('full_slug', $data['background'])->exists()) {
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
        ]);
    }

    private function importClasses(Character $character, array $classes): void
    {
        $order = 1;
        foreach ($classes as $classData) {
            $classSlug = $classData['class'];
            $subclassSlug = $classData['subclass'] ?? null;

            // Check for dangling class reference
            if ($classSlug && ! CharacterClass::where('full_slug', $classSlug)->exists()) {
                $this->warnings[] = "Class '{$classSlug}' not found in database - reference preserved but may not resolve";
            }

            // Check for dangling subclass reference
            if ($subclassSlug && ! CharacterClass::where('full_slug', $subclassSlug)->exists()) {
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
            if ($spellSlug && ! Spell::where('full_slug', $spellSlug)->exists()) {
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
            if ($itemSlug && ! Item::where('full_slug', $itemSlug)->exists()) {
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
            if ($languageSlug && ! Language::where('full_slug', $languageSlug)->exists()) {
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
            if ($skillSlug && ! Skill::where('full_slug', $skillSlug)->exists()) {
                $this->warnings[] = "Skill '{$skillSlug}' not found in database - reference preserved but may not resolve";
            }

            CharacterProficiency::create([
                'character_id' => $character->id,
                'skill_slug' => $skillSlug,
                'proficiency_type_slug' => null,
                'source' => $skillData['source'] ?? 'manual',
                'expertise' => $skillData['expertise'] ?? false,
            ]);
        }

        // Import type proficiencies
        foreach ($proficiencies['types'] ?? [] as $typeData) {
            $typeSlug = $typeData['type'];

            // Check for dangling proficiency type reference
            if ($typeSlug && ! ProficiencyType::where('full_slug', $typeSlug)->exists()) {
                $this->warnings[] = "Proficiency type '{$typeSlug}' not found in database - reference preserved but may not resolve";
            }

            CharacterProficiency::create([
                'character_id' => $character->id,
                'skill_slug' => null,
                'proficiency_type_slug' => $typeSlug,
                'source' => $typeData['source'] ?? 'manual',
                'expertise' => $typeData['expertise'] ?? false,
            ]);
        }
    }

    private function importConditions(Character $character, array $conditions): void
    {
        foreach ($conditions as $condData) {
            $conditionSlug = $condData['condition'];

            // Check for dangling condition reference
            if ($conditionSlug && ! Condition::where('full_slug', $conditionSlug)->exists()) {
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
            if ($featureSlug && ! OptionalFeature::where('full_slug', $featureSlug)->exists()) {
                $this->warnings[] = "Optional feature '{$featureSlug}' not found in database - reference preserved but may not resolve";
            }

            // Check for dangling class reference
            if ($classSlug && ! CharacterClass::where('full_slug', $classSlug)->exists()) {
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
            $category = NoteCategory::tryFrom($noteData['category']);

            if (! $category) {
                $this->warnings[] = "Unknown note category '{$noteData['category']}' - skipping note";

                continue;
            }

            CharacterNote::create([
                'character_id' => $character->id,
                'category' => $category,
                'title' => $noteData['title'] ?? null,
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

        do {
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
}
