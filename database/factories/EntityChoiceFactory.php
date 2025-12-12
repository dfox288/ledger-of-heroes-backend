<?php

namespace Database\Factories;

use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\EntityChoice;
use App\Models\Feat;
use App\Models\Race;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntityChoiceFactory extends Factory
{
    protected $model = EntityChoice::class;

    public function definition(): array
    {
        return [
            'reference_type' => Race::class,
            'reference_id' => Race::factory(),
            'choice_type' => $this->faker->randomElement(EntityChoice::CHOICE_TYPES),
            'choice_group' => $this->faker->slug(2),
            'quantity' => 1,
            'constraint' => null,
            'choice_option' => null,
            'target_type' => null,
            'target_slug' => null,
            'spell_max_level' => null,
            'spell_list_slug' => null,
            'spell_school_slug' => null,
            'proficiency_type' => null,
            'constraints' => null,
            'description' => null,
            'level_granted' => 1,
            'is_required' => true,
        ];
    }

    /**
     * Language choice (unrestricted).
     */
    public function languageChoice(): static
    {
        return $this->state(fn () => [
            'choice_type' => 'language',
            'choice_group' => 'language_choice',
        ]);
    }

    /**
     * Language choice with specific options.
     */
    public function restrictedLanguageChoice(string $languageSlug, int $option): static
    {
        return $this->state(fn () => [
            'choice_type' => 'language',
            'choice_group' => 'language_choice',
            'choice_option' => $option,
            'target_type' => 'language',
            'target_slug' => $languageSlug,
        ]);
    }

    /**
     * Spell choice (cantrip).
     */
    public function cantripChoice(?string $classSlug = null): static
    {
        return $this->state(fn () => [
            'choice_type' => 'spell',
            'choice_group' => 'cantrip_choice',
            'spell_max_level' => 0,
            'spell_list_slug' => $classSlug,
        ]);
    }

    /**
     * Spell choice (leveled spell).
     */
    public function spellChoice(int $maxLevel = 1, ?string $classSlug = null): static
    {
        return $this->state(fn () => [
            'choice_type' => 'spell',
            'choice_group' => 'spell_choice',
            'spell_max_level' => $maxLevel,
            'spell_list_slug' => $classSlug,
        ]);
    }

    /**
     * Proficiency choice (skill).
     */
    public function skillChoice(int $quantity = 1): static
    {
        return $this->state(fn () => [
            'choice_type' => 'proficiency',
            'choice_group' => 'skill_choice',
            'quantity' => $quantity,
            'proficiency_type' => 'skill',
        ]);
    }

    /**
     * Proficiency choice with restricted skills.
     */
    public function restrictedSkillChoice(string $skillSlug, int $option): static
    {
        return $this->state(fn () => [
            'choice_type' => 'proficiency',
            'choice_group' => 'skill_choice',
            'choice_option' => $option,
            'proficiency_type' => 'skill',
            'target_type' => 'skill',
            'target_slug' => $skillSlug,
        ]);
    }

    /**
     * Ability score choice.
     */
    public function abilityScoreChoice(int $quantity = 1, ?string $constraint = 'different'): static
    {
        return $this->state(fn () => [
            'choice_type' => 'ability_score',
            'choice_group' => 'ability_score_choice',
            'quantity' => $quantity,
            'constraint' => $constraint,
        ]);
    }

    /**
     * Equipment choice.
     */
    public function equipmentChoice(int $option = 1): static
    {
        return $this->state(fn () => [
            'choice_type' => 'equipment',
            'choice_group' => 'equipment_choice',
            'choice_option' => $option,
        ]);
    }

    /**
     * For a Background.
     */
    public function forBackground(): static
    {
        return $this->state(fn () => [
            'reference_type' => Background::class,
            'reference_id' => Background::factory(),
        ]);
    }

    /**
     * For a CharacterClass.
     */
    public function forClass(): static
    {
        return $this->state(fn () => [
            'reference_type' => CharacterClass::class,
            'reference_id' => CharacterClass::factory(),
        ]);
    }

    /**
     * For a Feat.
     */
    public function forFeat(): static
    {
        return $this->state(fn () => [
            'reference_type' => Feat::class,
            'reference_id' => Feat::factory(),
        ]);
    }
}
