<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use App\Models\EntityChoice;
use App\Models\Feat;
use App\Models\Item;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for EntityChoice exposure on view-only entity endpoints.
 *
 * Issue #747: Entity endpoints need to expose choices for frontend display.
 * - `choices` field: flat EntityChoiceResource collection (non-equipment)
 * - `equipment_choices` field: grouped format for equipment choices
 */
#[Group('feature-db')]
class EntityChoicesApiTest extends TestCase
{
    use RefreshDatabase;

    // =====================================================================
    // RACE CHOICES
    // =====================================================================

    #[Test]
    public function race_show_includes_choices_field()
    {
        $race = Race::factory()->create(['name' => 'Test Race']);

        // Create ability score choice (e.g., Half-Elf's +1 to two abilities)
        EntityChoice::factory()
            ->abilityScoreChoice(quantity: 2, constraint: 'different')
            ->create([
                'reference_type' => Race::class,
                'reference_id' => $race->id,
                'description' => 'Choose two different ability scores to increase by 1',
            ]);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'choices' => [
                        '*' => [
                            'id',
                            'choice_type',
                            'choice_group',
                            'quantity',
                            'description',
                            'is_required',
                        ],
                    ],
                ],
            ]);

        $choices = $response->json('data.choices');
        expect($choices)->toHaveCount(1);
        expect($choices[0]['choice_type'])->toBe('ability_score');
        expect($choices[0]['quantity'])->toBe(2);
        expect($choices[0]['constraint'])->toBe('different');
    }

    #[Test]
    public function race_show_includes_language_choices()
    {
        $race = Race::factory()->create(['name' => 'Test Race']);

        // Language choice (pick 1 language)
        EntityChoice::factory()
            ->languageChoice()
            ->create([
                'reference_type' => Race::class,
                'reference_id' => $race->id,
                'quantity' => 1,
                'description' => 'Choose one language',
            ]);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertOk();

        $choices = $response->json('data.choices');
        $languageChoice = collect($choices)->firstWhere('choice_type', 'language');

        expect($languageChoice)->not->toBeNull();
        expect($languageChoice['quantity'])->toBe(1);
    }

    #[Test]
    public function race_show_includes_proficiency_choices()
    {
        $race = Race::factory()->create(['name' => 'Test Race']);

        // Skill proficiency choice (pick 2 skills)
        EntityChoice::factory()
            ->skillChoice(quantity: 2)
            ->create([
                'reference_type' => Race::class,
                'reference_id' => $race->id,
                'description' => 'Choose two skills',
            ]);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertOk();

        $choices = $response->json('data.choices');
        $proficiencyChoice = collect($choices)->firstWhere('choice_type', 'proficiency');

        expect($proficiencyChoice)->not->toBeNull();
        expect($proficiencyChoice['quantity'])->toBe(2);
        expect($proficiencyChoice['proficiency_type'])->toBe('skill');
    }

    #[Test]
    public function race_show_includes_spell_choices()
    {
        $race = Race::factory()->create(['name' => 'Test Race']);

        // Cantrip choice
        EntityChoice::factory()
            ->cantripChoice()
            ->create([
                'reference_type' => Race::class,
                'reference_id' => $race->id,
                'quantity' => 1,
                'description' => 'Choose one cantrip',
            ]);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertOk();

        $choices = $response->json('data.choices');
        $spellChoice = collect($choices)->firstWhere('choice_type', 'spell');

        expect($spellChoice)->not->toBeNull();
        expect($spellChoice['spell_max_level'])->toBe(0); // Cantrip
    }

    #[Test]
    public function race_show_excludes_equipment_from_choices_field()
    {
        $race = Race::factory()->create(['name' => 'Test Race']);

        // Equipment choice should NOT appear in choices (goes to equipment_choices)
        EntityChoice::factory()
            ->equipmentChoice()
            ->create([
                'reference_type' => Race::class,
                'reference_id' => $race->id,
            ]);

        // Ability score choice should appear
        EntityChoice::factory()
            ->abilityScoreChoice()
            ->create([
                'reference_type' => Race::class,
                'reference_id' => $race->id,
            ]);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertOk();

        $choices = $response->json('data.choices');

        // Should only have ability_score, not equipment
        expect($choices)->toHaveCount(1);
        expect($choices[0]['choice_type'])->toBe('ability_score');
    }

    #[Test]
    public function race_show_returns_empty_choices_when_none_exist()
    {
        $race = Race::factory()->create(['name' => 'Test Race']);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertOk();

        $choices = $response->json('data.choices');
        expect($choices)->toBe([]);
    }

    // =====================================================================
    // BACKGROUND CHOICES
    // =====================================================================

    #[Test]
    public function background_show_includes_choices_field()
    {
        $background = Background::factory()->create(['name' => 'Test Background']);

        // Language choice
        EntityChoice::factory()
            ->languageChoice()
            ->create([
                'reference_type' => Background::class,
                'reference_id' => $background->id,
                'quantity' => 2,
                'description' => 'Choose two languages',
            ]);

        $response = $this->getJson("/api/v1/backgrounds/{$background->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'choices' => [
                        '*' => [
                            'id',
                            'choice_type',
                            'choice_group',
                            'quantity',
                        ],
                    ],
                ],
            ]);

        $choices = $response->json('data.choices');
        expect($choices)->toHaveCount(1);
        expect($choices[0]['choice_type'])->toBe('language');
        expect($choices[0]['quantity'])->toBe(2);
    }

    #[Test]
    public function background_show_includes_equipment_choices_field()
    {
        $background = Background::factory()->create(['name' => 'Test Background']);
        $item1 = Item::factory()->create(['name' => 'Longsword']);
        $item2 = Item::factory()->create(['name' => 'Shortsword']);

        // Equipment choice with two options
        EntityChoice::factory()
            ->equipmentChoice(option: 1)
            ->create([
                'reference_type' => Background::class,
                'reference_id' => $background->id,
                'choice_group' => 'weapon_choice',
                'target_type' => 'item',
                'target_slug' => $item1->slug,
                'description' => 'Longsword',
            ]);

        EntityChoice::factory()
            ->equipmentChoice(option: 2)
            ->create([
                'reference_type' => Background::class,
                'reference_id' => $background->id,
                'choice_group' => 'weapon_choice',
                'target_type' => 'item',
                'target_slug' => $item2->slug,
                'description' => 'Shortsword',
            ]);

        $response = $this->getJson("/api/v1/backgrounds/{$background->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'equipment_choices' => [
                        '*' => [
                            'choice_group',
                            'quantity',
                            'options' => [
                                '*' => [
                                    'option',
                                    'label',
                                    'items',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $equipmentChoices = $response->json('data.equipment_choices');
        expect($equipmentChoices)->toHaveCount(1);
        expect($equipmentChoices[0]['choice_group'])->toBe('weapon_choice');
        expect($equipmentChoices[0]['options'])->toHaveCount(2);
    }

    #[Test]
    public function background_show_separates_equipment_and_other_choices()
    {
        $background = Background::factory()->create(['name' => 'Test Background']);
        $item = Item::factory()->create(['name' => 'Dagger']);

        // Language choice (should be in choices)
        EntityChoice::factory()
            ->languageChoice()
            ->create([
                'reference_type' => Background::class,
                'reference_id' => $background->id,
            ]);

        // Equipment choice (should be in equipment_choices)
        EntityChoice::factory()
            ->equipmentChoice()
            ->create([
                'reference_type' => Background::class,
                'reference_id' => $background->id,
                'target_type' => 'item',
                'target_slug' => $item->slug,
            ]);

        $response = $this->getJson("/api/v1/backgrounds/{$background->id}");

        $response->assertOk();

        $choices = $response->json('data.choices');
        $equipmentChoices = $response->json('data.equipment_choices');

        // Language should be in choices only
        expect(collect($choices)->where('choice_type', 'language'))->toHaveCount(1);
        expect(collect($choices)->where('choice_type', 'equipment'))->toHaveCount(0);

        // Equipment should be in equipment_choices only
        expect($equipmentChoices)->toHaveCount(1);
    }

    // =====================================================================
    // FEAT CHOICES
    // =====================================================================

    #[Test]
    public function feat_show_includes_choices_field()
    {
        $feat = Feat::factory()->create(['name' => 'Test Feat']);

        // Spell choice
        EntityChoice::factory()
            ->spellChoice(maxLevel: 1)
            ->create([
                'reference_type' => Feat::class,
                'reference_id' => $feat->id,
                'quantity' => 1,
                'description' => 'Choose one 1st-level spell',
            ]);

        // Ability score choice (half-feat)
        EntityChoice::factory()
            ->abilityScoreChoice(quantity: 1)
            ->create([
                'reference_type' => Feat::class,
                'reference_id' => $feat->id,
                'description' => 'Increase one ability score by 1',
            ]);

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'choices' => [
                        '*' => [
                            'id',
                            'choice_type',
                            'choice_group',
                            'quantity',
                        ],
                    ],
                ],
            ]);

        $choices = $response->json('data.choices');
        expect($choices)->toHaveCount(2);

        $choiceTypes = collect($choices)->pluck('choice_type')->sort()->values()->toArray();
        expect($choiceTypes)->toBe(['ability_score', 'spell']);
    }

    #[Test]
    public function feat_show_includes_spell_choice_constraints()
    {
        $feat = Feat::factory()->create(['name' => 'Magic Initiate Test']);

        // Cantrip choice from specific class
        EntityChoice::factory()
            ->cantripChoice(classSlug: 'wizard')
            ->create([
                'reference_type' => Feat::class,
                'reference_id' => $feat->id,
                'quantity' => 2,
                'description' => 'Choose two wizard cantrips',
            ]);

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk();

        $choices = $response->json('data.choices');
        $spellChoice = collect($choices)->firstWhere('choice_type', 'spell');

        expect($spellChoice)->not->toBeNull();
        expect($spellChoice['spell_max_level'])->toBe(0);
        expect($spellChoice['spell_list_slug'])->toBe('wizard');
        expect($spellChoice['quantity'])->toBe(2);
    }

    #[Test]
    public function feat_show_includes_proficiency_choices()
    {
        $feat = Feat::factory()->create(['name' => 'Skilled Test']);

        // Skill choice
        EntityChoice::factory()
            ->skillChoice(quantity: 3)
            ->create([
                'reference_type' => Feat::class,
                'reference_id' => $feat->id,
                'description' => 'Choose three skills',
            ]);

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk();

        $choices = $response->json('data.choices');
        $proficiencyChoice = collect($choices)->firstWhere('choice_type', 'proficiency');

        expect($proficiencyChoice)->not->toBeNull();
        expect($proficiencyChoice['quantity'])->toBe(3);
        expect($proficiencyChoice['proficiency_type'])->toBe('skill');
    }
}
